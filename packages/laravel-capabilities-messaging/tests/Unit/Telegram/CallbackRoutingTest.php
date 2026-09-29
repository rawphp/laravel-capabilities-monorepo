<?php

// M-101 / D-006 step 4: a tapped approval button is an approval decision, not chat text. The
// default webhook → ProcessTelegramUpdate path routes callback_query updates to CallbackHandler
// (decode, accept/reject through ApprovalGateway, answerCallbackQuery) and never to the agent.

declare(strict_types=1);

use Rawphp\Capabilities\Approval\ApprovalManager;
use Rawphp\Capabilities\Approval\ApprovalStateMachine;
use Rawphp\CapabilitiesMessaging\Support\FakeTelegramBotClient;
use Rawphp\CapabilitiesMessaging\Telegram\CallbackHandler;
use Rawphp\CapabilitiesMessaging\Telegram\ProcessTelegramUpdate;
use Rawphp\CapabilitiesMessaging\Telegram\TelegramAdapter;
use Rawphp\CapabilitiesMessaging\Tests\Fixtures\FakeCapabilityBus;
use Rawphp\CapabilitiesMessaging\Tests\Fixtures\MessagingHelpers as H;

/**
 * @return array{processor: ProcessTelegramUpdate, bot: FakeTelegramBotClient, registry: FakeCapabilityBus, agentCalls: ArrayObject, approvals: ApprovalManager}
 */
function callbackRoutingHarness(bool $withHandler = true): array
{
    $bot = H::bot();
    $identity = H::identity();
    $identity->link('42', 'u1');
    $approvals = H::approvals();
    $approvals->request([
        'id' => 'cb-1',
        'capability_name' => 'billing.void',
        'requester_actor_type' => 'user',
        'requester_actor_id' => 'u1',
        'original_caller' => 'agent',
        'input_json' => [],
        'messaging' => ['channel' => 'telegram', 'chat_id' => '100'],
    ]);
    $agentCalls = new ArrayObject;
    $adapter = new TelegramAdapter($bot, static function (array $m) use ($agentCalls): array {
        $agentCalls[] = $m;

        return ['text' => 'agent answered'];
    });
    $registry = new FakeCapabilityBus;

    $processor = H::processor([
        'identity' => $identity,
        'adapter' => $adapter,
        'bot' => $bot,
        'registry' => $registry,
        'callbacks' => $withHandler ? new CallbackHandler(H::signer(), $identity, $approvals) : null,
    ]);

    return ['processor' => $processor, 'bot' => $bot, 'registry' => $registry, 'agentCalls' => $agentCalls, 'approvals' => $approvals];
}

it('routes an accept tap to the approval gateway and answers the callback, never the agent', function () {
    $h = callbackRoutingHarness();

    $r = $h['processor']->handle(H::callbackUpdate('cb-1', 'accept'));

    $row = $h['approvals']->find('cb-1');
    $answers = array_values(array_filter($h['bot']->calls(), fn (array $c) => $c['method'] === 'answerCallbackQuery'));

    expect($r['ok'])->toBeTrue()
        ->and($r['callback'])->toBe('ok')
        ->and($r['steps'])->toContain('approval_callback')
        // Accept on a manager with no executor fails closed inside core; the decision itself was routed.
        ->and($row['status'])->toBe(ApprovalStateMachine::STATUS_EXECUTED)
        ->and(count($h['agentCalls']))->toBe(0)
        ->and($h['registry']->invokeCount())->toBe(0)
        ->and($answers)->toHaveCount(1)
        ->and($answers[0]['args']['callback_query_id'])->toBe('cbq-2')
        ->and(array_column($h['bot']->calls(), 'method'))->not->toContain('sendMessage');
});

it('routes a reject tap and reports the decision in the toast', function () {
    $h = callbackRoutingHarness();

    $r = $h['processor']->handle(H::callbackUpdate('cb-1', 'reject'));

    expect($r['ok'])->toBeTrue()
        ->and($r['callback'])->toBe('ok')
        ->and($h['approvals']->find('cb-1')['status'])->toBe(ApprovalStateMachine::STATUS_REJECTED)
        ->and(count($h['agentCalls']))->toBe(0)
        ->and($h['bot']->calls()[0]['args']['text'])->toContain('Rejected');
});

it('answers a tampered or malformed token as invalid without touching approvals or the agent', function () {
    $h = callbackRoutingHarness();

    $garbage = $h['processor']->handle(H::callbackUpdate('cb-1', data: 'approve id=cb-1'));
    $forged = $h['processor']->handle(H::callbackUpdate('cb-1', updateId: 3, signer: H::signer(H::config(['telegram' => ['callback_secret' => 'someone-else']]))));

    expect($garbage['ok'])->toBeFalse()
        ->and($garbage['callback'])->toBe('invalid')
        ->and($forged['ok'])->toBeFalse()
        ->and($forged['callback'])->toBe('invalid')
        ->and($h['approvals']->find('cb-1')['status'])->toBe(ApprovalStateMachine::STATUS_PENDING)
        ->and(count($h['agentCalls']))->toBe(0)
        ->and(array_column($h['bot']->calls(), 'method'))->toBe(['answerCallbackQuery', 'answerCallbackQuery']);
});

it('an unlinked tapper is told so and the approval stays pending', function () {
    $h = callbackRoutingHarness();

    $r = $h['processor']->handle(H::callbackUpdate('cb-1', userId: 999));

    expect($r['ok'])->toBeFalse()
        ->and($r['callback'])->toBe('forbidden')
        ->and($h['approvals']->find('cb-1')['status'])->toBe(ApprovalStateMachine::STATUS_PENDING)
        ->and(count($h['agentCalls']))->toBe(0);
});

it('fails closed when no CallbackHandler is wired: the tap is answered, the agent never sees the token', function () {
    $h = callbackRoutingHarness(withHandler: false);

    $r = $h['processor']->handle(H::callbackUpdate('cb-1'));

    expect($r['ok'])->toBeFalse()
        ->and($r['error'])->toBe('callback_handler_unavailable')
        ->and(count($h['agentCalls']))->toBe(0)
        ->and($h['registry']->invokeCount())->toBe(0)
        ->and(array_column($h['bot']->calls(), 'method'))->toBe(['answerCallbackQuery']);
});

it('a plain chat message still reaches the agent (callback routing is only for callback_query)', function () {
    $h = callbackRoutingHarness();

    $r = $h['processor']->handle(H::telegramUpdate(userId: 42, chatId: 100));

    expect($r['ok'])->toBeTrue()
        ->and(count($h['agentCalls']))->toBe(1);
});
