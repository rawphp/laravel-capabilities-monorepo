<?php

declare(strict_types=1);

use Rawphp\Capabilities\Pipeline\PipelineStages;
use Rawphp\Capabilities\Tests\Fixtures\PipelineHelpers;

it('edge: authorize allow continues pipeline when actor=user caller=agent authorize=True [PIPE-001]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => true]);
    $extra = [];
    if ('user' === 'system') {
        $extra['actor'] = PipelineHelpers::systemActor('billing-worker');
        if ('agent' === 'job') {
            $extra['job'] = ['tenant_id' => 't-1'];
        }
    }
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('agent', $extra));
    expect($result->isOk())->toBeTrue()->and($h['registry']->lastStages())->toContain(PipelineStages::AUTHORIZE);
});

it('fail: authorize deny stops before run when actor=user caller=agent authorize=False [PIPE-001]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => false]);
    $extra = [];
    if ('user' === 'system') {
        $extra['actor'] = PipelineHelpers::systemActor('billing-worker');
        if ('agent' === 'job') {
            $extra['job'] = ['tenant_id' => 't-1'];
        }
    }
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('agent', $extra));
    expect($result->errorCode())->toBe('forbidden')->and($h['runCount']->value)->toBe(0);
});

it('fail: authorize deny no domain side effects when actor=user caller=agent authorize=False [PIPE-001]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => false]);
    $extra = [];
    if ('user' === 'system') {
        $extra['actor'] = PipelineHelpers::systemActor('billing-worker');
    }
    $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('agent', $extra));
    expect($h['runCount']->sideEffect)->toBeFalse();
});

it('happy: authorize deny may audit when actor=user caller=agent authorize=False [D-010]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => false]);
    $extra = [];
    if ('user' === 'system') {
        $extra['actor'] = PipelineHelpers::systemActor('billing-worker');
    }
    $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('agent', $extra));
    expect($h['fakes']->audit->all())->not->toBeEmpty();
});

it('edge: authorize allow continues pipeline when actor=user caller=mcp authorize=True [PIPE-001]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => true]);
    $extra = [];
    if ('user' === 'system') {
        $extra['actor'] = PipelineHelpers::systemActor('billing-worker');
        if ('mcp' === 'job') {
            $extra['job'] = ['tenant_id' => 't-1'];
        }
    }
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('mcp', $extra));
    expect($result->isOk())->toBeTrue()->and($h['registry']->lastStages())->toContain(PipelineStages::AUTHORIZE);
});

it('fail: authorize deny stops before run when actor=user caller=mcp authorize=False [PIPE-001]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => false]);
    $extra = [];
    if ('user' === 'system') {
        $extra['actor'] = PipelineHelpers::systemActor('billing-worker');
        if ('mcp' === 'job') {
            $extra['job'] = ['tenant_id' => 't-1'];
        }
    }
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('mcp', $extra));
    expect($result->errorCode())->toBe('forbidden')->and($h['runCount']->value)->toBe(0);
});

it('fail: authorize deny no domain side effects when actor=user caller=mcp authorize=False [PIPE-001]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => false]);
    $extra = [];
    if ('user' === 'system') {
        $extra['actor'] = PipelineHelpers::systemActor('billing-worker');
    }
    $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('mcp', $extra));
    expect($h['runCount']->sideEffect)->toBeFalse();
});

it('happy: authorize deny may audit when actor=user caller=mcp authorize=False [D-010]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => false]);
    $extra = [];
    if ('user' === 'system') {
        $extra['actor'] = PipelineHelpers::systemActor('billing-worker');
    }
    $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('mcp', $extra));
    expect($h['fakes']->audit->all())->not->toBeEmpty();
});

it('edge: authorize allow continues pipeline when actor=user caller=http authorize=True [PIPE-001]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => true]);
    $extra = [];
    if ('user' === 'system') {
        $extra['actor'] = PipelineHelpers::systemActor('billing-worker');
        if ('http' === 'job') {
            $extra['job'] = ['tenant_id' => 't-1'];
        }
    }
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http', $extra));
    expect($result->isOk())->toBeTrue()->and($h['registry']->lastStages())->toContain(PipelineStages::AUTHORIZE);
});

