<?php

namespace Rawphp\Capabilities\Approval;

use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Support\SystemActor;

/**
 * Sweep stuck approved → executed (D-006 / P2-004).
 *
 * Invoked by a host scheduler every N seconds, or manually via {@see self::artisan()} /
 * {@see ApprovalManager::artisanResume()} (same force path as ops repair).
 * Core does **not** register a `capabilities:approvals-resume` Artisan command —
 * hosts may wrap this class if they want one.
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
