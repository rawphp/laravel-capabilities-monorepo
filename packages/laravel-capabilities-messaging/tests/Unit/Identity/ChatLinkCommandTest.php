<?php

declare(strict_types=1);

use Rawphp\CapabilitiesMessaging\Identity\IdentityLinker;
use Rawphp\CapabilitiesMessaging\Telegram\ProcessTelegramUpdate;
use Rawphp\CapabilitiesMessaging\Telegram\TelegramAdapter;
use Rawphp\CapabilitiesMessaging\Tests\Fixtures\FakeCapabilityBus;
use Rawphp\CapabilitiesMessaging\Tests\Fixtures\MessagingHelpers as H;

/**
 * code_link (default identity mode): the Telegram user presents the app-issued code with
 * `/start <code>` or `/link <code>`; the pipeline binds before identity resolution (MSG-002).
 */
function linkFixture(array $configOverrides = []): array
{
    $bot = H::bot();
    $config = H::config($configOverrides);
    $identity = new IdentityLinker($config);
    $agentCalls = 0;
    $adapter = new TelegramAdapter($bot, static function (array $m) use (&$agentCalls): array {
        $agentCalls++;

        return ['text' => 'agent: '.$m['text'], 'tool_calls' => []];
    });
    $registry = new FakeCapabilityBus;
    $processor = H::processor(['config' => $config, 'identity' => $identity, 'adapter' => $adapter, 'bot' => $bot, 'registry' => $registry]);

    return ['bot' => $bot, 'identity' => $identity, 'processor' => $processor, 'registry' => $registry, 'agent_calls' => &$agentCalls];
}

it('happy: /start <code> binds the Telegram user and confirms, without an agent turn [MSG-002]', function (string $command) {
    $f = linkFixture();
    $code = $f['identity']->issueLinkCode('u1');

    $r = $f['processor']->handle(H::telegramUpdate(userId: 42, text: "/{$command} {$code}"));

    expect($r['ok'])->toBeTrue()
        ->and($r['linked'])->toBeTrue()
        ->and($f['identity']->isLinked('42'))->toBeTrue()
        ->and($f['bot']->calls()[0]['args']['text'])->toBe(ProcessTelegramUpdate::LINKED_REPLY)
        ->and($f['agent_calls'])->toBe(0)
        ->and($f['registry']->invokeCount())->toBe(0);

    $next = $f['processor']->handle(H::telegramUpdate(userId: 42, text: 'hi', updateId: 2));
    expect($next['ok'])->toBeTrue()->and($next['reply'])->toBe('agent: hi');
})->with(['start', 'link']);

it('fail: a reused code is refused for a second Telegram user [MSG-002]', function () {
    $f = linkFixture();
    $code = $f['identity']->issueLinkCode('u1');
    $f['processor']->handle(H::telegramUpdate(userId: 42, text: "/start {$code}"));

    $r = $f['processor']->handle(H::telegramUpdate(userId: 77, text: "/start {$code}", updateId: 2));

    expect($r['ok'])->toBeFalse()
        ->and($r['error'])->toBe('link_code_invalid')
        ->and($f['identity']->isLinked('77'))->toBeFalse()
        ->and($f['bot']->calls()[1]['args']['text'])->toBe(ProcessTelegramUpdate::LINK_FAILED_REPLY);
});

it('fail: an expired or unknown code is refused [MSG-002]', function () {
    $f = linkFixture();
    $expired = $f['identity']->issueLinkCode('u1', null, time() - 3600);

    $r1 = $f['processor']->handle(H::telegramUpdate(userId: 42, text: "/start {$expired}"));
    $r2 = $f['processor']->handle(H::telegramUpdate(userId: 42, text: '/start 0123456789abcdef', updateId: 2));

    expect($r1['error'])->toBe('link_code_invalid')
        ->and($r2['error'])->toBe('link_code_invalid')
        ->and($f['identity']->isLinked('42'))->toBeFalse();
});

it('edge: allowlist mode does not treat /start <code> as a bind [MSG-002]', function () {
    $f = linkFixture(['identity' => ['mode' => 'allowlist']]);
    $code = $f['identity']->issueLinkCode('u1');

    $r = $f['processor']->handle(H::telegramUpdate(userId: 42, text: "/start {$code}"));

    expect($r['ok'])->toBeFalse()
        ->and($r['error'])->toBe('identity_unresolved')
        ->and($f['identity']->isLinked('42'))->toBeFalse();
});

it('edge: a plain /start without a code goes through the normal pipeline [MSG-002]', function () {
    $f = linkFixture();

    $r = $f['processor']->handle(H::telegramUpdate(userId: 42, text: '/start'));

    expect($r['error'])->toBe('identity_unresolved');
});
