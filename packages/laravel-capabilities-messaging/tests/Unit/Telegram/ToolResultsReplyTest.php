<?php

declare(strict_types=1);

use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\CapabilitiesMessaging\Support\FakeTelegramBotClient;
use Rawphp\CapabilitiesMessaging\Telegram\ProcessTelegramUpdate;
use Rawphp\CapabilitiesMessaging\Telegram\TelegramAdapter;
use Rawphp\CapabilitiesMessaging\Tests\Fixtures\FakeCapabilityBus;
use Rawphp\CapabilitiesMessaging\Tests\Fixtures\MessagingHelpers as H;

/**
 * The chat user learns what happened to their request: tool results (output, approval_required,
 * refusals) go back to the agent in a follow-up ingress message carrying `tool_results`, and the
 * agent's answer to them is the reply. Messaging still owns every invoke.
 */
final class ToolResultsScenario
{
    /** @var list<array<string, mixed>> */
    public array $agentMessages = [];

    public FakeCapabilityBus $bus;

    public FakeTelegramBotClient $bot;

    public ProcessTelegramUpdate $processor;

    /**
     * @param  list<array{name: string, input?: array<string, mixed>}>  $toolCalls
     */
    public function __construct(array $toolCalls, ?callable $summarize = null)
    {
        $this->bus = new FakeCapabilityBus;
        $this->bot = H::bot();
        $summarize ??= static fn (array $results): string => implode(', ', array_map(
            static fn (array $r): string => $r['name'].'='.($r['result']->isOk() ? json_encode($r['result']->data) : $r['result']->errorCode()),
            $results,
        ));
        $agent = function (array $message) use ($toolCalls, $summarize): array {
            $this->agentMessages[] = $message;

            return isset($message['tool_results'])
                ? ['text' => $summarize($message['tool_results']), 'tool_calls' => [['name' => 'support.close']]]
                : ['text' => 'working on it', 'tool_calls' => $toolCalls];
        };
        $identity = H::identity();
        $identity->link('42', 'u1');
        $this->processor = H::processor([
            'identity' => $identity,
            'registry' => $this->bus,
            'adapter' => new TelegramAdapter($this->bot, $agent),
            'profile_tools' => ['support.lookup', 'support.close'],
        ]);
    }

    /** @return list<string> */
    public function sentTexts(): array
    {
        return array_map(static fn (array $c): string => $c['args']['text'], $this->bot->calls());
    }
}

it('happy: a read tool output reaches the agent and its answer is the reply [MSG-003]', function () {
    $s = new ToolResultsScenario([['name' => 'support.lookup', 'input' => ['id' => 7]]]);
    $s->bus->when('support.lookup', CapabilityResult::ok(['status' => 'open']));

    $r = $s->processor->handle(H::telegramUpdate(userId: 42));

    expect($r['ok'])->toBeTrue()
        ->and($s->sentTexts())->toBe(['support.lookup={"status":"open"}'])
        ->and($s->agentMessages)->toHaveCount(2)
        ->and($s->agentMessages[1]['tool_results'][0]['input'])->toBe(['id' => 7])
        ->and($s->agentMessages[1]['text'])->toBe('hello');
});

it('happy: approval_required reaches the user with the approval id [D-006]', function () {
    $s = new ToolResultsScenario([['name' => 'support.close', 'input' => ['id' => 7]]], static fn (array $results): string => 'Waiting for approval '.$results[0]['result']->approvalId());
    $s->bus->when('support.close', CapabilityResult::approvalRequired('appr-9'));

    $r = $s->processor->handle(H::telegramUpdate(userId: 42));

    expect($r['ok'])->toBeFalse()
        ->and($r['error'])->toBe('approval_required')
        ->and($s->sentTexts())->toBe(['Waiting for approval appr-9']);
});

it('fail: a refused tool call stops later calls and the refusal reaches the agent [D-007]', function () {
    $s = new ToolResultsScenario([
        ['name' => 'support.close', 'input' => ['id' => 7]],
        ['name' => 'support.lookup', 'input' => ['id' => 7]],
    ]);
    $s->bus->when('support.close', CapabilityResult::failure('forbidden', 'nope'));

    $r = $s->processor->handle(H::telegramUpdate(userId: 42));

    expect($r['error'])->toBe('forbidden')
        ->and($s->bus->invokeCount())->toBe(1)
        ->and($s->sentTexts())->toBe(['support.close=forbidden']);
});

it('fail: a retryable tool result is reported to the agent, not retried with a new turn [D-005]', function () {
    $s = new ToolResultsScenario([['name' => 'support.lookup', 'input' => []]]);
    $s->bus->when('support.lookup', CapabilityResult::failure('rate_limited', 'slow down'));

    $r = $s->processor->handle(H::telegramUpdate(userId: 42));

    expect($r['error'])->toBe('rate_limited')
        ->and($s->sentTexts())->toBe(['support.lookup=rate_limited']);
});

it('edge: tool calls in the follow-up answer are not invoked (one tool round per update) [D-013]', function () {
    $s = new ToolResultsScenario([['name' => 'support.lookup', 'input' => []]]);

    $s->processor->handle(H::telegramUpdate(userId: 42));

    expect(array_column($s->bus->invocations(), 'name'))->toBe(['support.lookup']);
});

it('edge: an answer without tool calls is sent as is, with one agent call [MSG-003]', function () {
    $s = new ToolResultsScenario([]);

    $r = $s->processor->handle(H::telegramUpdate(userId: 42));

    expect($r['ok'])->toBeTrue()
        ->and($s->agentMessages)->toHaveCount(1)
        ->and($s->sentTexts())->toBe(['working on it']);
});

it('happy: an unlinked user in a private chat is told how to link [MSG-002]', function () {
    $bot = H::bot();
    $p = H::processor(['adapter' => new TelegramAdapter($bot, H::echoAgent())]);

    $r = $p->handle(H::telegramUpdate(userId: 999, text: '/start'));

    expect($r['error'])->toBe('identity_unresolved')
        ->and(array_column(array_column($bot->calls(), 'args'), 'text'))->toBe([ProcessTelegramUpdate::UNLINKED_REPLY]);
});

it('edge: unlinked users get no reply in groups or in allowlist mode [MSG-002]', function () {
    $bot = H::bot();
    $group = H::telegramUpdate(userId: 999);
    $group['message']['chat']['type'] = 'supergroup';

    H::processor(['adapter' => new TelegramAdapter($bot, H::echoAgent())])->handle($group);
    H::processor([
        'config' => H::config(['identity' => ['mode' => 'allowlist']]),
        'adapter' => new TelegramAdapter($bot, H::echoAgent()),
    ])->handle(H::telegramUpdate(userId: 999));

    expect($bot->calls())->toBe([]);
});