it('fail: authorize deny stops before run when actor=user caller=http authorize=False [PIPE-001]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => false]);
    $extra = [];
    if ('user' === 'system') {
        $extra['actor'] = PipelineHelpers::systemActor('billing-worker');
        if ('http' === 'job') {
            $extra['job'] = ['tenant_id' => 't-1'];
        }
    }
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http', $extra));
    expect($result->errorCode())->toBe('forbidden')->and($h['runCount']->value)->toBe(0);
});

it('fail: authorize deny no domain side effects when actor=user caller=http authorize=False [PIPE-001]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => false]);
    $extra = [];
    if ('user' === 'system') {
        $extra['actor'] = PipelineHelpers::systemActor('billing-worker');
    }
    $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http', $extra));
    expect($h['runCount']->sideEffect)->toBeFalse();
});

it('happy: authorize deny may audit when actor=user caller=http authorize=False [D-010]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => false]);
    $extra = [];
    if ('user' === 'system') {
        $extra['actor'] = PipelineHelpers::systemActor('billing-worker');
    }
    $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http', $extra));
    expect($h['fakes']->audit->all())->not->toBeEmpty();
});

it('edge: authorize allow continues pipeline when actor=user caller=cli authorize=True [PIPE-001]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => true]);
    $extra = [];
    if ('user' === 'system') {
        $extra['actor'] = PipelineHelpers::systemActor('billing-worker');
        if ('cli' === 'job') {
            $extra['job'] = ['tenant_id' => 't-1'];
        }
    }
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('cli', $extra));
    expect($result->isOk())->toBeTrue()->and($h['registry']->lastStages())->toContain(PipelineStages::AUTHORIZE);
});

it('fail: authorize deny stops before run when actor=user caller=cli authorize=False [PIPE-001]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => false]);
    $extra = [];
    if ('user' === 'system') {
        $extra['actor'] = PipelineHelpers::systemActor('billing-worker');
        if ('cli' === 'job') {
            $extra['job'] = ['tenant_id' => 't-1'];
        }
    }
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('cli', $extra));
    expect($result->errorCode())->toBe('forbidden')->and($h['runCount']->value)->toBe(0);
});

it('fail: authorize deny no domain side effects when actor=user caller=cli authorize=False [PIPE-001]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => false]);
    $extra = [];
    if ('user' === 'system') {
        $extra['actor'] = PipelineHelpers::systemActor('billing-worker');
    }
    $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('cli', $extra));
    expect($h['runCount']->sideEffect)->toBeFalse();
});

it('happy: authorize deny may audit when actor=user caller=cli authorize=False [D-010]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => false]);
    $extra = [];
    if ('user' === 'system') {
        $extra['actor'] = PipelineHelpers::systemActor('billing-worker');
    }
    $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('cli', $extra));
    expect($h['fakes']->audit->all())->not->toBeEmpty();
});

it('edge: authorize allow continues pipeline when actor=user caller=job authorize=True [PIPE-001]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => true]);
    $extra = [];
    if ('user' === 'system') {
        $extra['actor'] = PipelineHelpers::systemActor('billing-worker');
        if ('job' === 'job') {
            $extra['job'] = ['tenant_id' => 't-1'];
        }
    }
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('job', $extra));
    expect($result->isOk())->toBeTrue()->and($h['registry']->lastStages())->toContain(PipelineStages::AUTHORIZE);
});

it('fail: authorize deny stops before run when actor=user caller=job authorize=False [PIPE-001]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => false]);
    $extra = [];
    if ('user' === 'system') {
        $extra['actor'] = PipelineHelpers::systemActor('billing-worker');
        if ('job' === 'job') {
            $extra['job'] = ['tenant_id' => 't-1'];
        }
    }
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('job', $extra));
    expect($result->errorCode())->toBe('forbidden')->and($h['runCount']->value)->toBe(0);
});

it('fail: authorize deny no domain side effects when actor=user caller=job authorize=False [PIPE-001]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => false]);
    $extra = [];
    if ('user' === 'system') {
        $extra['actor'] = PipelineHelpers::systemActor('billing-worker');
    }
    $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('job', $extra));
    expect($h['runCount']->sideEffect)->toBeFalse();
});

it('happy: authorize deny may audit when actor=user caller=job authorize=False [D-010]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => false]);
    $extra = [];
    if ('user' === 'system') {
        $extra['actor'] = PipelineHelpers::systemActor('billing-worker');
    }
    $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('job', $extra));
    expect($h['fakes']->audit->all())->not->toBeEmpty();
});

