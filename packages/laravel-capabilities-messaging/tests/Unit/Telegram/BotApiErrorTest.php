<?php

declare(strict_types=1);

use Rawphp\Capabilities\Support\ErrorCodeMap;
use Rawphp\CapabilitiesMessaging\Support\HttpTelegramBotClient;
use Rawphp\CapabilitiesMessaging\Support\TelegramBotApiException;
use Rawphp\CapabilitiesMessaging\Tests\Fixtures\MessagingHelpers as H;

/**
 * Outbound Bot API failures carry the same D-018 error codes as the inbound capability API.
 */
function botReturning(array $response): HttpTelegramBotClient
{
    return new HttpTelegramBotClient(
        H::config(['telegram' => ['bot_token' => 'tok']]),
        static fn (): array => $response,
    );
}

function botApiError(array $response): TelegramBotApiException
{
    try {
        botReturning($response)->sendMessage('42', 'hi');
    } catch (TelegramBotApiException $e) {
        return $e;
    }

    throw new RuntimeException('expected TelegramBotApiException');
}

it('maps Telegram error_code to a D-018 code [D-018]', function (int $telegramCode, string $code, bool $retryable) {
    $e = botApiError(['ok' => false, 'error_code' => $telegramCode, 'description' => 'nope']);

    expect($e->errorCode)->toBe($code)
        ->and(ErrorCodeMap::isKnown($e->errorCode))->toBeTrue()
        ->and($e->retryable)->toBe($retryable)
        ->and($e->method)->toBe('sendMessage')
        ->and($e->getMessage())->toBe('Telegram Bot API error on sendMessage: nope');
})->with([
    'bad request' => [400, 'validation_failed', false],
    'bad token' => [401, 'unauthenticated', false],
    'bot blocked' => [403, 'forbidden', false],
    'unknown method' => [404, 'not_found', false],
    'webhook conflict' => [409, 'conflict', false],
    'flood control' => [429, 'rate_limited', true],
    'server error' => [502, 'internal', true],
    'unmapped 4xx' => [418, 'domain_error', false],
    'missing error_code' => [0, 'internal', true],
]);

it('carries retry_after from a 429 flood-control response [D-018]', function () {
    $e = botApiError([
        'ok' => false,
        'error_code' => 429,
        'description' => 'Too Many Requests: retry after 7',
        'parameters' => ['retry_after' => 7],
    ]);

    expect($e->errorCode)->toBe('rate_limited')
        ->and($e->retryAfter)->toBe(7);
});

it('has no retry_after when Telegram sends none [D-018]', function () {
    $e = botApiError(['ok' => false, 'error_code' => 400]);

    expect($e->retryAfter)->toBeNull()
        ->and($e->getMessage())->toBe('Telegram Bot API error on sendMessage: unknown error');
});

it('fails closed when the response has no ok flag [D-018]', function () {
    $e = botApiError(['result' => []]);

    expect($e->errorCode)->toBe('internal');
});

it('transport failure is an internal, retryable error [D-018]', function () {
    $e = TelegramBotApiException::transportFailure('editMessageText', 'request failed');

    expect($e->errorCode)->toBe('internal')
        ->and($e->retryable)->toBeTrue()
        ->and($e->method)->toBe('editMessageText')
        ->and($e->getMessage())->toBe('Telegram Bot API request failed on editMessageText: request failed');
});

it('stays a RuntimeException so existing catch sites keep working [D-018]', function () {
    expect(botApiError(['ok' => false, 'error_code' => 500]))->toBeInstanceOf(RuntimeException::class);
});

it('returns the body unchanged when ok is true [L-004]', function () {
    expect(botReturning(['ok' => true, 'result' => ['message_id' => 1]])->sendMessage('42', 'hi'))
        ->toBe(['ok' => true, 'result' => ['message_id' => 1]]);
});
