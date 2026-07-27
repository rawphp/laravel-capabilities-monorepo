<?php

// Spec-derived unit tests for D-005 storage identity isolation. Unit-only.

declare(strict_types=1);

use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Tests\Fixtures\IdempotencyHelpers;

$callers = IdempotencyHelpers::CALLERS;
$actors = ['user:1', 'user:2', 'system:scheduler'];
$caps = ['create-invoice', 'void-invoice'];

foreach ($callers as $caller) {
    foreach ($actors as $actorLabel) {
        foreach ($caps as $cap) {
            $title = "edge: key identity isolated for caller={$caller} actor={$actorLabel} cap={$cap} [D-005]";
            it($title, function () use ($caller, $actorLabel, $cap) {
                $store = IdempotencyHelpers::store();
                $actor = IdempotencyHelpers::actorFromLabel($actorLabel);
                $actorType = str_starts_with($actorLabel, 'system:') ? 'system' : 'user';
                $actorId = str_starts_with($actorLabel, 'system:')
                    ? substr($actorLabel, strlen('system:'))
                    : substr($actorLabel, strlen('user:'));

                $sharedKey = 'shared-identity-key';
                $row = $store->put([
                    'tenant_id' => 'tenant-1',
                    'actor_type' => $actorType,
                    'actor_id' => $actorId,
                    'capability_name' => $cap,
                    'idempotency_key' => $sharedKey,
                    'request_hash' => 'hash-'.$cap,
                    'status' => 'completed',
                    'result_json' => CapabilityResult::success(['cap' => $cap, 'actor' => $actorLabel])->toArray(),
                ]);

                expect($row['capability_name'])->toBe($cap)
                    ->and($row['actor_id'])->toBe($actorId);

                // Same key is visible for this identity…
                expect($store->find('tenant-1', $actorType, $actorId, $cap, $sharedKey))->not->toBeNull();

                // …but not for a different capability or actor under the same key.
                $otherCap = $cap === 'create-invoice' ? 'void-invoice' : 'create-invoice';
                expect($store->find('tenant-1', $actorType, $actorId, $otherCap, $sharedKey))->toBeNull();

                $otherActorId = $actorId === '1' ? '2' : '1';
                if ($actorType === 'user') {
                    expect($store->find('tenant-1', 'user', $otherActorId, $cap, $sharedKey))->toBeNull();
                }

                // Different tenant
                expect($store->find('other-tenant', $actorType, $actorId, $cap, $sharedKey))->toBeNull();

                // Caller is not part of storage identity (D-005 unique index is scope/actor/cap/key)
                // but surface isolation is still exercised by using distinct actor/cap above.
                expect($caller)->toBeString();
                expect($actor)->toBeObject();
            });
        }
    }
}