it('edge: authorize allow continues pipeline when actor=system caller=agent authorize=True [PIPE-001]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => true]);
    $extra = [];
    if ('system' === 'system') {
        $extra['actor'] = PipelineHelpers::systemActor('billing-worker');
        if ('agent' === 'job') {
            $extra['job'] = ['tenant_id' => 't-1'];
        }
    }
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('agent', $extra));
    expect($result->isOk())->toBeTrue()->and($h['registry']->lastStages())->toContain(PipelineStages::AUTHORIZE);
});

it('fail: authorize deny stops before run when actor=system caller=agent authorize=False [PIPE-001]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => false]);
    $extra = [];
    if ('system' === 'system') {
        $extra['actor'] = PipelineHelpers::systemActor('billing-worker');
        if ('agent' === 'job') {
            $extra['job'] = ['tenant_id' => 't-1'];
        }
    }
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('agent', $extra));
    expect($result->errorCode())->toBe('forbidden')->and($h['runCount']->value)->toBe(0);
});

it('fail: authorize deny no domain side effects when actor=system caller=agent authorize=False [PIPE-001]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => false]);
    $extra = [];
    if ('system' === 'system') {
        $extra['actor'] = PipelineHelpers::systemActor('billing-worker');
    }
    $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('agent', $extra));
    expect($h['runCount']->sideEffect)->toBeFalse();
});

it('happy: authorize deny may audit when actor=system caller=agent authorize=False [D-010]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => false]);
    $extra = [];
    if ('system' === 'system') {
        $extra['actor'] = PipelineHelpers::systemActor('billing-worker');
    }
    $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('agent', $extra));
    expect($h['fakes']->audit->all())->not->toBeEmpty();
});

it('edge: authorize allow continues pipeline when actor=system caller=mcp authorize=True [PIPE-001]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => true]);
    $extra = [];
    if ('system' === 'system') {
        $extra['actor'] = PipelineHelpers::systemActor('billing-worker');
        if ('mcp' === 'job') {
            $extra['job'] = ['tenant_id' => 't-1'];
        }
    }
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('mcp', $extra));
    expect($result->isOk())->toBeTrue()->and($h['registry']->lastStages())->toContain(PipelineStages::AUTHORIZE);
});

it('fail: authorize deny stops before run when actor=system caller=mcp authorize=False [PIPE-001]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => false]);
    $extra = [];
    if ('system' === 'system') {
        $extra['actor'] = PipelineHelpers::systemActor('billing-worker');
        if ('mcp' === 'job') {
            $extra['job'] = ['tenant_id' => 't-1'];
        }
    }
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('mcp', $extra));
    expect($result->errorCode())->toBe('forbidden')->and($h['runCount']->value)->toBe(0);
});

it('fail: authorize deny no domain side effects when actor=system caller=mcp authorize=False [PIPE-001]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => false]);
    $extra = [];
    if ('system' === 'system') {
        $extra['actor'] = PipelineHelpers::systemActor('billing-worker');
    }
    $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('mcp', $extra));
    expect($h['runCount']->sideEffect)->toBeFalse();
});

it('happy: authorize deny may audit when actor=system caller=mcp authorize=False [D-010]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => false]);
    $extra = [];
    if ('system' === 'system') {
        $extra['actor'] = PipelineHelpers::systemActor('billing-worker');
    }
    $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('mcp', $extra));
    expect($h['fakes']->audit->all())->not->toBeEmpty();
});

it('edge: authorize allow continues pipeline when actor=system caller=http authorize=True [PIPE-001]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => true]);
    $extra = [];
    if ('system' === 'system') {
        $extra['actor'] = PipelineHelpers::systemActor('billing-worker');
        if ('http' === 'job') {
            $extra['job'] = ['tenant_id' => 't-1'];
        }
    }
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http', $extra));
    expect($result->isOk())->toBeTrue()->and($h['registry']->lastStages())->toContain(PipelineStages::AUTHORIZE);
});

