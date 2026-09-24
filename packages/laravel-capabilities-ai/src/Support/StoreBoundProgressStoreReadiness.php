<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Support;

use Rawphp\Capabilities\Contracts\Metrics;
use Rawphp\CapabilitiesAi\Contracts\ProgressStore;
use Rawphp\CapabilitiesAi\Contracts\ProgressStoreReadiness;
use Throwable;

/**
 * Live readiness: read-only since() ping of the bound ProgressStore (Redis in production).
 * Throw → not ready, and {@see METRIC_NOT_READY} is incremented when core Metrics is bound.
 */
final class StoreBoundProgressStoreReadiness implements ProgressStoreReadiness
{
    public const METRIC_NOT_READY = 'ai_progress_store_not_ready_total';

    /** Reserved turn id for the ping; never appended to. */
    public const PING_TURN = '__capabilities_ai_readiness__';

    public function __construct(
        private readonly ProgressStore $store,
        private readonly ?Metrics $metrics = null,
    ) {}

    public function isReady(): bool
    {
        try {
            $this->store->since(self::PING_TURN);

            return true;
        } catch (Throwable) {
            $this->metrics?->increment(self::METRIC_NOT_READY, 1, ['store' => $this->store::class]);

            return false;
        }
    }
}
