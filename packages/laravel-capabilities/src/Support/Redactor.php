<?php

namespace Rawphp\Capabilities\Support;

/**
 * Sensitive-key redaction shared by audit entries and any surface that echoes
 * capability input back out (D-010).
 */
final class Redactor
{
    public const PLACEHOLDER = '[REDACTED]';

    /** @var list<string> Matched as substrings of the normalized key. */
    private const SENSITIVE_KEYS = ['password', 'secret', 'token', 'apikey', 'authorization'];

    /**
     * Recursively redacts values whose key contains a sensitive word, ignoring case
     * and separators (Authorization, user_password, apiKey, Access-Token).
     *
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    public static function redact(array $data): array
    {
        foreach ($data as $key => $value) {
            if (self::isSensitiveKey((string) $key)) {
                $data[$key] = self::PLACEHOLDER;

                continue;
            }

            if (is_array($value)) {
                $data[$key] = self::redact($value);
            }
        }

        return $data;
    }

    public static function isSensitiveKey(string $key): bool
    {
        $normalized = str_replace(['_', '-', '.', ' '], '', strtolower($key));
        foreach (self::SENSITIVE_KEYS as $sensitive) {
            if (str_contains($normalized, $sensitive)) {
                return true;
            }
        }

        return false;
    }
}
