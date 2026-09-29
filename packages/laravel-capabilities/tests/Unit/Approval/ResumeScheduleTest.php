<?php

// L-014 / P2-004: approval.resume.* schedules the crash-recovery sweep for real. Unit-only.

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule;
use Rawphp\Capabilities\Adapters\Artisan\ArtisanCommandTable;
use Rawphp\Capabilities\Adapters\Artisan\ResumeApprovalsCommand;
use Rawphp\Capabilities\Approval\ApprovalManager;
use Rawphp\Capabilities\Approval\ResumeSchedulePlan;
use Rawphp\Capabilities\Tests\Fixtures\ApprovalHelpers;
use Rawphp\Capabilities\Tests\Fixtures\ArtisanCommandHarness;
use Rawphp\Capabilities\Tests\Fixtures\BootHelpers;
use Rawphp\Capabilities\Tests\Fixtures\FakeProviderApp;

function l014FakeSchedule(): object
{
    return new class
    {
        /** @var list<array{command: string, cron: ?string, without_overlapping: bool}> */
        public array $entries = [];

        public function command(string $command): object
        {
            $this->entries[] = ['command' => $command, 'cron' => null, 'without_overlapping' => false];
            $i = array_key_last($this->entries);
            $entries = &$this->entries;

            return new class($entries, $i)
            {
                public function __construct(private array &$entries, private int $i) {}

                public function cron(string $expression): self
                {
                    $this->entries[$this->i]['cron'] = $expression;

                    return $this;
                }

                public function withoutOverlapping(int $minutes = 1440): self
                {
                    $this->entries[$this->i]['without_overlapping'] = true;

                    return $this;
                }
            };
        }
    };
}

it('plans the sweep command from approval config when deferred and resume.enabled', function () {
    $plan = ResumeSchedulePlan::fromConfig(['execution' => 'deferred', 'resume' => ['enabled' => true, 'every_seconds' => 120]]);

    expect($plan)->toBe(['command' => ResumeApprovalsCommand::SIGNATURE_NAME, 'cron' => '*/2 * * * *']);
});

it('plans nothing for atomic execution or when resume is disabled', function () {
    expect(ResumeSchedulePlan::fromConfig(['execution' => 'atomic']))->toBeNull()
        ->and(ResumeSchedulePlan::fromConfig(['execution' => 'deferred', 'resume' => ['enabled' => false]]))->toBeNull();
});

it('maps every_seconds onto a cron expression with minute granularity', function (int $seconds, string $cron) {
    expect(ResumeSchedulePlan::cronExpression($seconds))->toBe($cron);
})->with([
    'sub-minute runs every minute' => [15, '* * * * *'],
    'default 60' => [60, '* * * * *'],
    'five minutes' => [300, '*/5 * * * *'],
    'rounds down to whole minutes' => [150, '*/2 * * * *'],
    'an hour or more is hourly' => [7200, '0 * * * *'],
]);

it('applies the plan to a scheduler as a non-overlapping command', function () {
    $schedule = l014FakeSchedule();

    ResumeSchedulePlan::apply($schedule, ['command' => 'capabilities:approvals-resume', 'cron' => '*/5 * * * *']);

    expect($schedule->entries)->toBe([[
        'command' => 'capabilities:approvals-resume',
        'cron' => '*/5 * * * *',
        'without_overlapping' => true,
    ]]);
});

it('registers capabilities:approvals-resume as an ops Artisan command', function () {
    $rows = ArtisanCommandTable::commands(['enabled' => true]);
    $resume = array_values(array_filter($rows, fn (array $r) => $r['key'] === 'approvals-resume'))[0] ?? null;

    expect($resume)->not->toBeNull()
        ->and($resume['signature'])->toStartWith('capabilities:approvals-resume')
        ->and($resume['class'])->toBe(ResumeApprovalsCommand::class)
        ->and($resume['role'])->toBe(ArtisanCommandTable::ROLE)
        ->and(is_subclass_of(ResumeApprovalsCommand::class, Command::class))->toBeTrue()
        ->and(ArtisanCommandTable::commands(['enabled' => false]))->toBe([]);
});

