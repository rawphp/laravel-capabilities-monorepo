<?php

declare(strict_types=1);

use Rawphp\Capabilities\Tests\Fixtures\PipelineHelpers;

it('happy: needsApproval true for agent stores pending [D-006]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('agent', ['needs_approval' => true]));
    if (true) {
        if (str_contains('stores pending [D-006]', 'stores pending')) {
            expect($result->isApprovalRequired())->toBeTrue();
            $row = $h['fakes']->approvals->find((string) $result->approvalId());
            expect($row)->not->toBeNull()->and($row['status'])->toBe('pending');
        } elseif (str_contains('stores pending [D-006]', 'does not run')) {
            expect($h['runCount']->value)->toBe(0);
        } else {
            expect($result->isApprovalRequired())->toBeTrue();
        }
    } else {
        expect($result->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    }
});

it('fail: needsApproval true for agent does not run [D-006]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('agent', ['needs_approval' => true]));
    if (true) {
        if (str_contains('does not run [D-006]', 'stores pending')) {
            expect($result->isApprovalRequired())->toBeTrue();
            $row = $h['fakes']->approvals->find((string) $result->approvalId());
            expect($row)->not->toBeNull()->and($row['status'])->toBe('pending');
        } elseif (str_contains('does not run [D-006]', 'does not run')) {
            expect($h['runCount']->value)->toBe(0);
        } else {
            expect($result->isApprovalRequired())->toBeTrue();
        }
    } else {
        expect($result->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    }
});

it('happy: needsApproval true for agent returns approval_required [D-006]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('agent', ['needs_approval' => true]));
    if (true) {
        if (str_contains('returns approval_required [D-006]', 'stores pending')) {
            expect($result->isApprovalRequired())->toBeTrue();
            $row = $h['fakes']->approvals->find((string) $result->approvalId());
            expect($row)->not->toBeNull()->and($row['status'])->toBe('pending');
        } elseif (str_contains('returns approval_required [D-006]', 'does not run')) {
            expect($h['runCount']->value)->toBe(0);
        } else {
            expect($result->isApprovalRequired())->toBeTrue();
        }
    } else {
        expect($result->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    }
});

it('happy: needsApproval false for agent continues to rate limit and run [D-006]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('agent', ['needs_approval' => false]));
    if (false) {
        if (str_contains('continues to rate limit and run [D-006]', 'stores pending')) {
            expect($result->isApprovalRequired())->toBeTrue();
            $row = $h['fakes']->approvals->find((string) $result->approvalId());
            expect($row)->not->toBeNull()->and($row['status'])->toBe('pending');
        } elseif (str_contains('continues to rate limit and run [D-006]', 'does not run')) {
            expect($h['runCount']->value)->toBe(0);
        } else {
            expect($result->isApprovalRequired())->toBeTrue();
        }
    } else {
        expect($result->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    }
});

it('happy: needsApproval true for mcp stores pending [D-006]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('mcp', ['needs_approval' => true]));
    if (true) {
        if (str_contains('stores pending [D-006]', 'stores pending')) {
            expect($result->isApprovalRequired())->toBeTrue();
            $row = $h['fakes']->approvals->find((string) $result->approvalId());
            expect($row)->not->toBeNull()->and($row['status'])->toBe('pending');
        } elseif (str_contains('stores pending [D-006]', 'does not run')) {
            expect($h['runCount']->value)->toBe(0);
        } else {
            expect($result->isApprovalRequired())->toBeTrue();
        }
    } else {
        expect($result->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    }
});

it('fail: needsApproval true for mcp does not run [D-006]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('mcp', ['needs_approval' => true]));
    if (true) {
        if (str_contains('does not run [D-006]', 'stores pending')) {
            expect($result->isApprovalRequired())->toBeTrue();
            $row = $h['fakes']->approvals->find((string) $result->approvalId());
            expect($row)->not->toBeNull()->and($row['status'])->toBe('pending');
        } elseif (str_contains('does not run [D-006]', 'does not run')) {
            expect($h['runCount']->value)->toBe(0);
        } else {
            expect($result->isApprovalRequired())->toBeTrue();
        }
    } else {
        expect($result->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    }
});

