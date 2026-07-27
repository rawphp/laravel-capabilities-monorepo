<?php

namespace Rawphp\Capabilities\Idempotency;

use InvalidArgumentException;

/**
 * Idempotency key format rules (D-005).
 *
 * Constraints: 1–128 chars, opaque, pattern [A-Za-z0-9._:-]+.
 */
final class IdempotencyKey
{
    public const MIN_LENGTH = 1;

    public const MAX_LENGTH = 128;

    public const PATTERN = '/^[A-Za-z0-9._:-]+$/';

    public static function isValid(string $key): bool
    {
        $len = strlen($key);

        if ($len < self::MIN_LENGTH || $len > self::MAX_LENGTH) {
            return false;
        }

        return preg_match(self::PATTERN, $key) === 1;
    }

    /**
     * @throws InvalidArgumentException when the key is empty, too long, or has illegal characters
     */
    public static function validate(string $key): string
    {
        if ($key === '') {
            throw new InvalidArgumentException('Idempotency key must not be empty (D-005).');
        }

        if (strlen($key) > self::MAX_LENGTH) {
            throw new InvalidArgumentException(sprintf(
                'Idempotency key must be at most %d characters (D-005).',
                self::MAX_LENGTH,
            ));
        }

        if (! self::isValid($key)) {
            throw new InvalidArgumentException(
                'Idempotency key may only contain A-Za-z0-9._:- (D-005).',
            );
        }

        return $key;
    }

    /**
     * CLI default: generate a UUID v4-style opaque key (D-005).
     */
    public static function generate(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }
}
