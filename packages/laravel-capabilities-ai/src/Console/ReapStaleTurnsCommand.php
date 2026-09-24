<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Console;

use Illuminate\Console\Command;
use Rawphp\Capabilities\Contracts\Metrics;
use Rawphp\CapabilitiesAi\Domain\StaleTurnReaper;
use Rawphp\CapabilitiesAi\Support\ContainerBindings;

/**
 * Artisan wrapper for {@see StaleTurnReaper}. Host schedules this; package does not auto-schedule.
 *
 * Reaped counts go to core's {@see Metrics} contract (D-019) so a growing stale-turn backlog is
 * visible after the scheduled run exits.
 */
final class ReapStaleTurnsCommand extends Command
{
    public const METRIC_REAPED = 'capabilities_ai_reaped_turns_total';

    protected $signature = 'capabilities-ai:reap-stale-turns';

    protected $description = 'Fail stale queued/running capabilities_ai turns (host schedules this).';

    public function handle(StaleTurnReaper $reaper, Metrics $metrics): int
    {
        $config = (array) $this->laravel->make('config')->get('capabilities-ai', []);
        $claimTtl = ContainerBindings::claimTtlFromConfig($config);
        $staleQueued = (int) ($config['reaper']['stale_queued_minutes'] ?? 30);
        $grace = (int) ($config['reaper']['stale_running_grace_seconds'] ?? 60);

        $counts = $reaper->reap($staleQueued, $claimTtl, $grace);
        $metrics->increment(self::METRIC_REAPED, $counts['queued'], ['status' => 'queued']);
        $metrics->increment(self::METRIC_REAPED, $counts['running'], ['status' => 'running']);
        $this->info("reaped queued={$counts['queued']} running={$counts['running']}");

        return self::SUCCESS;
    }
}
