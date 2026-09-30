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
     * A tapped inline button (Telegram `callback_query`) — an approval decision, never chat text.
     *
     * @param  array<string, mixed>  $update
     */
    public static function isCallbackQuery(array $update): bool
    {
        return is_array($update['callback_query'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $update
     */
    public static function callbackQueryId(array $update): ?string
    {
        $id = $update['callback_query']['id'] ?? null;

        return is_scalar($id) ? (string) $id : null;
    }

    /**
     * @param  array<string, mixed>  $update
     */
    public static function callbackData(array $update): string
    {
        $data = $update['callback_query']['data'] ?? '';

        return is_scalar($data) ? (string) $data : '';
    }

    /**
     * @param  array<string, mixed>  $update
     * @return array<string, mixed>
     */
    public static function callbackFrom(array $update): array
    {
        $from = $update['callback_query']['from'] ?? [];

        return is_array($from) ? $from : [];
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
    public static function isPrivateChat(array $update): bool
    {
        return ($update['message']['chat']['type'] ?? null) === 'private';
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
     * Stable D-005 key for the Nth tool call of one update, so a redelivered
     * update replays the stored outcome instead of running the capability again.
     * Null when update_id or chat id is not an integer — never invent a key.
     *
     * @param  array<string, mixed>  $update
     */
    public static function idempotencyKey(array $update, int $toolCallIndex): ?string
    {
        $key = self::updateKey($update);

        return $key === null ? null : $key.':'.$toolCallIndex;
    }

    /**
     * `telegram:<chat>:<update_id>` — one update in one chat. Null when either is not an integer.
     *
     * @param  array<string, mixed>  $update
     */
    public static function updateKey(array $update): ?string
    {
        $updateId = $update['update_id'] ?? null;
        $chatId = self::chatId($update);
        if ($chatId === null || (! is_int($updateId) && ! is_string($updateId))) {
            return null;
        }

        $key = sprintf('telegram:%s:%s', $chatId, $updateId);

        return preg_match('/^telegram:-?\d+:-?\d+$/', $key) === 1 ? $key : null;
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
