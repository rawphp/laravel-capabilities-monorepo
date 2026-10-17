<?php

// The `agent` invoke option reaches CapabilityContext::agent() inside run() through the real pipeline.

declare(strict_types=1);

use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Rawphp\Capabilities\Support\CapabilityContext;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceResult;
use Rawphp\Capabilities\Tests\Fixtures\PipelineHelpers;

/**
 * @return array{registry: CapabilityRegistry, name: string, seen: stdClass}
 */
function agentContextHarness(): array
{
    $seen = new stdClass;
    $seen->context = null;
    $h = PipelineHelpers::harness(['run' => function ($input, CapabilityContext $context) use ($seen) {
        $seen->context = $context;

        return new CreateInvoiceResult(invoice_id: 1);
    }]);

    return ['registry' => $h['registry'], 'name' => $h['name'], 'seen' => $seen];
}

it('happy: agent option reaches run() context after scope resolution [CTX-001]', function () {
    $h = agentContextHarness();
    $agent = ['conversation_ulid' => 'c1', 'turn_ulid' => 't1'];

    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('agent', [
        'agent' => $agent,
    ]));

    expect($result->isOk())->toBeTrue()
        ->and($h['seen']->context->agent())->toBe($agent)
        // Scope was resolved and applied to the context run() got.
        ->and($h['seen']->context->tenantId())->toBe('t-1');
});

it('edge: invoke without agent option gives run() a null agent context [CTX-001]', function () {
    $h = agentContextHarness();

    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http'));

    expect($result->isOk())->toBeTrue()
        ->and($h['seen']->context->agent())->toBeNull();
});
