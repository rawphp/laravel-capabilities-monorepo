<?php

declare(strict_types=1);

use Rawphp\Capabilities\Adapters\Http\CapabilityController;
use Rawphp\Capabilities\Capability;
use Rawphp\Capabilities\Support\CapabilityContext;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceInput;
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

it('happy: an approved request runs on the surface it was requested through [D-022 / D-006]', function () {
    $h = PipelineHelpers::harness();
    $runs = 0;
    Capability::define('http-approved')
        ->description('HTTP-only capability that needs approval')
        ->surfaces(['http'])
        ->input(CreateInvoiceInput::class)
        ->output(CreateInvoiceResult::class)
        ->allowSystemCallers(true)
        ->needsApproval(fn () => true)
        ->run(function () use (&$runs) {
            $runs++;

            return new CreateInvoiceResult(invoice_id: 1);
        })
        ->register($h['registry']);

    $requested = $h['registry']->invoke('http-approved', PipelineHelpers::validInput(), PipelineHelpers::options('job', [
        'derived_caller' => 'http',
    ]));
    $row = (array) $h['registry']->approvalStore()?->find((string) $requested->approvalId());

    expect($requested->isApprovalRequired())->toBeTrue()
        ->and($row['original_caller'] ?? null)->toBe('job')
        ->and($row['original_surface'] ?? null)->toBe('http')
        ->and($h['registry']->executeApproval($row)->isOk())->toBeTrue()
        ->and($runs)->toBe(1);
});

it('fail: an approval row without a stored surface gates on the original caller [D-022]', function () {
    $h = PipelineHelpers::harness(['cap_surfaces' => ['http']]);

    $result = $h['registry']->executeApproval([
        'id' => 'ap-legacy',
        'capability_name' => $h['name'],
        'input_json' => PipelineHelpers::validInput(),
        'original_caller' => 'job',
        'requester_actor_type' => 'system',
        'requester_actor_id' => 'billing-worker',
        'tenant_id' => 't-1',
    ]);

    expect($result->errorCode())->toBe('forbidden')
        ->and($h['runCount']->value)->toBe(0);
});

it('happy: catalog list and describe filter on the credential surface, not the downgraded caller [D-022]', function () {
    $h = HttpHelpers::harness(['cap_surfaces' => ['http']]);
    Capability::define('job-only')
        ->description('Job-only capability')
        ->surfaces(['job'])
        ->input(CreateInvoiceInput::class)
        ->output(CreateInvoiceResult::class)
        ->run(fn () => new CreateInvoiceResult(invoice_id: 1))
        ->register($h['registry']);
    $downgraded = fn (string $method) => HttpHelpers::authedRequest([
        'method' => $method,
        'credential' => ['adapter' => 'http'],
        'headers' => ['x-capabilities-caller' => 'job'],
    ]);

    $listed = array_column($h['controller']->list($downgraded('GET'))->body['data']['capabilities'] ?? [], 'name');

    expect($listed)->toBe([$h['name']])
        ->and($h['controller']->describe($downgraded('GET'), $h['name'])->isOk())->toBeTrue()
        ->and($h['controller']->describe($downgraded('GET'), 'job-only')->errorCode())->toBe('not_found');
});