it('fail: authorize deny stops before run when actor=system caller=http authorize=False [PIPE-001]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => false]);
    $extra = [];
    if ('system' === 'system') {
        $extra['actor'] = PipelineHelpers::systemActor('billing-worker');
        if ('http' === 'job') {
            $extra['job'] = ['tenant_id' => 't-1'];
        }
    }
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http', $extra));
    expect($result->errorCode())->toBe('forbidden')->and($h['runCount']->value)->toBe(0);
});

it('fail: authorize deny no domain side effects when actor=system caller=http authorize=False [PIPE-001]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => false]);
    $extra = [];
    if ('system' === 'system') {
        $extra['actor'] = PipelineHelpers::systemActor('billing-worker');
    }
    $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http', $extra));
    expect($h['runCount']->sideEffect)->toBeFalse();
});

it('happy: authorize deny may audit when actor=system caller=http authorize=False [D-010]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => false]);
    $extra = [];
    if ('system' === 'system') {
        $extra['actor'] = PipelineHelpers::systemActor('billing-worker');
    }
    $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http', $extra));
    expect($h['fakes']->audit->all())->not->toBeEmpty();
});

it('edge: authorize allow continues pipeline when actor=system caller=cli authorize=True [PIPE-001]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => true]);
    $extra = [];
    if ('system' === 'system') {
        $extra['actor'] = PipelineHelpers::systemActor('billing-worker');
        if ('cli' === 'job') {
            $extra['job'] = ['tenant_id' => 't-1'];
        }
    }
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('cli', $extra));
    expect($result->isOk())->toBeTrue()->and($h['registry']->lastStages())->toContain(PipelineStages::AUTHORIZE);
});

it('fail: authorize deny stops before run when actor=system caller=cli authorize=False [PIPE-001]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => false]);
    $extra = [];
    if ('system' === 'system') {
        $extra['actor'] = PipelineHelpers::systemActor('billing-worker');
        if ('cli' === 'job') {
            $extra['job'] = ['tenant_id' => 't-1'];
        }
    }
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('cli', $extra));
    expect($result->errorCode())->toBe('forbidden')->and($h['runCount']->value)->toBe(0);
});

it('fail: authorize deny no domain side effects when actor=system caller=cli authorize=False [PIPE-001]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => false]);
    $extra = [];
    if ('system' === 'system') {
        $extra['actor'] = PipelineHelpers::systemActor('billing-worker');
    }
    $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('cli', $extra));
    expect($h['runCount']->sideEffect)->toBeFalse();
});

it('happy: authorize deny may audit when actor=system caller=cli authorize=False [D-010]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => false]);
    $extra = [];
    if ('system' === 'system') {
        $extra['actor'] = PipelineHelpers::systemActor('billing-worker');
    }
    $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('cli', $extra));
    expect($h['fakes']->audit->all())->not->toBeEmpty();
});

it('edge: authorize allow continues pipeline when actor=system caller=job authorize=True [PIPE-001]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => true]);
    $extra = [];
    if ('system' === 'system') {
        $extra['actor'] = PipelineHelpers::systemActor('billing-worker');
        if ('job' === 'job') {
            $extra['job'] = ['tenant_id' => 't-1'];
        }
    }
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('job', $extra));
    expect($result->isOk())->toBeTrue()->and($h['registry']->lastStages())->toContain(PipelineStages::AUTHORIZE);
});

it('fail: authorize deny stops before run when actor=system caller=job authorize=False [PIPE-001]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => false]);
    $extra = [];
    if ('system' === 'system') {
        $extra['actor'] = PipelineHelpers::systemActor('billing-worker');
        if ('job' === 'job') {
            $extra['job'] = ['tenant_id' => 't-1'];
        }
    }
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('job', $extra));
    expect($result->errorCode())->toBe('forbidden')->and($h['runCount']->value)->toBe(0);
});

it('fail: authorize deny no domain side effects when actor=system caller=job authorize=False [PIPE-001]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => false]);
    $extra = [];
    if ('system' === 'system') {
        $extra['actor'] = PipelineHelpers::systemActor('billing-worker');
    }
    $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('job', $extra));
    expect($h['runCount']->sideEffect)->toBeFalse();
});

it('happy: authorize deny may audit when actor=system caller=job authorize=False [D-010]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => false]);
    $extra = [];
    if ('system' === 'system') {
        $extra['actor'] = PipelineHelpers::systemActor('billing-worker');
    }
    $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('job', $extra));
    expect($h['fakes']->audit->all())->not->toBeEmpty();
});
