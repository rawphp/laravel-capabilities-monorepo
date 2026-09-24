<?php

declare(strict_types=1);

use Rawphp\Capabilities\Support\CapabilityContext;
use Rawphp\Capabilities\Tests\Fixtures\PipelineHelpers;

/*
 * Messaging-originated tool calls stay caller=agent (spec: Caller context), so the
 * agent surface gate alone cannot see them. The global messaging flag must still
 * block them — "messaging requires agent + messaging package" (SURF boot rule 6).
 */

it('fail: messaging-originated agent invoke is forbidden when messaging surface is disabled [PIPE-005]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('agent', [
        'messaging' => ['channel' => 'telegram', 'chat_id' => '77'],
    ]));
    expect($result->errorCode())->toBe('forbidden')
        ->and($result->error['message'])->toContain('surface "messaging"')
        ->and($h['runCount']->value)->toBe(0);
});

it('fail: messaging metadata on a supplied context is gated the same way [PIPE-005]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $options = PipelineHelpers::options('agent');
    $options['context'] = new CapabilityContext(
        caller: 'agent',
        actor: $options['actor'],
        messaging: ['channel' => 'telegram', 'chat_id' => '77'],
    );
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), $options);
    expect($result->errorCode())->toBe('forbidden')->and($h['runCount']->value)->toBe(0);
});

it('happy: messaging-originated agent invoke runs when messaging surface is enabled [PIPE-005]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'surfaces' => ['messaging' => true]]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('agent', [
        'messaging' => ['channel' => 'telegram', 'chat_id' => '77'],
    ]));
    expect($result->isOk())->toBeTrue()
        ->and($h['registry']->lastState()?->caller)->toBe('agent')
        ->and($h['runCount']->value)->toBe(1);
});

it('happy: plain agent invoke is unaffected by the messaging flag [PIPE-005]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('agent'));
    expect($result->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
});

it('fail: messaging-originated invoke still needs the capability agent surface [PIPE-005]', function () {
    $h = PipelineHelpers::harness([
        'allowSystemCallers' => true,
        'surfaces' => ['messaging' => true],
        'cap_surfaces' => ['http'],
        'name' => 'only-http',
    ]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('agent', [
        'messaging' => ['channel' => 'telegram', 'chat_id' => '77'],
    ]));
    expect($result->errorCode())->toBe('forbidden')->and($h['runCount']->value)->toBe(0);
});
