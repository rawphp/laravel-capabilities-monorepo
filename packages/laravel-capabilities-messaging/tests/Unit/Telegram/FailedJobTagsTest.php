<?php

declare(strict_types=1);

use Rawphp\Capabilities\Support\CapabilityContext;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\CapabilitiesMessaging\Identity\IdentityLinker;
use Rawphp\CapabilitiesMessaging\MessagingConfig;
use Rawphp\CapabilitiesMessaging\Tests\Fixtures\FakeCapabilityBus;
use Rawphp\CapabilitiesMessaging\Support\FakeTelegramBotClient;
use Rawphp\CapabilitiesMessaging\Telegram\ProcessTelegramUpdate;
use Rawphp\CapabilitiesMessaging\Telegram\TelegramAdapter;
use Rawphp\CapabilitiesMessaging\Tests\Fixtures\MessagingHelpers as H;


it("edge: ProcessTelegramUpdate failure tags channel [D-019]", function () {
    $p = H::processor();
    expect($p->failedJobTags(H::telegramUpdate())['channel'])->toBe('telegram');
});

it("edge: ProcessTelegramUpdate failure tags chat_id [D-019]", function () {
    $p = H::processor();
    expect($p->failedJobTags(H::telegramUpdate(chatId: 123))['chat_id'])->toBe('123');
});

it("edge: ProcessTelegramUpdate failure tags update_id [D-019]", function () {
    $p = H::processor();
    expect($p->failedJobTags(H::telegramUpdate(updateId: 55))['update_id'])->toBe(55);
});
