<?php

declare(strict_types=1);

use Rawphp\Capabilities\Approval\ApprovalManager;
use Rawphp\Capabilities\Tests\Fixtures\ApprovalHelpers;

it('happy: execution deferred enables resume scheduling [P2-004]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'resume_enabled' => true]);
    expect($h['manager']->isDeferred())->toBeTrue()->and($h['manager']->resumeEnabled())->toBeTrue();
});

it('happy: execution atomic disables resume necessity [P2-004]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'atomic']);
    expect($h['manager']->isAtomic())->toBeTrue()->and($h['manager']->resumeEnabled())->toBeFalse();
});

it('fail: execution invalid value fails config validation [D-006]', function () {
    expect(fn () => ApprovalManager::validateConfig(['execution' => 'nope']))->toThrow(InvalidArgumentException::class);
});

it('edge: resume.enabled false does not schedule when deferred [P2-004]', function () {
    $h = ApprovalHelpers::harness(['execution' => 'deferred', 'resume_enabled' => false]);
    expect($h['manager']->resumeEnabled())->toBeFalse();
});
