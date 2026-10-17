<?php

// A rate_limited refusal frees its key only when no domain write can have
// survived and no approval owns the row; every other failure is stored (D-005).

declare(strict_types=1);

use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Tests\Fixtures\IdempotencyHelpers;

function settleRateLimited(): CapabilityResult
{
    return CapabilityResult::failure(code: 'rate_limited', message: 'Slow down.', extra: ['retry_after' => 60]);
}

/**
 * @return array{guard: mixed, store: mixed, definition: mixed, context: mixed, hash: string}
 */
function settleHarness(string $key): array
{
    $clock = IdempotencyHelpers::clock();
    $store = IdempotencyHelpers::store($clock);
    $guard = IdempotencyHelpers::guard($store, $clock);
    $definition = IdempotencyHelpers::mutatingDefinition();
    $context = IdempotencyHelpers::context();
    $hash = IdempotencyHelpers::hash(IdempotencyHelpers::inputA());

    expect($guard->lookup($definition, $context, $key, $hash)['action'])->toBe('continue');

    return ['guard' => $guard, 'store' => $store, 'definition' => $definition, 'context' => $context, 'hash' => $hash];
}

it('frees a processing claim on rate_limited when the domain is unchanged', function () {
    $h = settleHarness('settle-free');

    $stored = $h['guard']->settleFailure($h['definition'], $h['context'], 'settle-free', $h['hash'], settleRateLimited(), domainUnchanged: true);

    expect($stored)->toBeFalse()
        ->and($h['guard']->lookup($h['definition'], $h['context'], 'settle-free', $h['hash'])['action'])->toBe('continue');
});

it('stores rate_limited when a domain write may have survived', function () {
    $h = settleHarness('settle-written');

    $stored = $h['guard']->settleFailure($h['definition'], $h['context'], 'settle-written', $h['hash'], settleRateLimited(), domainUnchanged: false);

    expect($stored)->toBeTrue()
        ->and($h['store']->find('tenant-1', 'user', '1', 'create-invoice', 'settle-written')['status'])->toBe('failed');
});

it('stores rate_limited on a row an approval owns, keeping the link', function () {
    $h = settleHarness('settle-approval');
    $h['guard']->storeResult($h['definition'], $h['context'], 'settle-approval', $h['hash'], CapabilityResult::approvalRequired('appr-1'));

    $stored = $h['guard']->settleFailure($h['definition'], $h['context'], 'settle-approval', $h['hash'], settleRateLimited(), domainUnchanged: true);

    $row = $h['store']->find('tenant-1', 'user', '1', 'create-invoice', 'settle-approval');
    expect($stored)->toBeTrue()
        ->and($row['status'])->toBe('failed')
        ->and($row['approval_id'])->toBe('appr-1');
});

it('stores every other failure even when the domain is unchanged', function () {
    $h = settleHarness('settle-domain');

    $stored = $h['guard']->settleFailure(
        $h['definition'],
        $h['context'],
        'settle-domain',
        $h['hash'],
        CapabilityResult::failure(code: 'domain_error', message: 'Nope.'),
        domainUnchanged: true,
    );

    expect($stored)->toBeTrue()
        ->and($h['store']->find('tenant-1', 'user', '1', 'create-invoice', 'settle-domain')['status'])->toBe('failed');
});
