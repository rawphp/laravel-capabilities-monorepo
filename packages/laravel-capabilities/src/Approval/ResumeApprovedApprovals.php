<?php

namespace Rawphp\Capabilities\Approval;

use Rawphp\Capabilities\Adapters\Artisan\ResumeApprovalsCommand;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Support\SystemActor;

/**
 * Sweep stuck approved → executed (D-006 / P2-004).
 *
 * Run by `capabilities:approvals-resume` ({@see ResumeApprovalsCommand}),
 * which the service provider schedules every `approval.resume.every_seconds` when
 * `execution = deferred` and `resume.enabled` ({@see ResumeSchedulePlan}). Manual repair:
 * `--force --id=…`, {@see self::artisan()} or {@see ApprovalManager::artisanResume()}.
 * Delegates to {@see ApprovalManager::resume()} so accept and resume share paths.
 */
final class ResumeApprovedApprovals
{
    public function __construct(
        private readonly ApprovalManager $manager,
    ) {}

    public function manager(): ApprovalManager
    {
        return $this->manager;
    }

    /**
     * @return list<CapabilityResult>
     */
    public function handle(?string $id = null): array
    {
        return $this->manager->resume($id, SystemActor::named('approval-resume'));
    }

    /**
     * Same path as the scheduler (manual repair).
     *
     * @return list<CapabilityResult>
     */
    public function artisan(?string $id = null): array
    {
        return $this->manager->artisanResume($id);
    }

    public function shouldSchedule(): bool
    {
        return $this->manager->resumeEnabled();
    }

    public function everySeconds(): int
    {
        return $this->manager->resumeEverySeconds();
    }
}
