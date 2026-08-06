<?php

declare(strict_types=1);

use Rawphp\Capabilities\Contracts\IdempotencyStore;
use Rawphp\CapabilitiesAi\Support\StoreBoundIdempotencyReadiness;

it('is not ready when store unbound', function () {
    $r = StoreBoundIdempotencyReadiness::unbound();
    expect($r->isReady())->toBeFalse();
});

it('is ready when store find ping succeeds', function () {
    $store = new class implements IdempotencyStore
    {
        public function find(?string $tenantId, string $actorType, string $actorId, string $capabilityName, string $key): ?array
        {
            return null;
        }

        public function put(array $record): array
        {
            return $record;
        }

        public function update(?string $tenantId, string $actorType, string $actorId, string $capabilityName, string $key, array $attributes): ?array
        {
            return null;
        }
    };

    $r = StoreBoundIdempotencyReadiness::forStore($store);
    expect($r->isReady())->toBeTrue();
});

it('is not ready when store find ping throws', function () {
    $store = new class implements IdempotencyStore
    {
        public function find(?string $tenantId, string $actorType, string $actorId, string $capabilityName, string $key): ?array
        {
            throw new RuntimeException('down');
        }

        public function put(array $record): array
        {
            return $record;
        }

        public function update(?string $tenantId, string $actorType, string $actorId, string $capabilityName, string $key, array $attributes): ?array
        {
            return null;
        }
    };

    expect(StoreBoundIdempotencyReadiness::forStore($store)->isReady())->toBeFalse();
});
