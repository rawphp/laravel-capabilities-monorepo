<?php

declare(strict_types=1);

namespace Rawphp\Capabilities\Adapters\Artisan;

use Illuminate\Console\Command;
use Rawphp\Capabilities\Approval\ApprovalManager;
use Rawphp\Capabilities\Approval\ResumeApprovedApprovals;
use Rawphp\Capabilities\Support\CapabilityResult;
use Throwable;

/**
 * Ops command: finish approved-but-not-executed approvals (D-006 / P2-004 Shape A).
 *
 * Scheduled by the service provider every `approval.resume.every_seconds` when
 * `execution = deferred` and `resume.enabled` (respects grace + lease); `--force` is the
 * operator repair path for one `--id` (same as {@see ApprovalManager::artisanResume()}).
 * Not the product CLI (D-016) — in-server ops only.
 */
class ResumeApprovalsCommand extends Command
{
    public const SIGNATURE_NAME = 'capabilities:approvals-resume';

    protected $signature = 'capabilities:approvals-resume
                            {--id= : Resume one approval id instead of sweeping}
                            {--force : Ignore grace and lease (operator repair; use with --id)}';

    protected $description = 'Execute stuck approved approvals (crash recovery sweep); scheduled automatically when approval.resume.enabled.';

    public function __construct(
        private readonly ?ApprovalManager $manager = null,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $manager = $this->manager;
        if ($manager === null) {
            try {
                $manager = $this->laravel->make(ApprovalManager::class);
            } catch (Throwable) {
                $this->error('ApprovalManager is not bound.');

                return self::FAILURE;
            }
        }

        $id = $this->option('id');
        $summary = self::sweep($manager, is_string($id) && $id !== '' ? $id : null, (bool) $this->option('force'));

        $this->line(sprintf('resumed=%d skipped=%d failed=%d', $summary['resumed'], $summary['skipped'], $summary['failed']));

        return self::SUCCESS;
    }

    /**
     * Pure sweep: scheduled path ({@see ResumeApprovedApprovals::handle()}) or forced
     * operator repair ({@see ResumeApprovedApprovals::artisan()}).
     *
     * @return array{resumed: int, skipped: int, failed: int}
     */
    public static function sweep(ApprovalManager $manager, ?string $id, bool $force): array
    {
        $resume = new ResumeApprovedApprovals($manager);
        $results = $force ? $resume->artisan($id) : $resume->handle($id);

        $summary = ['resumed' => 0, 'skipped' => 0, 'failed' => 0];
        foreach ($results as $result) {
            $summary[self::bucket($result)]++;
        }

        return $summary;
    }

    private static function bucket(CapabilityResult $result): string
    {
        if ($result->isOk()) {
            return 'resumed';
        }

        return ($result->error['skipped'] ?? false) === true ? 'skipped' : 'failed';
    }
}
