<?php

declare(strict_types=1);

use Rawphp\Capabilities\Support\FailingAuditWriter;
use Rawphp\Capabilities\Support\FixedClock;
use Rawphp\Capabilities\Support\InMemoryAuditWriter;
use Rawphp\CapabilitiesMessaging\Notifiers\TelegramApprovalNotifier;
use Rawphp\CapabilitiesMessaging\Support\HttpTelegramBotClient;
use Rawphp\CapabilitiesMessaging\Support\TelegramBotApiException;
use Rawphp\CapabilitiesMessaging\Support\TelegramBotClient;
use Rawphp\CapabilitiesMessaging\Tests\Fixtures\MessagingHelpers as H;

/**
 * Telegram delivery failures leave an audit trace, then propagate unchanged (D-010).
 */
function deliveryAudit(): InMemoryAuditWriter
{
    return new InMemoryAuditWriter(new FixedClock(new DateTimeImmutable('2026-01-15T12:00:00Z')));
}

function failingBot(array $response): HttpTelegramBotClient
{
    return new HttpTelegramBotClient(H::config(), static fn (): array => $response);
}

function auditedNotifier(TelegramBotClient $bot, mixed $audit): TelegramApprovalNotifier
{
    return new TelegramApprovalNotifier(H::config(), $bot, H::signer(), $audit);
}

it('fail: sendMessage Bot API failure is audited as approval.notify_failed and rethrown [D-010]', function () {
    $audit = deliveryAudit();
    $n = auditedNotifier(failingBot(['ok' => false, 'error_code' => 403, 'description' => 'bot was blocked']), $audit);

    expect(fn () => $n->notifyPending([
        'id' => 'appr-9',
        'capability_name' => 'billing.void',
        'tenant_id' => 't1',
        'messaging' => ['chat_id' => '55'],
    ]))->toThrow(TelegramBotApiException::class, 'bot was blocked');

    expect($audit->all())->toHaveCount(1)
        ->and($audit->all()[0])->toMatchArray([
            'event' => 'approval.notify_failed',
            'approval_id' => 'appr-9',
            'capability_name' => 'billing.void',
            'tenant_id' => 't1',
            'channel' => 'telegram',
            'method' => 'sendMessage',
            'error_code' => 'forbidden',
            'retryable' => false,
            'error' => 'Telegram Bot API error on sendMessage: bot was blocked',
        ])
        ->and($n->notified())->toBe([]);
});

it('fail: editMessageText failure is audited and rethrown [D-010]', function () {
    $audit = deliveryAudit();
    $n = auditedNotifier(failingBot(['ok' => false, 'error_code' => 429, 'description' => 'slow down']), $audit);

    expect(fn () => $n->editMessage([
        'id' => 'appr-3',
        'capability_name' => 'x',
        'messaging' => ['chat_id' => '1', 'message_id' => 9],
    ], 'expired'))->toThrow(TelegramBotApiException::class);

    expect($audit->all())->toHaveCount(1)
        ->and($audit->all()[0])->toMatchArray([
            'event' => 'approval.notify_failed',
            'approval_id' => 'appr-3',
            'method' => 'editMessageText',
            'error_code' => 'rate_limited',
            'retryable' => true,
        ])
        ->and($n->edits())->toBe([]);
});

it('edge: non Bot API delivery failure is audited as internal, not retryable [D-010]', function () {
    $audit = deliveryAudit();
    $bot = H::bot();
    $bot->failNextSend();
    $n = auditedNotifier($bot, $audit);

    expect(fn () => $n->notifyPending(['id' => 'a1', 'messaging' => ['chat_id' => '1']]))
        ->toThrow(RuntimeException::class, 'Telegram sendMessage failed (fake).');

    expect($audit->all()[0])->toMatchArray([
        'event' => 'approval.notify_failed',
        'approval_id' => 'a1',
        'capability_name' => null,
        'tenant_id' => null,
        'error_code' => 'internal',
        'retryable' => false,
    ]);
});

it('edge: audit write failure never masks the delivery failure [D-010]', function () {
    $n = auditedNotifier(failingBot(['ok' => false, 'error_code' => 500]), new FailingAuditWriter);

    expect(fn () => $n->notifyPending(['id' => 'a1', 'messaging' => ['chat_id' => '1']]))
        ->toThrow(TelegramBotApiException::class);
});

it('happy: delivery failure without an audit writer still propagates [D-010]', function () {
    $n = auditedNotifier(failingBot(['ok' => false, 'error_code' => 500]), null);

    expect(fn () => $n->notifyPending(['id' => 'a1', 'messaging' => ['chat_id' => '1']]))
        ->toThrow(TelegramBotApiException::class);
});

it('happy: successful delivery writes no audit entry [D-010]', function () {
    $audit = deliveryAudit();
    $n = auditedNotifier(H::bot(), $audit);

    $n->notifyPending(['id' => 'a1', 'messaging' => ['chat_id' => '1', 'message_id' => 4]]);
    $n->editMessage(['id' => 'a1', 'messaging' => ['chat_id' => '1', 'message_id' => 4]], 'done');

    expect($audit->all())->toBe([]);
});

it('happy: provider hands the host AuditWriter to the notifier when bound [D-010]', function () {
    $src = (string) file_get_contents(H::MSG_SRC.'/MessagingServiceProvider.php');

    expect($src)->toContain('$app->bound(AuditWriter::class) ? $app->make(AuditWriter::class) : null');
});