it('the command sweeps stuck approved rows through ApprovalManager::resume (scheduled path respects grace)', function () {
    $h = ApprovalHelpers::withPending(['grace_seconds' => 30]);
    $id = (string) $h['row']['id'];
    $h['store']->update($id, ['status' => 'approved', 'approved_at' => $h['clock']->now()->modify('-120 seconds')->format(DATE_ATOM), 'execution_lease_until' => null]);

    $summary = ResumeApprovalsCommand::sweep($h['manager'], id: null, force: false);

    expect($summary)->toBe(['resumed' => 1, 'skipped' => 0, 'failed' => 0])
        ->and($h['runCount']->value)->toBe(1)
        ->and($h['store']->find($id)['status'])->toBe('executed');
});

it('the command --force repairs one row inside grace (operator path) and reports skips otherwise', function () {
    $h = ApprovalHelpers::withPending(['grace_seconds' => 30]);
    $id = (string) $h['row']['id'];
    $h['store']->update($id, ['status' => 'approved', 'approved_at' => $h['clock']->now()->format(DATE_ATOM), 'execution_lease_until' => null]);

    $skipped = ResumeApprovalsCommand::sweep($h['manager'], id: $id, force: false);
    $forced = ResumeApprovalsCommand::sweep($h['manager'], id: $id, force: true);

    expect($skipped)->toBe(['resumed' => 0, 'skipped' => 1, 'failed' => 0])
        ->and($forced)->toBe(['resumed' => 1, 'skipped' => 0, 'failed' => 0])
        ->and($h['runCount']->value)->toBe(1);
});

it('the command prints the sweep summary for one --id and exits 0', function () {
    $h = ApprovalHelpers::withPending(['grace_seconds' => 30]);
    $id = (string) $h['row']['id'];
    $h['store']->update($id, ['status' => 'approved', 'approved_at' => $h['clock']->now()->format(DATE_ATOM), 'execution_lease_until' => null]);

    $inGrace = ArtisanCommandHarness::run(new ResumeApprovalsCommand($h['manager']), ['--id' => $id]);
    $forced = ArtisanCommandHarness::run(new ResumeApprovalsCommand($h['manager']), ['--id' => $id, '--force' => true]);

    expect($inGrace)->toBe(['exit' => 0, 'output' => "resumed=0 skipped=1 failed=0\n"])
        ->and($forced)->toBe(['exit' => 0, 'output' => "resumed=1 skipped=0 failed=0\n"])
        ->and($h['store']->find($id)['status'])->toBe('executed');
});

it('the command resolves ApprovalManager from the container and fails closed when it cannot', function () {
    $h = ApprovalHelpers::withPending();

    $bound = ArtisanCommandHarness::run(new ResumeApprovalsCommand, [], [ApprovalManager::class => $h['manager']]);
    $unbound = ArtisanCommandHarness::run(new ResumeApprovalsCommand, [], [
        ApprovalManager::class => static fn () => throw new RuntimeException('no store'),
    ]);

    expect($bound)->toBe(['exit' => 0, 'output' => "resumed=0 skipped=0 failed=0\n"])
        ->and($unbound['exit'])->toBe(1)
        ->and($unbound['output'])->toContain('ApprovalManager is not bound.');
});

it('provider: boot registers the sweep on the console Schedule when deferred + enabled, nothing otherwise', function () {
    $config = BootHelpers::config(['approval' => ['store' => 'memory', 'resume' => ['every_seconds' => 300]], 'idempotency' => ['driver' => 'memory'], 'audit' => ['driver' => 'memory']]);

    $on = FakeProviderApp::registered($config);
    $plan = $on->provider->bootResumeSchedule();
    $schedule = l014FakeSchedule();
    foreach ($on->afterResolving[Schedule::class] ?? [] as $cb) {
        $cb($schedule, $on);
    }

    $off = FakeProviderApp::registered(array_replace_recursive($config, ['approval' => ['execution' => 'atomic']]));

    expect($plan)->toBe(['command' => 'capabilities:approvals-resume', 'cron' => '*/5 * * * *'])
        ->and($schedule->entries)->toHaveCount(1)
        ->and($schedule->entries[0]['cron'])->toBe('*/5 * * * *')
        ->and($off->provider->bootResumeSchedule())->toBeNull()
        ->and($off->afterResolving)->toBe([]);
});

it('resume config surfaces on the manager exactly as the plan reads it', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'every_seconds' => 90]);
    /** @var ApprovalManager $m */
    $m = $h['manager'];

    expect(ResumeSchedulePlan::fromConfig($m->config()))->toBe(['command' => 'capabilities:approvals-resume', 'cron' => '* * * * *']);
});
