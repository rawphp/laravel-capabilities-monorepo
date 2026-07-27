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
