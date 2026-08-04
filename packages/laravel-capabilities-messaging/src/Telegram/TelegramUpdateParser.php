<?php

namespace Rawphp\CapabilitiesMessaging\Telegram;

/**
 * Pure Telegram Update payload field extraction.
 *
 * Keeps {@see ProcessTelegramUpdate} focused on the MSG-003 pipeline
 * (identity → thread → tools → reply) rather than wire-shape parsing.
 */
final class TelegramUpdateParser
{
    /**
     * @param  array<string, mixed>  $update
     */
    public static function isValidShape(array $update): bool
    {
        return isset($update['update_id'])
            || isset($update['message'])
            || isset($update['callback_query']);
    }

    /**
     * @param  array<string, mixed>  $update
     */
    public static function chatId(array $update): string|int|null
    {
        return $update['message']['chat']['id']
            ?? $update['callback_query']['message']['chat']['id']
            ?? $update['chat_id']
            ?? null;
    }

    /**
     * @param  array<string, mixed>  $update
     */
    public static function telegramUserId(array $update): ?string
    {
        $id = $update['message']['from']['id']
            ?? $update['callback_query']['from']['id']
            ?? $update['telegram_user_id']
            ?? null;

        return $id === null ? null : (string) $id;
    }

    /**
     * @param  array<string, mixed>  $update
     */
    public static function topicId(array $update): string|int|null
    {
        return $update['message']['message_thread_id']
            ?? $update['topic_id']
            ?? null;
    }

    /**
     * @param  array<string, mixed>  $update
     */
    public static function text(array $update): string
    {
        return (string) ($update['message']['text']
            ?? $update['callback_query']['data']
            ?? $update['text']
            ?? '');
    }

    /**
     * @param  array<string, mixed>  $update
     */
    public static function messageId(array $update): string|int|null
    {
        return $update['message']['message_id']
            ?? $update['callback_query']['message']['message_id']
            ?? null;
    }

    /**
     * @param  array<string, mixed>  $update
     * @return array{channel: string, chat_id: string|null, update_id: int|string|null}
     */
    public static function tags(array $update): array
    {
        $chat = self::chatId($update);

        return [
            'channel' => 'telegram',
            'chat_id' => $chat === null ? null : (string) $chat,
            'update_id' => $update['update_id'] ?? null,
        ];
    }
}
