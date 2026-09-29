<?php

namespace Rawphp\CapabilitiesMessaging\Support;

use RuntimeException;

/**
 * In-memory Telegram Bot API fake for unit tests — never hits the network.
 */
final class FakeTelegramBotClient implements TelegramBotClient
{
    /** @var list<array{method: string, args: array<string, mixed>}> */
    private array $calls = [];

    private bool $failSend = false;

    private int $messageSeq = 1;

    public function failNextSend(bool $fail = true): void
    {
        $this->failSend = $fail;
    }

    public function sendMessage(string $chatId, string $text, array $payload = []): array
    {
        if ($this->failSend) {
            $this->failSend = false;
            throw new RuntimeException('Telegram sendMessage failed (fake).');
        }

        $this->assertCallbackDataFits($payload);

        $messageId = $this->messageSeq++;
        $args = array_merge($payload, [
            'chat_id' => $chatId,
            'text' => $text,
            'message_id' => $messageId,
        ]);
        $this->calls[] = ['method' => 'sendMessage', 'args' => $args];

        return ['ok' => true, 'result' => ['message_id' => $messageId, 'chat' => ['id' => $chatId], 'text' => $text]];
    }

    public function editMessageText(string $chatId, string|int $messageId, string $text, array $payload = []): array
    {
        $args = array_merge($payload, [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
        ]);
        $this->calls[] = ['method' => 'editMessageText', 'args' => $args];

        return ['ok' => true, 'result' => ['message_id' => $messageId, 'text' => $text]];
    }

    public function answerCallbackQuery(string $callbackQueryId, string $text = '', array $payload = []): array
    {
        $this->calls[] = ['method' => 'answerCallbackQuery', 'args' => array_merge($payload, [
            'callback_query_id' => $callbackQueryId,
            'text' => $text,
        ])];

        return ['ok' => true, 'result' => true];
    }

    /**
     * Mirror the Bot API: callback_data over 64 bytes is a 400 BUTTON_DATA_INVALID.
     *
     * @param  array<string, mixed>  $payload
     */
    private function assertCallbackDataFits(array $payload): void
    {
        foreach ($payload['reply_markup']['inline_keyboard'] ?? [] as $row) {
            foreach ((array) $row as $button) {
                if (strlen((string) ($button['callback_data'] ?? '')) > 64) {
                    throw TelegramBotApiException::fromResponse('sendMessage', [
                        'ok' => false,
                        'error_code' => 400,
                        'description' => 'Bad Request: BUTTON_DATA_INVALID',
                    ]);
                }
            }
        }
    }

    /**
     * @return list<array{method: string, args: array<string, mixed>}>
     */
    public function calls(): array
    {
        return $this->calls;
    }

    public function reset(): void
    {
        $this->calls = [];
        $this->messageSeq = 1;
        $this->failSend = false;
    }
}
