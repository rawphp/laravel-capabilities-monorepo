<?php

namespace Rawphp\Capabilities\Idempotency;

/**
 * Idempotency subsystem configuration (D-005).
 */
final class IdempotencyConfig
{
    public const DEFAULT_TTL_HOURS = 24;

    public const DEFAULT_HEADER = 'Idempotency-Key';

    public function __construct(
        public readonly bool $enabled = true,
        public readonly int $ttlHours = self::DEFAULT_TTL_HOURS,
        public readonly string $header = self::DEFAULT_HEADER,
        public readonly bool $warnMissingKey = true,
    ) {
        if ($this->ttlHours < 1) {
            throw new \InvalidArgumentException('idempotency.ttl_hours must be >= 1.');
        }

        if ($this->header === '') {
            throw new \InvalidArgumentException('idempotency.header must not be empty.');
        }
    }

    public static function defaults(): self
    {
        return new self;
    }

    /**
     * @param  array{
     *     enabled?: bool,
     *     ttl_hours?: int,
     *     header?: string,
     *     warn_missing_key?: bool
     * }  $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            enabled: (bool) ($config['enabled'] ?? true),
            ttlHours: (int) ($config['ttl_hours'] ?? self::DEFAULT_TTL_HOURS),
            header: (string) ($config['header'] ?? self::DEFAULT_HEADER),
            warnMissingKey: (bool) ($config['warn_missing_key'] ?? true),
        );
    }

    /**
     * @return array{enabled: bool, ttl_hours: int, header: string, warn_missing_key: bool}
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'ttl_hours' => $this->ttlHours,
            'header' => $this->header,
            'warn_missing_key' => $this->warnMissingKey,
        ];
    }
}
