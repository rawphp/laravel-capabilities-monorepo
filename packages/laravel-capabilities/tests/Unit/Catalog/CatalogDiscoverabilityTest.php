<?php

// HTTP catalog applies canDiscover like agent/MCP tool lists (D-008 / D-009). Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Tests\Fixtures\CatalogHelpers;
use Rawphp\Capabilities\Tests\Fixtures\HttpHelpers;

it('fail: catalog list omits capabilities the actor may not discover [D-008]', function () {
    $h = CatalogHelpers::harness(['canDiscover' => false]);
    CatalogHelpers::registerNamed($h['registry'], ['name' => 'list-invoices']);

    $names = array_column($h['catalog']->list(), 'name');

    expect($names)->toBe(['list-invoices']);
});

it('happy: catalog list passes the actor to canDiscover [D-008]', function () {
    $staff = HttpHelpers::user(1);
    $guest = HttpHelpers::user(2);
    $guest->is_staff = false;
    $h = CatalogHelpers::harness(['canDiscover' => fn ($actor) => (bool) ($actor->is_staff ?? false)]);

    expect(array_column($h['catalog']->list(false, ['actor' => $staff]), 'name'))->toBe(['create-invoice'])
        ->and($h['catalog']->list(false, ['actor' => $guest]))->toBe([])
        ->and($h['catalog']->list(true, ['actor' => $guest]))->toBe([]);
});

it('fail: catalog describe of an undiscoverable capability reads as unknown [D-008]', function () {
    $h = CatalogHelpers::harness(['canDiscover' => false, 'aliases' => ['make-invoice']]);

    expect(fn () => $h['catalog']->describe('create-invoice'))
        ->toThrow(InvalidArgumentException::class, 'Unknown capability "create-invoice".')
        ->and(fn () => $h['catalog']->describe('make-invoice'))
        ->toThrow(InvalidArgumentException::class, 'Unknown capability "make-invoice".');
});

it('fail: HTTP list and describe hide what the request user may not discover [D-008]', function () {
    $h = HttpHelpers::harness(['canDiscover' => fn ($actor) => ($actor->id ?? null) === 1]);
    $other = HttpHelpers::user(2);

    $list = $h['controller']->list(HttpHelpers::authedRequest(['user' => $other]));
    $describe = $h['controller']->describe(HttpHelpers::authedRequest(['user' => $other]), 'create-invoice');

    expect($list->body['data']['capabilities'])->toBe([])
        ->and($describe->errorCode())->toBe('not_found');

    $ownList = $h['controller']->list(HttpHelpers::authedRequest(['user' => HttpHelpers::user(1)]));
    $ownDescribe = $h['controller']->describe(HttpHelpers::authedRequest(['user' => HttpHelpers::user(1)]), 'create-invoice');

    expect(array_column($ownList->body['data']['capabilities'], 'name'))->toBe(['create-invoice'])
        ->and($ownDescribe->body['data']['name'])->toBe('create-invoice');
});
