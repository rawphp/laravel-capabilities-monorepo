<?php

namespace Rawphp\CapabilitiesMessaging\Support;

use Rawphp\CapabilitiesMessaging\MessagingConfig;
use RuntimeException;

/**
 * Production Telegram Bot API client over HTTP.
 *
 * Transport is injectable so unit tests never hit the network (L-004).
 * Default transport uses file_get_contents / stream context against api.telegram.org
 * when no transport is provided (real apps should prefer Illuminate HTTP via provider wiring).
 */
final class HttpTelegramBotClient implements TelegramBotClient
{
    /** Optional Bot API parameters callers may pass; any other payload key is dropped. */
    private const OPTIONAL_PARAMS = ['message_thread_id', 'reply_markup', 'parse_mode'];

    /** @var callable(string, array<string, mixed>, string): array<string, mixed> */
    private $transport;

    /**
     * @param  (callable(string $method, array<string, mixed> $params, string $token): array<string, mixed>)|null  $transport
     */
    public function __construct(
        private readonly MessagingConfig $config,
        ?callable $transport = null,
    ) {
        $this->transport = $transport ?? self::defaultTransport(...);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function sendMessage(string $chatId, string $text, array $payload = []): array
    {
        $params = array_merge([
            'chat_id' => $chatId,
            'text' => $text,
        ], self::optional($payload));

        return $this->call('sendMessage', $params);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function editMessageText(string $chatId, string|int $messageId, string $text, array $payload = []): array
    {
        $params = array_merge([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
        ], self::optional($payload));

        return $this->call('editMessageText', $params);
    }

    public function answerCallbackQuery(string $callbackQueryId, string $text = '', array $payload = []): array
    {
        $params = ['callback_query_id' => $callbackQueryId];
        if ($text !== '') {
            $params['text'] = $text;
        }
        if (isset($payload['show_alert'])) {
            $params['show_alert'] = (bool) $payload['show_alert'];
        }

        return $this->call('answerCallbackQuery', $params);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function optional(array $payload): array
    {
        return array_intersect_key($payload, array_flip(self::OPTIONAL_PARAMS));
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function call(string $method, array $params): array
    {
        $token = $this->config->botToken();
        if ($token === null) {
            throw new RuntimeException(
                'TELEGRAM_BOT_TOKEN is required for HttpTelegramBotClient (D-021).'
            );
        }

        $result = ($this->transport)($method, $params, $token);
        if (! is_array($result)) {
            throw new RuntimeException('Telegram Bot API transport returned a non-array response.');
        }

        if (($result['ok'] ?? false) !== true) {
            throw TelegramBotApiException::fromResponse($method, $result);
        }

        return $result;
    }

    /**
     * Built-in production transport (no Illuminate dependency). Unit tests inject a fake.
     * Returns the decoded Bot API body; call() classifies ok:false responses (D-018).
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private static function defaultTransport(string $method, array $params, string $token): array
    {
        $url = sprintf('https://api.telegram.org/bot%s/%s', $token, $method);
        $body = json_encode($params, JSON_THROW_ON_ERROR);
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $body,
                'timeout' => 15,
                'ignore_errors' => true,
            ],
        ]);

        $raw = @file_get_contents($url, false, $context);
        if ($raw === false) {
            throw TelegramBotApiException::transportFailure($method, 'request failed');
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            throw TelegramBotApiException::transportFailure($method, 'invalid JSON');
        }

        return $decoded;
    }
}
