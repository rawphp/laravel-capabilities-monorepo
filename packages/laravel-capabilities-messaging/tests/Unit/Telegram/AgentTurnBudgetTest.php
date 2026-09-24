<?php

// D-013: Telegram agent turns carry the per-turn tool-call count so the core
// pipeline's AgentTurnBudget can stop the loop, same as the AI adapter path.

declare(strict_types=1);

use Rawphp\Capabilities\RateLimiting\AgentTurnBudget;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\CapabilitiesMessaging\Support\FakeTelegramBotClient;
use Rawphp\CapabilitiesMessaging\Telegram\ProcessTelegramUpdate;
use Rawphp\CapabilitiesMessaging\Telegram\TelegramAdapter;
use Rawphp\CapabilitiesMessaging\Tests\Fixtures\FakeCapabilityBus;
use Rawphp\CapabilitiesMessaging\Tests\Fixtures\MessagingHelpers as H;

/**
 * @return array{processor: ProcessTelegramUpdate, registry: FakeCapabilityBus, bot: FakeTelegramBotClient}
 */
function budgetTurn(int $toolCalls, ?FakeCapabilityBus $registry = null): array
{
    $identity = H::identity();
    $identity->link('42', 'u1');
    $registry ??= new FakeCapabilityBus;
    $bot = H::bot();
    $calls = array_fill(0, $toolCalls, ['name' => 'support.ping', 'input' => []]);
    $adapter = new TelegramAdapter($bot, static fn (array $m) => ['text' => 'done', 'tool_calls' => $calls]);

    return [
        'processor' => H::processor([
            'identity' => $identity,
            'registry' => $registry,
            'adapter' => $adapter,
            'bot' => $bot,
            'profile_tools' => ['support.ping'],
        ]),
        'registry' => $registry,
        'bot' => $bot,
    ];
}

it('happy: each Telegram tool call carries its 1-based agent_turn_tool_calls count [D-013]', function () {
    $t = budgetTurn(3);

    $r = $t['processor']->handle(H::telegramUpdate(userId: 42));

    $counts = array_map(
        static fn (array $i) => $i['options']['agent_turn_tool_calls'] ?? null,
        $t['registry']->invocations(),
    );
    expect($r['ok'])->toBeTrue()
        ->and($counts)->toBe([1, 2, 3]);
});

it('fail: Telegram turn stops invoking tools once the core turn budget is exhausted [D-013]', function () {
    // Bus enforces the same rule InvokePipeline applies to caller=agent invokes.
    $budget = new AgentTurnBudget(2);
    $registry = new FakeCapabilityBus;
    $registry->when('support.ping', static function (string $name, array $input, array $options) use ($budget): CapabilityResult {
        $calls = (int) ($options['agent_turn_tool_calls'] ?? 0);
        if ($options['caller'] === 'agent' && $budget->exhausted($calls)) {
            return CapabilityResult::failure(code: 'rate_limited', message: $budget->stopMessage($calls)['message']);
        }

        return CapabilityResult::ok(['name' => $name]);
    });
    $t = budgetTurn(5, $registry);

    $r = $t['processor']->handle(H::telegramUpdate(userId: 42));

    expect($r['ok'])->toBeFalse()
        ->and($r['error'])->toBe('rate_limited')
        ->and($registry->invokeCount())->toBe(3)
        ->and($r['steps'])->not->toContain('conversation_reply');
});

it('edge: the tool-call count resets for each Telegram update [D-013]', function () {
    $t = budgetTurn(2);

    $t['processor']->handle(H::telegramUpdate(userId: 42, updateId: 1));
    $t['processor']->handle(H::telegramUpdate(userId: 42, updateId: 2));

    $counts = array_map(
        static fn (array $i) => $i['options']['agent_turn_tool_calls'] ?? null,
        $t['registry']->invocations(),
    );
    expect($counts)->toBe([1, 2, 1, 2]);
});
