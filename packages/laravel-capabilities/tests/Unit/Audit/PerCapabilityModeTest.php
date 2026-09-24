<?php

// Per-capability audit mode override (D-010): a capability may tighten the
// global best_effort default to strict. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Capability;
use Rawphp\Capabilities\Registry\CapabilityDefinition;
use Rawphp\Capabilities\Tests\Fixtures\AuditHelpers;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceInput;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceResult;

it('happy: capability audit mode strict fails the invoke under global best_effort [D-010]', function () {
    $h = AuditHelpers::harness(['mode' => 'best_effort', 'fail_audit' => true, 'audit' => ['mode' => 'strict']]);
    $r = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options());

    expect($h['registry']->auditMode())->toBe('best_effort')
        ->and($r->isOk())->toBeFalse()
        ->and($r->errorCode())->toBe('audit_failed')
        ->and($h['runCount']->value)->toBe(1);
});

it('edge: strict override applies only to its capability; siblings keep global best_effort [D-010]', function () {
    $h = AuditHelpers::harness(['mode' => 'best_effort', 'fail_audit' => true, 'audit' => ['mode' => 'strict']]);
    Capability::define('lenient-cap')
        ->input(CreateInvoiceInput::class)
        ->output(CreateInvoiceResult::class)
        ->surfaces(['http'])
        ->run(fn () => new CreateInvoiceResult(invoice_id: 7))
        ->register($h['registry']);

    $r = $h['registry']->invoke('lenient-cap', AuditHelpers::input(), AuditHelpers::options());

    expect($r->isOk())->toBeTrue();
});

it('happy: effective audit mode resolves override over global default [D-010]', function () {
    $strict = new CapabilityDefinition(name: 'a', input: 'X', audit: ['mode' => 'strict']);
    $plain = new CapabilityDefinition(name: 'b', input: 'X', audit: ['force' => true]);
    $bool = new CapabilityDefinition(name: 'c', input: 'X', audit: true);

    expect($strict->auditMode('best_effort'))->toBe('strict')
        ->and($strict->auditMode('strict'))->toBe('strict')
        ->and($plain->auditMode('best_effort'))->toBe('best_effort')
        ->and($plain->auditMode('strict'))->toBe('strict')
        ->and($bool->auditMode('best_effort'))->toBe('best_effort');
});

it('fail: capability cannot loosen audit mode to best_effort [D-010]', function () {
    new CapabilityDefinition(name: 'loose', input: 'X', audit: ['mode' => 'best_effort']);
})->throws(InvalidArgumentException::class, 'can only tighten');

it('fail: unknown capability audit mode fails closed at definition [D-010]', function () {
    Capability::define('bad-mode')->input('X')->audit(['mode' => 'lenient'])->toDefinition();
})->throws(InvalidArgumentException::class, 'lenient');

it('fail: non-string capability audit mode fails closed at definition [D-010]', function () {
    new CapabilityDefinition(name: 'bad-type', input: 'X', audit: ['mode' => true]);
})->throws(InvalidArgumentException::class, 'Undefined audit mode "bool"');
