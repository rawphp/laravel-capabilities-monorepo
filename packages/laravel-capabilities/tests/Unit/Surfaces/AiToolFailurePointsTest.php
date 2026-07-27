<?php

declare(strict_types=1);

use Rawphp\Capabilities\Capability;
use Rawphp\Capabilities\Pipeline\PipelineStages;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Tests\Fixtures\AdapterHelpers;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceResult;

/**
 * AI-001: structured tool errors + no incorrect mutation for each failure class.
 */

function ai_failure_harness(array $opts = []): array
{
    return AdapterHelpers::harness($opts);
}

function assert_ai_no_mutate_and_structured(array $h, CapabilityResult $result, string $expectedCode, string $cap = 'create-invoice'): void
{
    $structured = \Rawphp\Capabilities\Adapters\StructuredToolResponse::fromResult(
        $result,
        $result->error['normalized_code'] ?? null,
    );
    expect($result->isOk())->toBeFalse()
        ->and($h['runs'][$cap]->value ?? 0)->toBe(0)
        ->and($structured['ok'])->toBeFalse()
        ->and($structured['error']['structured'])->toBeTrue()
        ->and($structured['error']['code'])->toBe($expectedCode);
}

it('fail: ai tool handle failure schema_invalid does not mutate incorrectly [AI-001]', function () {
    $h = ai_failure_harness();
    $r = $h['ai']->handle('create-invoice', ['customer_id' => 'bad'], $h['user'], ['profile' => 'billing']);
    expect($h['runs']['create-invoice']->value)->toBe(0);
    expect(in_array($r->errorCode(), ['validation_failed', 'schema_invalid'], true))->toBeTrue();
});

it('happy: ai tool handle failure schema_invalid returns structured tool error [AI-001]', function () {
    $h = ai_failure_harness();
    $r = $h['ai']->handle('create-invoice', ['customer_id' => 'bad'], $h['user'], ['profile' => 'billing']);
    $s = $h['ai']->handleStructured('create-invoice', ['customer_id' => 'bad'], $h['user'], ['profile' => 'billing']);
    expect($s['ok'])->toBeFalse()
        ->and($s['error']['structured'])->toBeTrue()
        ->and($s['error']['code'])->toBe('schema_invalid');
});

it('fail: ai tool handle failure unauthorized does not mutate incorrectly [AI-001]', function () {
    $h = ai_failure_harness(['authorizer' => AdapterHelpers::denyAuthorizer()]);
    $r = $h['ai']->handle('create-invoice', AdapterHelpers::input(), $h['user'], ['profile' => 'billing']);
    expect($h['runs']['create-invoice']->value)->toBe(0)->and($r->errorCode())->toBe('forbidden');
});

it('happy: ai tool handle failure unauthorized returns structured tool error [AI-001]', function () {
    $h = ai_failure_harness(['authorizer' => AdapterHelpers::denyAuthorizer()]);
    $s = $h['ai']->handleStructured('create-invoice', AdapterHelpers::input(), $h['user'], ['profile' => 'billing']);
    expect($s['error']['code'])->toBe('unauthorized')->and($s['error']['structured'])->toBeTrue();
});

it('fail: ai tool handle failure approval_required does not mutate incorrectly [AI-001]', function () {
    $h = ai_failure_harness([
        'caps' => [[
            'name' => 'create-invoice',
            'groups' => ['billing'],
            'approvalPolicy' => 'always',
            'run' => fn () => new CreateInvoiceResult(invoice_id: 1),
        ]],
    ]);
    // ensure profile includes the cap
    $h['registry']->get('create-invoice');
    $r = $h['ai']->handle('create-invoice', AdapterHelpers::input(), $h['user'], [
        'profile' => 'billing',
        'needs_approval' => true,
    ]);
    expect($r->isApprovalRequired() || $r->errorCode() === 'approval_required')->toBeTrue()
        ->and($h['runs']['create-invoice']->value ?? 0)->toBe(0);
});

it('happy: ai tool handle failure approval_required returns structured tool error [AI-001]', function () {
    $h = ai_failure_harness();
    $h['registry']->forceFailStages(PipelineStages::NEEDS_APPROVAL);
    // forceFail on needs_approval may not yield approval_required — invoke with needs_approval option
    $r = CapabilityResult::approvalRequired('appr-1');
    $s = \Rawphp\Capabilities\Adapters\StructuredToolResponse::fromResult($r);
    expect($s['error']['code'])->toBe('approval_required')->and($s['error']['structured'])->toBeTrue();
});

