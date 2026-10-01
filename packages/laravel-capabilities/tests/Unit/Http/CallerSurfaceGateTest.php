<?php

declare(strict_types=1);

use Rawphp\Capabilities\Adapters\Http\CapabilityController;
use Rawphp\Capabilities\Support\CapabilityContext;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceResult;
use Rawphp\Capabilities\Tests\Fixtures\FakeCapabilityBus;
use Rawphp\Capabilities\Tests\Fixtures\HttpHelpers;
use Rawphp\Capabilities\Tests\Fixtures\PipelineHelpers;

it('fail: an HTTP caller downgrade does not open a job-only surface [D-022]', function () {
    $runs = 0;
    $h = HttpHelpers::harness([
        'cap_surfaces' => ['job'],
        'run' => function () use (&$runs) {
            $runs++;

            return new CreateInvoiceResult(invoice_id: 1);
        },
    ]);

    $res = $h['controller']->invoke(HttpHelpers::authedRequest([
        'method' => 'POST',
        'jsonBody' => PipelineHelpers::validInput(),
        'credential' => ['adapter' => 'http'],
        'headers' => ['x-capabilities-caller' => 'job'],
    ]), $h['name']);

    expect($res->errorCode())->toBe('forbidden')
        ->and($res->body['error']['message'] ?? '')->toContain('surface "http"')
        ->and($runs)->toBe(0);
});

it('happy: an HTTP caller downgrade still tightens the policy caller [D-022]', function () {
    $seen = null;
    $h = HttpHelpers::harness([
        'cap_surfaces' => ['http'],
        'run' => function (mixed $in, CapabilityContext $ctx) use (&$seen) {
            $seen = $ctx->caller();

            return new CreateInvoiceResult(invoice_id: 1);
        },
    ]);

    $res = $h['controller']->invoke(HttpHelpers::authedRequest([
        'method' => 'POST',
        'jsonBody' => PipelineHelpers::validInput(),
        'credential' => ['adapter' => 'http'],
        'headers' => ['x-capabilities-caller' => 'job'],
    ]), $h['name']);

    expect($res->isOk())->toBeTrue()
        ->and($seen)->toBe('job')
        ->and($res->body['meta']['caller'] ?? null)->toBe('job')
        ->and($res->body['meta']['derived_caller'] ?? null)->toBe('http');
});

it('happy: in-process invoke keeps surface equal to caller when derived_caller is absent [D-022]', function () {
    $h = PipelineHelpers::harness([
        'cap_surfaces' => ['job'],
        'allowSystemCallers' => true,
    ]);

    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('job'));

    expect($result->isOk())->toBeTrue()
        ->and($h['runCount']->value)->toBe(1);
});

it('happy: body idempotency_key is a wire key and leaves capability input [D-005]', function () {
    $bus = new FakeCapabilityBus;
    $controller = new CapabilityController($bus);
    $controller->invoke(HttpHelpers::authedRequest([
        'method' => 'POST',
        'jsonBody' => ['customer_id' => 1, 'idempotency_key' => 'from-body'],
    ]), 'create-invoice');

    expect($bus->invocations[0]['options']['idempotency_key'] ?? null)->toBe('from-body')
        ->and($bus->invocations[0]['input'])->not->toHaveKey('idempotency_key')
        ->and($bus->invocations[0]['input']['customer_id'] ?? null)->toBe(1);
});

it('happy: Idempotency-Key header wins over the body key [D-005]', function () {
    $bus = new FakeCapabilityBus;
    $controller = new CapabilityController($bus);
    $controller->invoke(HttpHelpers::authedRequest([
        'method' => 'POST',
        'jsonBody' => ['customer_id' => 1, 'idempotency_key' => 'from-body'],
        'headers' => ['idempotency-key' => 'from-header'],
    ]), 'create-invoice');

    expect($bus->invocations[0]['options']['idempotency_key'] ?? null)->toBe('from-header')
        ->and($bus->invocations[0]['input'])->not->toHaveKey('idempotency_key');
});
