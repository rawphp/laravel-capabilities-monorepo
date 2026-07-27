<?php

// Spec-derived unit tests for D-010 mode x required matrix. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Audit\AuditOutbox;
use Rawphp\Capabilities\Tests\Fixtures\AuditHelpers;

it("happy: success when mode=best_effort required=True audit_ok=True run_ok=True [D-010]", function () {
    $h = AuditHelpers::harness(['mode' => 'best_effort', 'required' => true, 'fail_audit' => false]);
    $r = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options());
    expect($r->isOk())->toBeTrue();
});

it("edge: domain failed when mode=best_effort required=True audit_ok=True run_ok=False [D-010]", function () {
    $h = AuditHelpers::harness(['mode' => 'best_effort', 'required' => true, 'fail_audit' => false, 'run_throws' => 'domain boom']);
    $r = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options());
    expect($r->isFailed())->toBeTrue()->and($r->errorCode())->toBe('domain_error');
});

it("happy: domain success client success when mode=best_effort required=True audit_ok=False run_ok=True [D-010]", function () {
    $h = AuditHelpers::harness(['mode' => 'best_effort', 'required' => true, 'fail_audit' => true]);
    $r = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options());
    expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
});

it("happy: outbox written when mode=best_effort required=True audit_ok=False run_ok=True [D-010]", function () {
    $h = AuditHelpers::harness(['mode' => 'best_effort', 'required' => true, 'fail_audit' => true]);
    $r = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options());
    expect($r->isOk())->toBeTrue()->and($h['outbox']->pending())->not->toBeEmpty();
});

it("edge: domain failed when mode=best_effort required=True audit_ok=False run_ok=False [D-010]", function () {
    $h = AuditHelpers::harness(['mode' => 'best_effort', 'required' => true, 'fail_audit' => true, 'run_throws' => 'domain boom']);
    $r = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options());
    expect($r->isFailed())->toBeTrue()->and($r->errorCode())->toBe('domain_error');
});

it("happy: success when mode=best_effort required=False audit_ok=True run_ok=True [D-010]", function () {
    $h = AuditHelpers::harness(['mode' => 'best_effort', 'required' => false, 'fail_audit' => false]);
    $r = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options());
    expect($r->isOk())->toBeTrue();
});

it("edge: domain failed when mode=best_effort required=False audit_ok=True run_ok=False [D-010]", function () {
    $h = AuditHelpers::harness(['mode' => 'best_effort', 'required' => false, 'fail_audit' => false, 'run_throws' => 'domain boom']);
    $r = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options());
    expect($r->isFailed())->toBeTrue()->and($r->errorCode())->toBe('domain_error');
});

it("happy: domain success client success when mode=best_effort required=False audit_ok=False run_ok=True [D-010]", function () {
    $h = AuditHelpers::harness(['mode' => 'best_effort', 'required' => false, 'fail_audit' => true]);
    $r = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options());
    expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
});

it("edge: retry optional when mode=best_effort required=False audit_ok=False run_ok=True [D-010]", function () {
    $h = AuditHelpers::harness(['mode' => 'best_effort', 'required' => false, 'fail_audit' => true]);
    $r = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options());
    expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
});

it("edge: domain failed when mode=best_effort required=False audit_ok=False run_ok=False [D-010]", function () {
    $h = AuditHelpers::harness(['mode' => 'best_effort', 'required' => false, 'fail_audit' => true, 'run_throws' => 'domain boom']);
    $r = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options());
    expect($r->isFailed())->toBeTrue()->and($r->errorCode())->toBe('domain_error');
});

it("happy: success when mode=strict required=True audit_ok=True run_ok=True [D-010]", function () {
    $h = AuditHelpers::harness(['mode' => 'strict', 'required' => true, 'fail_audit' => false]);
    $r = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options());
    expect($r->isOk())->toBeTrue();
});

it("edge: domain failed when mode=strict required=True audit_ok=True run_ok=False [D-010]", function () {
    $h = AuditHelpers::harness(['mode' => 'strict', 'required' => true, 'fail_audit' => false, 'run_throws' => 'domain boom']);
    $r = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options());
    expect($r->isFailed())->toBeTrue()->and($r->errorCode())->toBe('domain_error');
});

it("edge: strict audit failure behavior when mode=strict required=True audit_ok=False run_ok=True [D-010]", function () {
    $h = AuditHelpers::harness(['mode' => 'strict', 'required' => true, 'fail_audit' => true]);
    $r = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options());
    expect($r->isOk())->toBeFalse()->and($r->errorCode())->toBe('audit_failed')->and($h['runCount']->value)->toBe(1);
});

it("edge: domain failed when mode=strict required=True audit_ok=False run_ok=False [D-010]", function () {
    $h = AuditHelpers::harness(['mode' => 'strict', 'required' => true, 'fail_audit' => true, 'run_throws' => 'domain boom']);
    $r = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options());
    expect($r->isFailed())->toBeTrue()->and($r->errorCode())->toBe('domain_error');
});

it("happy: success when mode=strict required=False audit_ok=True run_ok=True [D-010]", function () {
    $h = AuditHelpers::harness(['mode' => 'strict', 'required' => false, 'fail_audit' => false]);
    $r = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options());
    expect($r->isOk())->toBeTrue();
});

it("edge: domain failed when mode=strict required=False audit_ok=True run_ok=False [D-010]", function () {
    $h = AuditHelpers::harness(['mode' => 'strict', 'required' => false, 'fail_audit' => false, 'run_throws' => 'domain boom']);
    $r = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options());
    expect($r->isFailed())->toBeTrue()->and($r->errorCode())->toBe('domain_error');
});

it("edge: strict audit failure behavior when mode=strict required=False audit_ok=False run_ok=True [D-010]", function () {
    $h = AuditHelpers::harness(['mode' => 'strict', 'required' => false, 'fail_audit' => true]);
    $r = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options());
    expect($r->isOk())->toBeFalse()->and($r->errorCode())->toBe('audit_failed')->and($h['runCount']->value)->toBe(1);
});

it("edge: domain failed when mode=strict required=False audit_ok=False run_ok=False [D-010]", function () {
    $h = AuditHelpers::harness(['mode' => 'strict', 'required' => false, 'fail_audit' => true, 'run_throws' => 'domain boom']);
    $r = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options());
    expect($r->isFailed())->toBeTrue()->and($r->errorCode())->toBe('domain_error');
});

