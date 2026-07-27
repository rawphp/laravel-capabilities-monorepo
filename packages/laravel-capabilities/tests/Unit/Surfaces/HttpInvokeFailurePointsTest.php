<?php

// REQ-011 fleshed unit tests for Surfaces/HttpInvokeFailurePointsTest.php. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Adapters\Http\CapabilityController;
use Rawphp\Capabilities\Support\ErrorCodeMap;
use Rawphp\Capabilities\Tests\Fixtures\FakeCapabilityBus;
use Rawphp\Capabilities\Tests\Fixtures\HttpHelpers;

$codes = [
    'unauthenticated',
    'forbidden',
    'validation_failed',
    'not_found',
    'conflict',
    'rate_limited',
    'approval_required',
    'domain_error',
    'output_invalid',
    'internal',
];

foreach ($codes as $code) {
    it("happy: http invoke maps failure {$code} to envelope [HTTP-001]", function () use ($code) {
        if ($code === 'unauthenticated') {
            // Auth gate short-circuits before registry.
            $bus = FakeCapabilityBus::failing('internal');
            $controller = new CapabilityController($bus);
            $res = $controller->invoke(HttpHelpers::guestRequest([
                'method' => 'POST',
                'jsonBody' => [],
            ]), 'create-invoice');
            expect($res->errorCode())->toBe('unauthenticated')
                ->and($res->status)->toBe(ErrorCodeMap::httpStatus('unauthenticated'))
                ->and($res->body['ok'])->toBeFalse()
                ->and($res->body['error'])->toHaveKeys(['code', 'message', 'retryable', 'http_status'])
                ->and($bus->invokeCalls)->toBe(0);

            return;
        }

        $bus = FakeCapabilityBus::failing($code);
        $controller = new CapabilityController($bus);
        $res = $controller->invoke(HttpHelpers::authedRequest([
            'method' => 'POST',
            'jsonBody' => ['customer_id' => 1],
        ]), 'create-invoice');

        expect($res->errorCode())->toBe($code)
            ->and($res->status)->toBe(ErrorCodeMap::httpStatus($code))
            ->and($res->body['ok'])->toBeFalse()
            ->and($res->body['error']['code'])->toBe($code)
            ->and($res->body['error'])->toHaveKeys(['code', 'message', 'retryable', 'http_status', 'cli_exit']);

        if ($code === 'approval_required') {
            expect($res->body['error']['approval_id'] ?? null)->not->toBeNull();
        }
    });

    it("fail: http invoke failure {$code} does not partial-commit domain unless domain already did [HTTP-001]", function () use ($code) {
        if ($code === 'unauthenticated') {
            $bus = FakeCapabilityBus::failing('domain_error');
            expect($bus->domainCommitted)->toBeFalse();
            $controller = new CapabilityController($bus);
            $controller->invoke(HttpHelpers::guestRequest([
                'method' => 'POST',
                'jsonBody' => [],
            ]), 'create-invoice');
            // Auth short-circuit: registry never called, domain never committed.
            expect($bus->invokeCalls)->toBe(0)
                ->and($bus->domainCommitted)->toBeFalse();

            return;
        }

        $bus = FakeCapabilityBus::failing($code);
        expect($bus->domainCommitted)->toBeFalse();
        $controller = new CapabilityController($bus);
        $controller->invoke(HttpHelpers::authedRequest([
            'method' => 'POST',
            'jsonBody' => ['customer_id' => 1],
        ]), 'create-invoice');

        if ($code === 'domain_error') {
            // Domain already ran — HTTP layer must not invent a second mutation path.
            expect($bus->invokeCalls)->toBe(1)
                ->and($bus->domainCommitted)->toBeTrue();
        } else {
            // Pre-run / non-domain failures: bus may be invoked but domain is not committed.
            expect($bus->invokeCalls)->toBe(1)
                ->and($bus->domainCommitted)->toBeFalse();
        }
    });
}
