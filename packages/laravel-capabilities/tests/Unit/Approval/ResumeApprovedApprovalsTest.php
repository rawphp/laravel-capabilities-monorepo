<?php

declare(strict_types=1);

// ResumeApprovedApprovals job surface.

use Rawphp\Capabilities\Approval\ApprovalManager;
use Rawphp\Capabilities\Approval\ResumeApprovedApprovals;
use Rawphp\Capabilities\Support\FixedClock;

it('ResumeApprovedApprovals exposes its manager, schedule, and artisan surface', function () {
    $clock = new FixedClock(new DateTimeImmutable('2026-01-01T00:00:00Z'));
    $manager = ApprovalManager::inMemory($clock);

    $resume = new ResumeApprovedApprovals($manager);
    expect($resume->manager())->toBe($manager)
        ->and($resume->shouldSchedule())->toBeBool()
        ->and($resume->everySeconds())->toBeInt()
        ->and($resume->handle())->toBeArray()
        ->and($resume->artisan())->toBeArray();
});
