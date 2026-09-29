<?php

declare(strict_types=1);

// L-501 / D-006: the scope an approval request was stamped with survives the row and comes
// back whole at execution — tenant, team, organization and JSON-safe attributes. Closures
// (the query factory) and non-scalar attributes are not persisted.

use Rawphp\Capabilities\Support\CapabilityScope;
use Rawphp\Capabilities\Support\InMemoryScopedQueryFactory;

it('toRow persists tenant, team, organization and scalar attributes only', function () {
    $scope = (new CapabilityScope(
        tenantId: 't1',
        teamId: 'team-9',
        organizationId: 'org-3',
        attributes: ['region' => 'au', 'seats' => 5, 'trial' => false, 'note' => null, 'flags' => ['x'], 'obj' => new stdClass],
    ))->withQueryFactory(new InMemoryScopedQueryFactory);

    expect($scope->toRow())->toBe([
        'tenant_id' => 't1',
        'team_id' => 'team-9',
        'organization_id' => 'org-3',
        'attributes' => ['region' => 'au', 'seats' => 5, 'trial' => false, 'note' => null],
    ]);
});

it('fromRow rebuilds the stamped scope with the row tenant as tenant authority', function () {
    $row = ['tenant_id' => 't1', 'scope' => ['tenant_id' => 't1', 'team_id' => 'team-9', 'organization_id' => 'org-3', 'attributes' => ['region' => 'au']]];

    $scope = CapabilityScope::fromRow($row);

    expect($scope)->toBeInstanceOf(CapabilityScope::class)
        ->and($scope->tenantId)->toBe('t1')
        ->and($scope->teamId)->toBe('team-9')
        ->and($scope->organizationId)->toBe('org-3')
        ->and($scope->attributes)->toBe(['region' => 'au']);
});

it('fromRow yields a tenant-only scope for legacy rows whose scope is a string or missing', function (array $row) {
    $scope = CapabilityScope::fromRow($row);

    expect($scope->tenantId)->toBe('t1')
        ->and($scope->teamId)->toBeNull()
        ->and($scope->organizationId)->toBeNull()
        ->and($scope->attributes)->toBe([]);
})->with([
    'legacy string scope' => [['tenant_id' => 't1', 'scope' => 't1']],
    'null scope' => [['tenant_id' => 't1', 'scope' => null]],
    'no scope key' => [['tenant_id' => 't1']],
    'array without dimensions' => [['tenant_id' => 't1', 'scope' => ['tenant_id' => 't1']]],
]);

it('fromRow yields null for an untenanted row so scope resolves at execution time', function (array $row) {
    expect(CapabilityScope::fromRow($row))->toBeNull();
})->with([
    'null tenant' => [['tenant_id' => null, 'scope' => ['tenant_id' => null, 'team_id' => 'team-9', 'organization_id' => null, 'attributes' => []]]],
    'empty tenant' => [['tenant_id' => '', 'scope' => '']],
    'no tenant key' => [[]],
]);

it('a rebuilt scope regains a query factory from the container binding like any resolver scope', function () {
    $scope = CapabilityScope::fromRow(['tenant_id' => 't1', 'scope' => (new CapabilityScope(tenantId: 't1', teamId: 'team-9'))->toRow()]);

    expect(fn () => $scope->query('Invoice'))->toThrow(RuntimeException::class);

    $scoped = $scope->withQueryFactory(new InMemoryScopedQueryFactory);

    expect($scoped->query('Invoice'))->not->toBeNull()
        ->and($scoped->teamId)->toBe('team-9');
});