it('happy: needsApproval true for mcp returns approval_required [D-006]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('mcp', ['needs_approval' => true]));
    if (true) {
        if (str_contains('returns approval_required [D-006]', 'stores pending')) {
            expect($result->isApprovalRequired())->toBeTrue();
            $row = $h['fakes']->approvals->find((string) $result->approvalId());
            expect($row)->not->toBeNull()->and($row['status'])->toBe('pending');
        } elseif (str_contains('returns approval_required [D-006]', 'does not run')) {
            expect($h['runCount']->value)->toBe(0);
        } else {
            expect($result->isApprovalRequired())->toBeTrue();
        }
    } else {
        expect($result->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    }
});

it('happy: needsApproval false for mcp continues to rate limit and run [D-006]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('mcp', ['needs_approval' => false]));
    if (false) {
        if (str_contains('continues to rate limit and run [D-006]', 'stores pending')) {
            expect($result->isApprovalRequired())->toBeTrue();
            $row = $h['fakes']->approvals->find((string) $result->approvalId());
            expect($row)->not->toBeNull()->and($row['status'])->toBe('pending');
        } elseif (str_contains('continues to rate limit and run [D-006]', 'does not run')) {
            expect($h['runCount']->value)->toBe(0);
        } else {
            expect($result->isApprovalRequired())->toBeTrue();
        }
    } else {
        expect($result->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    }
});

it('happy: needsApproval true for http stores pending [D-006]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http', ['needs_approval' => true]));
    if (true) {
        if (str_contains('stores pending [D-006]', 'stores pending')) {
            expect($result->isApprovalRequired())->toBeTrue();
            $row = $h['fakes']->approvals->find((string) $result->approvalId());
            expect($row)->not->toBeNull()->and($row['status'])->toBe('pending');
        } elseif (str_contains('stores pending [D-006]', 'does not run')) {
            expect($h['runCount']->value)->toBe(0);
        } else {
            expect($result->isApprovalRequired())->toBeTrue();
        }
    } else {
        expect($result->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    }
});

it('fail: needsApproval true for http does not run [D-006]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http', ['needs_approval' => true]));
    if (true) {
        if (str_contains('does not run [D-006]', 'stores pending')) {
            expect($result->isApprovalRequired())->toBeTrue();
            $row = $h['fakes']->approvals->find((string) $result->approvalId());
            expect($row)->not->toBeNull()->and($row['status'])->toBe('pending');
        } elseif (str_contains('does not run [D-006]', 'does not run')) {
            expect($h['runCount']->value)->toBe(0);
        } else {
            expect($result->isApprovalRequired())->toBeTrue();
        }
    } else {
        expect($result->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    }
});

it('happy: needsApproval true for http returns approval_required [D-006]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http', ['needs_approval' => true]));
    if (true) {
        if (str_contains('returns approval_required [D-006]', 'stores pending')) {
            expect($result->isApprovalRequired())->toBeTrue();
            $row = $h['fakes']->approvals->find((string) $result->approvalId());
            expect($row)->not->toBeNull()->and($row['status'])->toBe('pending');
        } elseif (str_contains('returns approval_required [D-006]', 'does not run')) {
            expect($h['runCount']->value)->toBe(0);
        } else {
            expect($result->isApprovalRequired())->toBeTrue();
        }
    } else {
        expect($result->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    }
});

it('happy: needsApproval false for http continues to rate limit and run [D-006]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http', ['needs_approval' => false]));
    if (false) {
        if (str_contains('continues to rate limit and run [D-006]', 'stores pending')) {
            expect($result->isApprovalRequired())->toBeTrue();
            $row = $h['fakes']->approvals->find((string) $result->approvalId());
            expect($row)->not->toBeNull()->and($row['status'])->toBe('pending');
        } elseif (str_contains('continues to rate limit and run [D-006]', 'does not run')) {
            expect($h['runCount']->value)->toBe(0);
        } else {
            expect($result->isApprovalRequired())->toBeTrue();
        }
    } else {
        expect($result->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    }
});

it('happy: needsApproval true for cli stores pending [D-006]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('cli', ['needs_approval' => true]));
    if (true) {
        if (str_contains('stores pending [D-006]', 'stores pending')) {
            expect($result->isApprovalRequired())->toBeTrue();
            $row = $h['fakes']->approvals->find((string) $result->approvalId());
            expect($row)->not->toBeNull()->and($row['status'])->toBe('pending');
        } elseif (str_contains('stores pending [D-006]', 'does not run')) {
            expect($h['runCount']->value)->toBe(0);
        } else {
            expect($result->isApprovalRequired())->toBeTrue();
        }
    } else {
        expect($result->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    }
});

