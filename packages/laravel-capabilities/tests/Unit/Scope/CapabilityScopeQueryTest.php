<?php

declare(strict_types=1);

// CapabilityScope::query with and without a factory.

use Rawphp\Capabilities\Support\CapabilityScope;
use Rawphp\Capabilities\Support\InMemoryScopedQueryFactory;

it('CapabilityScope::query throws without a query factory and returns a scoped query with one', function () {
    $factory = new InMemoryScopedQueryFactory;
    $scope = new CapabilityScope(tenantId: 't1');
    expect(fn () => $scope->query('Invoice'))->toThrow(RuntimeException::class);

    $scoped = $scope->withQueryFactory($factory);
    expect($scoped->query('Invoice'))->not->toBeNull()
        ->and($scoped->tenantId)->toBe('t1');
});
