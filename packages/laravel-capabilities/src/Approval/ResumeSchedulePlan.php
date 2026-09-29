<?php

namespace Rawphp\Capabilities\Approval;

use Illuminate\Console\Scheduling\Schedule;
use Rawphp\Capabilities\Adapters\Artisan\ResumeApprovalsCommand;

/**
 * Pure scheduling plan for the Shape A crash-recovery sweep (D-006 / P2-004 / L-014).
 *
 * `approval.resume.enabled` with `execution = deferred` schedules
 * `capabilities:approvals-resume` every `approval.resume.every_seconds` (minute granularity —
 * the Laravel scheduler ticks once a minute, so sub-minute values run every minute). Atomic
 * execution has no `approved` limbo and schedules nothing.
 */
final class ResumeSchedulePlan
{
    /**
     * @param  array<string, mixed>  $approvalConfig  `config('capabilities.approval')`
     * @return array{command: string, cron: string}|null
     */
    public static function fromConfig(array $approvalConfig): ?array
    {
        $merged = ApprovalManager::mergeConfig($approvalConfig);
        $deferred = $merged['execution'] === ApprovalStateMachine::EXECUTION_DEFERRED;
        $enabled = (bool) ($merged['resume']['enabled'] ?? true);

        if (! $deferred || ! $enabled) {
            return null;
        }

        return [
            'command' => ResumeApprovalsCommand::SIGNATURE_NAME,
            'cron' => self::cronExpression((int) ($merged['resume']['every_seconds'] ?? 60)),
        ];
    }

    public static function cronExpression(int $everySeconds): string
    {
        $minutes = intdiv(max(1, $everySeconds), 60);
        if ($minutes <= 1) {
            return '* * * * *';
        }
        if ($minutes >= 60) {
            return '0 * * * *';
        }

        return "*/{$minutes} * * * *";
    }

    /**
     * Register the plan on a console Schedule (duck-typed so units need no container).
     *
     * @param  object  $schedule  {@see Schedule}
     * @param  array{command: string, cron: string}  $plan
     */
    public static function apply(object $schedule, array $plan): void
    {
        $schedule->command($plan['command'])
            ->cron($plan['cron'])
            ->withoutOverlapping();
    }
}