it('fail: needsApproval true for cli does not run [D-006]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('cli', ['needs_approval' => true]));
    if (true) {
        if (str_contains('does not run [D-006]', 'stores pending')) {
            expect($result->isApprovalRequired())->toBeTrue();
            $row = $h['fakes']->approvals->find((string) $result->approvalId());
            expect($row)->not->toBeNull()->and($row['status'])->toBe('pending');
        } elseif (str_contains('does not run [D-006]', 'does not run')) {
            expect($h['runCount']->value)->toBe(0);
        } else {
            expect($result->isApprovalRequired())->toBeTrue();
        }
    } else {
        expect($result->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    }
});

it('happy: needsApproval true for cli returns approval_required [D-006]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('cli', ['needs_approval' => true]));
    if (true) {
        if (str_contains('returns approval_required [D-006]', 'stores pending')) {
            expect($result->isApprovalRequired())->toBeTrue();
            $row = $h['fakes']->approvals->find((string) $result->approvalId());
            expect($row)->not->toBeNull()->and($row['status'])->toBe('pending');
        } elseif (str_contains('returns approval_required [D-006]', 'does not run')) {
            expect($h['runCount']->value)->toBe(0);
        } else {
            expect($result->isApprovalRequired())->toBeTrue();
        }
    } else {
        expect($result->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    }
});

it('happy: needsApproval false for cli continues to rate limit and run [D-006]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('cli', ['needs_approval' => false]));
    if (false) {
        if (str_contains('continues to rate limit and run [D-006]', 'stores pending')) {
            expect($result->isApprovalRequired())->toBeTrue();
            $row = $h['fakes']->approvals->find((string) $result->approvalId());
            expect($row)->not->toBeNull()->and($row['status'])->toBe('pending');
        } elseif (str_contains('continues to rate limit and run [D-006]', 'does not run')) {
            expect($h['runCount']->value)->toBe(0);
        } else {
            expect($result->isApprovalRequired())->toBeTrue();
        }
    } else {
        expect($result->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    }
});

it('happy: needsApproval true for job stores pending [D-006]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('job', ['needs_approval' => true]));
    if (true) {
        if (str_contains('stores pending [D-006]', 'stores pending')) {
            expect($result->isApprovalRequired())->toBeTrue();
            $row = $h['fakes']->approvals->find((string) $result->approvalId());
            expect($row)->not->toBeNull()->and($row['status'])->toBe('pending');
        } elseif (str_contains('stores pending [D-006]', 'does not run')) {
            expect($h['runCount']->value)->toBe(0);
        } else {
            expect($result->isApprovalRequired())->toBeTrue();
        }
    } else {
        expect($result->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    }
});

it('fail: needsApproval true for job does not run [D-006]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('job', ['needs_approval' => true]));
    if (true) {
        if (str_contains('does not run [D-006]', 'stores pending')) {
            expect($result->isApprovalRequired())->toBeTrue();
            $row = $h['fakes']->approvals->find((string) $result->approvalId());
            expect($row)->not->toBeNull()->and($row['status'])->toBe('pending');
        } elseif (str_contains('does not run [D-006]', 'does not run')) {
            expect($h['runCount']->value)->toBe(0);
        } else {
            expect($result->isApprovalRequired())->toBeTrue();
        }
    } else {
        expect($result->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    }
});

it('happy: needsApproval true for job returns approval_required [D-006]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('job', ['needs_approval' => true]));
    if (true) {
        if (str_contains('returns approval_required [D-006]', 'stores pending')) {
            expect($result->isApprovalRequired())->toBeTrue();
            $row = $h['fakes']->approvals->find((string) $result->approvalId());
            expect($row)->not->toBeNull()->and($row['status'])->toBe('pending');
        } elseif (str_contains('returns approval_required [D-006]', 'does not run')) {
            expect($h['runCount']->value)->toBe(0);
        } else {
            expect($result->isApprovalRequired())->toBeTrue();
        }
    } else {
        expect($result->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    }
});

it('happy: needsApproval false for job continues to rate limit and run [D-006]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('job', ['needs_approval' => false]));
    if (false) {
        if (str_contains('continues to rate limit and run [D-006]', 'stores pending')) {
            expect($result->isApprovalRequired())->toBeTrue();
            $row = $h['fakes']->approvals->find((string) $result->approvalId());
            expect($row)->not->toBeNull()->and($row['status'])->toBe('pending');
        } elseif (str_contains('continues to rate limit and run [D-006]', 'does not run')) {
            expect($h['runCount']->value)->toBe(0);
        } else {
            expect($result->isApprovalRequired())->toBeTrue();
        }
    } else {
        expect($result->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    }
});
