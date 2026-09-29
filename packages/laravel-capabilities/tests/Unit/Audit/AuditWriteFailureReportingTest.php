<?php

// L-104 / D-010: an audit write that fails is never silent (reported + metric), never leaks
// the driver exception on the wire in strict mode, and never breaks an approval whose state
// already changed.

declare(strict_types=1);

use Rawphp\Capabilities\Support\FailingAuditWriter;
use Rawphp\Capabilities\Tests\Fixtures\ApprovalHelpers;
use Rawphp\Capabilities\Tests\Fixtures\AuditHelpers;
use Rawphp\Capabilities\Tests\Fixtures\RecordingExceptionHandler;

afterEach(fn () => RecordingExceptionHandler::unbind());

it('best_effort: a failed audit write is reported to the host and counted, and the invoke still succeeds', function () {
    $handler = RecordingExceptionHandler::bind();
    $h = AuditHelpers::harness(['mode' => 'best_effort', 'fail_audit' => true, 'fail_message' => 'SQLSTATE[HY000]: insert into capabilities_audit_outbox ... payload_json']);

    $r = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options());

    expect($r->isOk())->toBeTrue()
        ->and($handler->reported)->toHaveCount(1)
        ->and($handler->reported[0]->getMessage())->toContain('SQLSTATE')
        ->and($handler->metrics->get('audit_write_failed_total', ['mode' => 'best_effort']))->toBe(1);
});

it('strict: the wire message is fixed and carries no driver exception text', function () {
    $handler = RecordingExceptionHandler::bind();
    $h = AuditHelpers::harness(['mode' => 'strict', 'fail_audit' => true, 'fail_message' => 'SQLSTATE[HY000]: insert ... payload_json = {"secret":1}']);

    $r = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options());

    expect($r->errorCode())->toBe('audit_failed')
        ->and($r->error['message'])->toBe('Audit failed.')
        ->and(json_encode($r->toArray()))->not->toContain('SQLSTATE')
        ->and($handler->reported)->toHaveCount(1)
        ->and($handler->metrics->get('audit_write_failed_total', ['mode' => 'strict']))->toBe(1);
});

it('reports nothing when the audit write succeeds', function () {
    $handler = RecordingExceptionHandler::bind();
    $h = AuditHelpers::harness();

    $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options());

    expect($handler->reported)->toBe([])
        ->and($handler->metrics->get('audit_write_failed_total', ['mode' => 'best_effort']))->toBe(0);
});

it('approvals: a throwing audit writer does not break request(), accept() or the executed result', function () {
    $handler = RecordingExceptionHandler::bind();
    $h = ApprovalHelpers::harness(['audit' => new FailingAuditWriter('outbox table missing')]);

    $row = $h['manager']->request(ApprovalHelpers::pendingRecord());
    $result = $h['manager']->accept((string) $row['id'], ApprovalHelpers::requester());

    expect($row['status'])->toBe('pending')
        ->and($result->isOk())->toBeTrue()
        ->and($h['runCount']->value)->toBe(1)
        ->and($h['store']->find((string) $row['id'])['result_status'])->toBe('ok')
        // requested + decided + executed each failed to audit: reported, not thrown.
        ->and(count($handler->reported))->toBe(3)
        ->and($handler->metrics->get('audit_write_failed_total', ['mode' => 'approval']))->toBe(3);
});
