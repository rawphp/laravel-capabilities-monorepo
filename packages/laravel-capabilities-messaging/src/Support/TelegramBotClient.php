<?php

namespace Rawphp\CapabilitiesMessaging\Support;

/**
 * Narrow Bot API surface used by the messaging package (mockable; no network).
 */
interface TelegramBotClient
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function sendMessage(string $chatId, string $text, array $payload = []): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function editMessageText(string $chatId, string|int $messageId, string $text, array $payload = []): array;

    /**
     * @return list<array{method: string, args: array<string, mixed>}>
     */
    public function calls(): array;
}
