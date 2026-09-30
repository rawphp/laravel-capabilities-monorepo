<?php

namespace Rawphp\CapabilitiesMessaging\Support;

/**
 * Telegram message text limits. Bot API counts text in UTF-16 code units (an emoji outside the
 * BMP is two), 4096 per message, and rejects empty text.
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

        return self::prefix($text, $max - 1).'…';
    }

    /**
     * Split `text` into messages of at most `max` units, breaking at the last paragraph break,
     * else line break, else space in the second half of each window, else mid-word. Blank text
     * yields no messages.
     *
     * @return list<string>
     */
    public static function split(string $text, int $max = self::MAX_LENGTH): array
    {
        $parts = [];
        $rest = trim($text);
        while (self::length($rest) > $max) {
            $window = self::prefix($rest, $max);
            $cut = strlen($window);
            foreach (["\n\n", "\n", ' '] as $separator) {
                $at = strrpos($window, $separator);
                if ($at !== false && $at >= intdiv($cut, 2)) {
                    $cut = $at;
                    break;
                }
            }
            $parts[] = rtrim(substr($rest, 0, $cut));
            $rest = ltrim(substr($rest, $cut));
        }
        if ($rest !== '') {
            $parts[] = $rest;
        }

        return $parts;
    }

    /**
     * The longest prefix of `text` within `units`, never splitting a character.
     */
    private static function prefix(string $text, int $units): string
    {
        $kept = '';
        $used = 0;
        foreach (mb_str_split($text) as $char) {
            $width = strlen($char) === 4 ? 2 : 1;
            if ($used + $width > $units) {
                break;
            }
            $kept .= $char;
            $used += $width;
        }

        return $kept;
    }
}
