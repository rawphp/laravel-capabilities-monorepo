<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Support;

use Rawphp\Capabilities\Contracts\IdempotencyStore;
use Rawphp\CapabilitiesAi\Contracts\IdempotencyReadiness;
use Throwable;

/**
 * Live accept-time readiness: core IdempotencyStore bound + ping succeeds.
 * Unbound store → not ready (fail closed). AlwaysReadyIdempotency is tests-only.
 */
final class StoreBoundIdempotencyReadiness implements IdempotencyReadiness
{
    private function __construct(
        private readonly ?IdempotencyStore $store,
    ) {}

    public static function unbound(): self
    {
        return new self(null);
    }

    public static function forStore(IdempotencyStore $store): self
    {
        return new self($store);
    }

    public function isReady(): bool
    {
        if ($this->store === null) {
            return false;
        }

        try {
            // Ping only — null record is success; throw → not ready.
            $this->store->find(
                null,
                'system',
                'capabilities-ai-readiness',
                '__capabilities_ai_readiness__',
                '__ping__',
            );

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
