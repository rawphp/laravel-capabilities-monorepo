<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Console;

use Illuminate\Console\Command;
use Rawphp\CapabilitiesAi\Domain\StaleTurnReaper;
use Rawphp\CapabilitiesAi\Support\ContainerBindings;

/**
 * Artisan wrapper for {@see StaleTurnReaper}. Host schedules this; package does not auto-schedule.
 */
final class ReapStaleTurnsCommand extends Command
{
    protected $signature = 'capabilities-ai:reap-stale-turns';

    protected $description = 'Fail stale queued/running capabilities_ai turns (host schedules this).';

    public function handle(StaleTurnReaper $reaper): int
    {
        $config = (array) config('capabilities-ai', []);
        $claimTtl = ContainerBindings::claimTtlFromConfig($config);
        $staleQueued = (int) ($config['reaper']['stale_queued_minutes'] ?? 30);
        $grace = (int) ($config['reaper']['stale_running_grace_seconds'] ?? 60);

        $counts = $reaper->reap($staleQueued, $claimTtl, $grace);
        $this->info("reaped queued={$counts['queued']} running={$counts['running']}");

        return self::SUCCESS;
    }
}
