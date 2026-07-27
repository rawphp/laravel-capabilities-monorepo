<?php

declare(strict_types=1);

use Rawphp\Capabilities\Capability;
use Rawphp\Capabilities\Schema\OutputValidator;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceInput;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceResult;
use Rawphp\Capabilities\Tests\Fixtures\DiscoveryHelpers;

it('happy: output_invalid is 500-class envelope [D-014]', function () {
    $registry = DiscoveryHelpers::registry();
    Capability::define('http-500')
        ->input(CreateInvoiceInput::class)
        ->output(CreateInvoiceResult::class)
        ->run(fn ($in) => ['invoice_id' => 'bad'])
        ->register($registry);

    $result = $registry->invoke('http-500', [
        'customer_id' => 1,
        'amount_cents' => 1,
        'currency' => 'USD',
    ], ['caller' => 'http']);

    $http = (new OutputValidator)->toHttpEnvelope($result);
    expect($http['status'])->toBeGreaterThanOrEqual(500)
        ->and($http['body']['error']['code'])->toBe('output_invalid');
});

it('fail: output_invalid is not 200 success [D-014]', function () {
    $registry = DiscoveryHelpers::registry();
    Capability::define('http-not-200')
        ->input(CreateInvoiceInput::class)
        ->output(CreateInvoiceResult::class)
        ->run(fn ($in) => [])
        ->register($registry);

    $result = $registry->invoke('http-not-200', [
        'customer_id' => 1,
        'amount_cents' => 1,
        'currency' => 'USD',
    ], ['caller' => 'http']);

    $http = (new OutputValidator)->toHttpEnvelope($result);
    expect($http['status'])->not->toBe(200)
        ->and($http['body']['ok'])->toBeFalse();
});

it('fail: output_invalid is not silent coercion to partial data [D-014]', function () {
    $registry = DiscoveryHelpers::registry();
    Capability::define('http-no-coerce')
        ->input(CreateInvoiceInput::class)
        ->output(CreateInvoiceResult::class)
        ->run(fn ($in) => ['invoice_id' => '1', 'extra' => true])
        ->register($registry);

    $result = $registry->invoke('http-no-coerce', [
        'customer_id' => 1,
        'amount_cents' => 1,
        'currency' => 'USD',
    ], ['caller' => 'http']);

    expect($result->isOk())->toBeFalse()
        ->and($result->data)->toBeNull()
        ->and($result->errorCode())->toBe('output_invalid');
});
