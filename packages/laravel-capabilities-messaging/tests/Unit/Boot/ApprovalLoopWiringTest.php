<?php

// M-101: the provider wires the Telegram approval loop end to end — the notifier is reachable
// through core's container seam, and the update processor carries a CallbackHandler.

declare(strict_types=1);

use Rawphp\Capabilities\Contracts\ApprovalGateway;
use Rawphp\Capabilities\Contracts\ApprovalNotifier;
use Rawphp\CapabilitiesMessaging\Identity\IdentityLinker;
use Rawphp\CapabilitiesMessaging\MessagingServiceProvider;
use Rawphp\CapabilitiesMessaging\Notifiers\TelegramApprovalNotifier;
use Rawphp\CapabilitiesMessaging\Telegram\CallbackHandler;
use Rawphp\CapabilitiesMessaging\Telegram\ProcessTelegramUpdate;
use Rawphp\CapabilitiesMessaging\Tests\Fixtures\MessagingHelpers as H;

it('tags the Telegram notifier with the core approval-notifier tag and keeps the contract alias', function () {
    $app = H::container(['telegram' => ['enabled' => true]]);

    $tagged = iterator_to_array($app->tagged(ApprovalNotifier::CONTAINER_TAG), false);

    expect($tagged)->toHaveCount(1)
        ->and($tagged[0])->toBeInstanceOf(TelegramApprovalNotifier::class)
        ->and($app->make(ApprovalNotifier::class))->toBe($tagged[0])
        ->and(MessagingServiceProvider::registrationPlan()['tags'])->toBe([ApprovalNotifier::CONTAINER_TAG => [TelegramApprovalNotifier::class]]);
});

it('binds CallbackHandler on the core ApprovalGateway and hands it to ProcessTelegramUpdate', function () {
    $app = H::container(['telegram' => ['enabled' => true, 'callback_secret' => 'cb-secret']]);
    $app->instance(ApprovalGateway::class, H::approvals());

    $handler = $app->make(CallbackHandler::class);
    $processor = $app->make(ProcessTelegramUpdate::class);
    $wired = (new ReflectionClass($processor))->getProperty('callbacks')->getValue($processor);

    expect($handler)->toBeInstanceOf(CallbackHandler::class)
        // Lazy factory (D-021: no callback secret needed to build the processor) resolving to the singleton.
        ->and($wired)->toBeInstanceOf(Closure::class)
        ->and($wired())->toBe($handler);
});

it('a tap on a host with no callback secret is answered as unavailable instead of crashing the worker [D-021]', function () {
    $app = H::container(['telegram' => ['enabled' => true]]);
    $processor = $app->make(ProcessTelegramUpdate::class);

    $r = $processor->handle(H::callbackUpdate('cb-1'));

    expect($r['ok'])->toBeFalse()
        ->and($r['error'])->toBe('callback_handler_unavailable');
});

it('without a core ApprovalGateway the handler is still built and fails closed on use', function () {
    $app = H::container(['telegram' => ['enabled' => true, 'callback_secret' => 'cb-secret']]);
    $identity = $app->make(IdentityLinker::class);

    $handler = $app->make(CallbackHandler::class);

    expect($handler)->toBeInstanceOf(CallbackHandler::class)
        ->and(fn () => $handler->handleCallbackData(H::signer(H::config(['telegram' => ['callback_secret' => 'cb-secret']]))->encode(H::signer(H::config(['telegram' => ['callback_secret' => 'cb-secret']]))->sign('x', 'accept')), ['id' => '1']))
        ->toThrow(RuntimeException::class, 'ApprovalGateway is required');
});
