<?php

declare(strict_types=1);

use Rawphp\Capabilities\Capability;
use Rawphp\Capabilities\Events\CapabilityFailed;
use Rawphp\Capabilities\Schema\OutputValidator;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceInput;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceResult;
use Rawphp\Capabilities\Tests\Fixtures\DiscoveryHelpers;

function validInvoiceInput(): array
{
    return [
        'customer_id' => 1,
        'amount_cents' => 100,
        'currency' => 'USD',
    ];
}

it('happy: validate_output true validates declared output after successful run [D-014]', function () {
    $registry = DiscoveryHelpers::registry([], ['validate_output' => true]);
    Capability::define('out-valid')
        ->input(CreateInvoiceInput::class)
        ->output(CreateInvoiceResult::class)
        ->run(fn ($in) => new CreateInvoiceResult(invoice_id: 5))
        ->register($registry);

    $result = $registry->invoke('out-valid', validInvoiceInput());
    expect($result->isOk())->toBeTrue()
        ->and($result->data->invoice_id)->toBe(5);
});

it('fail: invalid output after run emits CapabilityFailed [D-014]', function () {
    $registry = DiscoveryHelpers::registry();
    Capability::define('out-fail-event')
        ->input(CreateInvoiceInput::class)
        ->output(CreateInvoiceResult::class)
        ->run(fn ($in) => ['wrong' => true])
        ->register($registry);

    $result = $registry->invoke('out-fail-event', validInvoiceInput());
    expect($result->isOk())->toBeFalse()
        ->and($registry->failedEvents())->not->toBeEmpty()
        ->and($registry->failedEvents()[0])->toBeInstanceOf(CapabilityFailed::class);
});

it('fail: invalid output maps to output_invalid envelope [D-014]', function () {
    $registry = DiscoveryHelpers::registry();
    Capability::define('out-fail-code')
        ->input(CreateInvoiceInput::class)
        ->output(CreateInvoiceResult::class)
        ->run(fn ($in) => [])
        ->register($registry);

    $result = $registry->invoke('out-fail-code', validInvoiceInput());
    expect($result->errorCode())->toBe('output_invalid')
        ->and($result->toArray()['ok'])->toBeFalse();
});

it('fail: invalid output is not returned as success to agent tools [D-014]', function () {
    $registry = DiscoveryHelpers::registry();
    Capability::define('out-agent')
        ->input(CreateInvoiceInput::class)
        ->output(CreateInvoiceResult::class)
        ->run(fn ($in) => ['invoice_id' => 'nope'])
        ->register($registry);

    $result = $registry->invoke('out-agent', validInvoiceInput(), ['caller' => 'agent']);
    $tool = (new OutputValidator)->toToolResult($result);
    expect($tool['ok'])->toBeFalse()->and($tool['is_error'])->toBeTrue();
});

it('fail: invalid output is not returned as success to MCP tools [D-014]', function () {
    $registry = DiscoveryHelpers::registry();
    Capability::define('out-mcp')
        ->input(CreateInvoiceInput::class)
        ->output(CreateInvoiceResult::class)
        ->run(fn ($in) => ['invoice_id' => null])
        ->register($registry);

    $result = $registry->invoke('out-mcp', validInvoiceInput(), ['caller' => 'mcp']);
    $tool = (new OutputValidator)->toToolResult($result);
    expect($tool['ok'])->toBeFalse()->and($result->errorCode())->toBe('output_invalid');
});

it('fail: invalid output is not returned as success to HTTP [D-014]', function () {
    $registry = DiscoveryHelpers::registry();
    Capability::define('out-http')
        ->input(CreateInvoiceInput::class)
        ->output(CreateInvoiceResult::class)
        ->run(fn ($in) => new CreateInvoiceResult(invoice_id: 1)) // valid type but we'll force bad via array
        ->register($registry);

    // Override with bad output path
    $registry2 = DiscoveryHelpers::registry();
    Capability::define('out-http-bad')
        ->input(CreateInvoiceInput::class)
        ->output(CreateInvoiceResult::class)
        ->run(fn ($in) => ['not' => 'valid'])
        ->register($registry2);

    $result = $registry2->invoke('out-http-bad', validInvoiceInput(), ['caller' => 'http']);
    $http = (new OutputValidator)->toHttpEnvelope($result);
    expect($http['status'])->toBe(500)
        ->and($http['body']['ok'])->toBeFalse();
});

it('edge: readOnly without output schema may skip when configured [D-014]', function () {
    $registry = DiscoveryHelpers::registry();
    Capability::define('ro-skip-out')
        ->readOnly(true)
        ->surfaces(['http'])
        ->run(fn () => ['anything' => true])
        ->register($registry);

    $def = $registry->get('ro-skip-out');
    expect($def->shouldValidateOutput(true))->toBeFalse();

    $result = $registry->invoke('ro-skip-out', []);
    expect($result->isOk())->toBeTrue();
});

it('edge: validate_output false only when explicitly configured [D-014]', function () {
    $registry = DiscoveryHelpers::registry([], ['validate_output' => false]);
    Capability::define('out-off')
        ->input(CreateInvoiceInput::class)
        ->output(CreateInvoiceResult::class)
        ->run(fn ($in) => ['garbage' => true])
        ->register($registry);

    expect($registry->validateOutputEnabled())->toBeFalse();
    $result = $registry->invoke('out-off', validInvoiceInput());
    // When validate_output is false, invalid shape is not rejected by output stage.
    expect($result->isOk())->toBeTrue();
});

it('happy: valid output passes through unchanged [D-014]', function () {
    $registry = DiscoveryHelpers::registry();
    $out = new CreateInvoiceResult(invoice_id: 99);
    Capability::define('out-pass')
        ->input(CreateInvoiceInput::class)
        ->output(CreateInvoiceResult::class)
        ->run(fn ($in) => $out)
        ->register($registry);

    $result = $registry->invoke('out-pass', validInvoiceInput());
    expect($result->data)->toBe($out);
});

it('fail: missing required output field fails validation [D-014]', function () {
    $registry = DiscoveryHelpers::registry();
    Capability::define('out-missing')
        ->input(CreateInvoiceInput::class)
        ->output(CreateInvoiceResult::class)
        ->run(fn ($in) => [])
        ->register($registry);

    $result = $registry->invoke('out-missing', validInvoiceInput());
    expect($result->errorCode())->toBe('output_invalid');
});

it('fail: wrong type in output field fails validation [D-014]', function () {
    $registry = DiscoveryHelpers::registry();
    Capability::define('out-wrong-type')
        ->input(CreateInvoiceInput::class)
        ->output(CreateInvoiceResult::class)
        ->run(fn ($in) => ['invoice_id' => 'string-not-int'])
        ->register($registry);

    $result = $registry->invoke('out-wrong-type', validInvoiceInput());
    expect($result->errorCode())->toBe('output_invalid');
});