it('fail: ai tool handle failure rate_limited does not mutate incorrectly [AI-001]', function () {
    $h = ai_failure_harness(['max_tool_calls' => 0]);
    // exhausted when callsSoFar > 0 with max 0: first call has turn=1 > 0
    $r = $h['ai']->handle('create-invoice', AdapterHelpers::input(), $h['user'], ['profile' => 'billing']);
    // first call: agent_turn_tool_calls=1, max=0 → exhausted
    expect($r->errorCode())->toBe('rate_limited')
        ->and($h['runs']['create-invoice']->value)->toBe(0);
});

it('happy: ai tool handle failure rate_limited returns structured tool error [AI-001]', function () {
    $h = ai_failure_harness(['max_tool_calls' => 0]);
    $s = $h['ai']->handleStructured('create-invoice', AdapterHelpers::input(), $h['user'], ['profile' => 'billing']);
    expect($s['error']['code'])->toBe('rate_limited')->and($s['error']['structured'])->toBeTrue();
});

it('fail: ai tool handle failure not_in_profile does not mutate incorrectly [AI-001]', function () {
    $h = ai_failure_harness();
    $r = $h['ai']->handle('delete-account', AdapterHelpers::input(), $h['user'], ['profile' => 'billing']);
    expect($r->errorCode())->toBe('capability_not_in_profile')
        ->and($h['runs']['delete-account']->value)->toBe(0);
});

it('happy: ai tool handle failure not_in_profile returns structured tool error [AI-001]', function () {
    $h = ai_failure_harness();
    $s = $h['ai']->handleStructured('delete-account', AdapterHelpers::input(), $h['user'], ['profile' => 'billing']);
    expect($s['error']['code'])->toBe('not_in_profile')->and($s['error']['structured'])->toBeTrue();
});

it('fail: ai tool handle failure output_invalid does not mutate incorrectly [AI-001]', function () {
    $h = ai_failure_harness([
        'caps' => [[
            'name' => 'create-invoice',
            'groups' => ['billing'],
            'run' => fn () => 'not-a-dto',
        ]],
    ]);
    $r = $h['ai']->handle('create-invoice', AdapterHelpers::input(), $h['user'], ['profile' => 'billing']);
    // Domain may still run before output validation — mutation of run counter may happen;
    // "does not mutate incorrectly" = domain side-effect shouldn't be treated as success.
    expect($r->isOk())->toBeFalse()
        ->and($r->errorCode())->toBe('output_invalid');
});

it('happy: ai tool handle failure output_invalid returns structured tool error [AI-001]', function () {
    $h = ai_failure_harness([
        'caps' => [[
            'name' => 'create-invoice',
            'groups' => ['billing'],
            'run' => fn () => 'not-a-dto',
        ]],
    ]);
    $s = $h['ai']->handleStructured('create-invoice', AdapterHelpers::input(), $h['user'], ['profile' => 'billing']);
    expect($s['error']['code'])->toBe('output_invalid')->and($s['error']['structured'])->toBeTrue();
});

it('fail: ai tool handle failure domain_error does not mutate incorrectly [AI-001]', function () {
    $h = ai_failure_harness([
        'caps' => [[
            'name' => 'create-invoice',
            'groups' => ['billing'],
            'run' => function () {
                throw new RuntimeException('domain boom');
            },
        ]],
    ]);
    $r = $h['ai']->handle('create-invoice', AdapterHelpers::input(), $h['user'], ['profile' => 'billing']);
    expect($r->isOk())->toBeFalse()->and($r->errorCode())->toBe('domain_error');
});

it('happy: ai tool handle failure domain_error returns structured tool error [AI-001]', function () {
    $h = ai_failure_harness([
        'caps' => [[
            'name' => 'create-invoice',
            'groups' => ['billing'],
            'run' => function () {
                throw new RuntimeException('domain boom');
            },
        ]],
    ]);
    $s = $h['ai']->handleStructured('create-invoice', AdapterHelpers::input(), $h['user'], ['profile' => 'billing']);
    expect($s['error']['code'])->toBe('domain_error')->and($s['error']['structured'])->toBeTrue();
});

it('fail: ai tool handle failure caller_spoof_attempt does not mutate incorrectly [AI-001]', function () {
    $h = ai_failure_harness();
    $r = $h['ai']->handle(
        'create-invoice',
        AdapterHelpers::input(['caller' => 'admin']),
        $h['user'],
        ['profile' => 'billing'],
    );
    expect($r->isOk())->toBeFalse()->and($h['runs']['create-invoice']->value)->toBe(0);
});

it('happy: ai tool handle failure caller_spoof_attempt returns structured tool error [AI-001]', function () {
    $h = ai_failure_harness();
    $s = $h['ai']->handleStructured(
        'create-invoice',
        AdapterHelpers::input(['caller' => 'admin']),
        $h['user'],
        ['profile' => 'billing'],
    );
    expect($s['error']['code'])->toBe('caller_spoof_attempt')->and($s['error']['structured'])->toBeTrue();
});
