<?php

// output_invalid is a server bug (D-014): always leave an audit trail, even when the
// capability would otherwise skip audit (readOnly / audit: false). Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Tests\Fixtures\AuditHelpers;

$badRun = fn () => fn ($in) => ['wrong' => true];

it('happy: readOnly capability with invalid output still writes a tagged audit entry [D-014]', function () use ($badRun) {
    $h = AuditHelpers::harness(['readOnly' => true, 'name' => 'ro-bad-output', 'run' => $badRun()]);

    $r = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options());

    expect($r->errorCode())->toBe('output_invalid')
        ->and($h['audit']->all())->toHaveCount(1)
        ->and($h['audit']->all()[0]['event'])->toBe('capability.output_invalid')
        ->and($h['audit']->all()[0]['result']['code'])->toBe('output_invalid');
});

it('happy: audit false capability with invalid output still writes a tagged audit entry [D-014]', function () use ($badRun) {
    $h = AuditHelpers::harness(['audit' => false, 'name' => 'no-audit-bad-output', 'run' => $badRun()]);

    $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options());

    expect($h['audit']->all())->toHaveCount(1)
        ->and($h['audit']->all()[0]['event'])->toBe('capability.output_invalid');
});

it('happy: mutating capability output_invalid audit entry carries the distinct tag [D-014]', function () use ($badRun) {
    $h = AuditHelpers::harness(['name' => 'mut-bad-output', 'run' => $badRun()]);

    $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options());

    expect($h['audit']->all()[0]['event'])->toBe('capability.output_invalid');
});

it('edge: readOnly capability with valid output still skips audit [D-010]', function () {
    $h = AuditHelpers::harness(['readOnly' => true, 'name' => 'ro-good-output']);

    $r = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options());

    expect($r->isOk())->toBeTrue()
        ->and($h['audit']->all())->toBeEmpty();
});

it('edge: audit disabled globally is still respected for output_invalid [D-010]', function () use ($badRun) {
    $h = AuditHelpers::harness(['readOnly' => true, 'audit_enabled' => false, 'name' => 'ro-off', 'run' => $badRun()]);

    $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options());

    expect($h['audit']->all())->toBeEmpty();
});

it('fail: output_invalid audit write failure enqueues outbox even when not required and no outbox bound [D-010]', function () use ($badRun) {
    $h = AuditHelpers::harness([
        'readOnly' => true,
        'fail_audit' => true,
        'required' => false,
        'name' => 'ro-bad-output-writer-down',
        'run' => $badRun(),
    ]);
    $h['registry']->withAuditOutbox(null);

    $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options());

    $outbox = $h['registry']->auditOutbox();
    expect($outbox)->not->toBeNull()
        ->and($outbox->pending())->toHaveCount(1)
        ->and($outbox->pending()[0]['entry']['event'])->toBe('capability.output_invalid');
});
