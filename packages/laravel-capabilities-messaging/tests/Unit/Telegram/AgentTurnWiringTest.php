<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Psr\Log\LoggerInterface;
use Rawphp\Capabilities\Contracts\CapabilityBus;
use Rawphp\CapabilitiesMessaging\Contracts\AgentTurn;
use Rawphp\CapabilitiesMessaging\Identity\IdentityLinker;
use Rawphp\CapabilitiesMessaging\Support\TelegramBotClient;
use Rawphp\CapabilitiesMessaging\Telegram\ProcessTelegramUpdate;
use Rawphp\CapabilitiesMessaging\Telegram\TelegramAdapter;
use Rawphp\CapabilitiesMessaging\Tests\Fixtures\FakeCapabilityBus;
use Rawphp\CapabilitiesMessaging\Tests\Fixtures\InMemoryUserModel;
use Rawphp\CapabilitiesMessaging\Tests\Fixtures\MessagingHelpers as H;
use Rawphp\CapabilitiesMessaging\Tests\Fixtures\RecordingLogger;

/**
 * Spec §6: ConversationIngress -> agent (tools = profile) -> registry. The agent turn is a host
 * binding (AgentTurn); unbound, chat fails closed instead of echoing the user's text (D-007/D-008).
 */
final class ScriptedAgentTurn implements AgentTurn
{
    /** @var list<array<string, mixed>> */
    public array $messages = [];

    /** @param  list<array{name: string, input?: array<string, mixed>}>  $toolCalls */
    public function __construct(private array $toolCalls = []) {}

    public function toolNames(string $profile): array
    {
        return $profile === 'support' ? ['support.ping'] : [];
    }

    public function respond(array $message): array
    {
        $this->messages[] = $message;

        return ['text' => 'agent says hi', 'tool_calls' => $this->toolCalls];
    }
}

function wiredApp(): Container
{
    InMemoryUserModel::seed(['u1']);
    $app = H::container([
        'telegram' => ['bot_token' => 't', 'webhook_secret' => 's'],
        'agent_profile' => 'support',
        'user_model' => InMemoryUserModel::class,
    ]);
    $app->make(IdentityLinker::class)->link('42', 'u1');

    return $app;
}

it('fail: TelegramAdapter without an agent turn refuses instead of echoing [D-007]', function () {
    expect(fn () => (new TelegramAdapter(H::bot()))->handle(['text' => 'echo me']))
        ->toThrow(RuntimeException::class, 'agent_turn_unbound');
});

it('fail: provider-built pipeline with no AgentTurn bound sends no reply and logs an error [D-007]', function () {
    $app = wiredApp();
    $logger = new RecordingLogger;
    $app->instance(LoggerInterface::class, $logger);

    $r = $app->make(ProcessTelegramUpdate::class)->handle(H::telegramUpdate(userId: 42, text: 'echo me'));

    expect($r['ok'])->toBeFalse()
        ->and($r['error'])->toContain('agent_turn_unbound')
        ->and($app->make(TelegramBotClient::class)->calls())->toBe([])
        ->and($logger->records[0]['level'])->toBe('error');
});

it('happy: bound AgentTurn answers with the profile tools, and its reply is sent [D-008]', function () {
    $app = wiredApp();
    $turn = new ScriptedAgentTurn;
    $app->instance(AgentTurn::class, $turn);

    $r = $app->make(ProcessTelegramUpdate::class)->handle(H::telegramUpdate(userId: 42, text: 'hello'));

    expect($r['ok'])->toBeTrue()
        ->and($r['tools'])->toBe(['support.ping'])
        ->and($turn->messages[0]['text'])->toBe('hello')
        ->and($turn->messages[0]['tools'])->toBe(['support.ping'])
        ->and($turn->messages[0]['profile'])->toBe('support')
        ->and($app->make(TelegramBotClient::class)->calls()[0]['args']['text'])->toBe('agent says hi');
});

it('happy: tool calls from the bound AgentTurn go through the capability bus as caller agent [D-007]', function () {
    $app = wiredApp();
    $bus = new FakeCapabilityBus;
    $app->instance(CapabilityBus::class, $bus);
    $app->instance(AgentTurn::class, new ScriptedAgentTurn([['name' => 'support.ping', 'input' => ['n' => 1]]]));

    $r = $app->make(ProcessTelegramUpdate::class)->handle(H::telegramUpdate(userId: 42));

    expect($r['ok'])->toBeTrue()
        ->and($bus->invocations()[0]['name'])->toBe('support.ping')
        ->and($bus->invocations()[0]['options']['caller'])->toBe('agent');
});

it('fail: a tool call outside the AgentTurn profile tools is refused before the bus [D-008]', function () {
    $app = wiredApp();
    $bus = new FakeCapabilityBus;
    $app->instance(CapabilityBus::class, $bus);
    $app->instance(AgentTurn::class, new ScriptedAgentTurn([['name' => 'billing.refund']]));

    $r = $app->make(ProcessTelegramUpdate::class)->handle(H::telegramUpdate(userId: 42));

    expect($r['ok'])->toBeFalse()
        ->and($r['error'])->toBe('tool_not_in_profile')
        ->and($bus->invokeCount())->toBe(0);
});
