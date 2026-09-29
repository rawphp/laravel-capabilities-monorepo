<?php

namespace Rawphp\CapabilitiesMessaging\Support;

/**
 * Telegram message text limits. Bot API counts text in UTF-16 code units (an emoji outside the
 * BMP is two), 4096 per message.
 */
final class TelegramText
{
    public const MAX_LENGTH = 4096;

    public static function length(string $text): int
    {
        return intdiv(strlen((string) mb_convert_encoding($text, 'UTF-16LE', 'UTF-8')), 2);
    }

    /**
     * Cut `text` to at most `max` units, ending in an ellipsis when anything was dropped.
     */
    public static function truncate(string $text, int $max = self::MAX_LENGTH): string
    {
        if (self::length($text) <= $max) {
            return $text;
        }

        $kept = '';
        $used = 0;
        foreach (mb_str_split($text) as $char) {
            $units = strlen($char) === 4 ? 2 : 1;
            if ($used + $units > $max - 1) {
                break;
            }
            $kept .= $char;
            $used += $units;
        }

        return $kept.'…';
    }
}
