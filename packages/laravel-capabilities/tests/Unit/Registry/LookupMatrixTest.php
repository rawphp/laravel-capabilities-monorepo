<?php

declare(strict_types=1);

use Rawphp\Capabilities\Tests\Fixtures\PipelineHelpers;

it('fail: unknown capability via agent is not_found [PIPE-004]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke('no-such-cap', PipelineHelpers::validInput(), PipelineHelpers::options('agent'));
    expect($result->errorCode())->toBe('not_found')->and($h['runCount']->value)->toBe(0);
});

it('fail: known capability wrong surface via agent not invokable as that surface [PIPE-005]', function () {
    $h = PipelineHelpers::harness([
        'allowSystemCallers' => true,
        'cap_surfaces' => ['http'], // only http
        'name' => 'surface-narrow-agent',
    ]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('agent'));
    if ('agent' === 'http') {
        // for http, make surfaces only agent so http is wrong
        $h = PipelineHelpers::harness([
            'allowSystemCallers' => true,
            'cap_surfaces' => ['agent'],
            'name' => 'surface-narrow-http-x',
        ]);
        $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http'));
    }
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')->and($h['runCount']->value)->toBe(0);
});

it('happy: known capability correct surface via agent invokable [PIPE-003]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('agent'));
    expect($result->isOk())->toBeTrue();
});

it('fail: unknown capability via mcp is not_found [PIPE-004]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke('no-such-cap', PipelineHelpers::validInput(), PipelineHelpers::options('mcp'));
    expect($result->errorCode())->toBe('not_found')->and($h['runCount']->value)->toBe(0);
});

it('fail: known capability wrong surface via mcp not invokable as that surface [PIPE-005]', function () {
    $h = PipelineHelpers::harness([
        'allowSystemCallers' => true,
        'cap_surfaces' => ['http'], // only http
        'name' => 'surface-narrow-mcp',
    ]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('mcp'));
    if ('mcp' === 'http') {
        // for http, make surfaces only agent so http is wrong
        $h = PipelineHelpers::harness([
            'allowSystemCallers' => true,
            'cap_surfaces' => ['agent'],
            'name' => 'surface-narrow-http-x',
        ]);
        $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http'));
    }
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')->and($h['runCount']->value)->toBe(0);
});

it('happy: known capability correct surface via mcp invokable [PIPE-003]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('mcp'));
    expect($result->isOk())->toBeTrue();
});

it('fail: unknown capability via http is not_found [PIPE-004]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke('no-such-cap', PipelineHelpers::validInput(), PipelineHelpers::options('http'));
    expect($result->errorCode())->toBe('not_found')->and($h['runCount']->value)->toBe(0);
});

it('fail: known capability wrong surface via http not invokable as that surface [PIPE-005]', function () {
    $h = PipelineHelpers::harness([
        'allowSystemCallers' => true,
        'cap_surfaces' => ['http'], // only http
        'name' => 'surface-narrow-http',
    ]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http'));
    if ('http' === 'http') {
        // for http, make surfaces only agent so http is wrong
        $h = PipelineHelpers::harness([
            'allowSystemCallers' => true,
            'cap_surfaces' => ['agent'],
            'name' => 'surface-narrow-http-x',
        ]);
        $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http'));
    }
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')->and($h['runCount']->value)->toBe(0);
});

it('happy: known capability correct surface via http invokable [PIPE-003]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http'));
    expect($result->isOk())->toBeTrue();
});

it('fail: unknown capability via cli is not_found [PIPE-004]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke('no-such-cap', PipelineHelpers::validInput(), PipelineHelpers::options('cli'));
    expect($result->errorCode())->toBe('not_found')->and($h['runCount']->value)->toBe(0);
});

it('fail: known capability wrong surface via cli not invokable as that surface [PIPE-005]', function () {
    $h = PipelineHelpers::harness([
        'allowSystemCallers' => true,
        'cap_surfaces' => ['http'], // only http
        'name' => 'surface-narrow-cli',
    ]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('cli'));
    if ('cli' === 'http') {
        // for http, make surfaces only agent so http is wrong
        $h = PipelineHelpers::harness([
            'allowSystemCallers' => true,
            'cap_surfaces' => ['agent'],
            'name' => 'surface-narrow-http-x',
        ]);
        $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http'));
    }
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')->and($h['runCount']->value)->toBe(0);
});

it('happy: known capability correct surface via cli invokable [PIPE-003]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('cli'));
    expect($result->isOk())->toBeTrue();
});

it('fail: unknown capability via job is not_found [PIPE-004]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke('no-such-cap', PipelineHelpers::validInput(), PipelineHelpers::options('job'));
    expect($result->errorCode())->toBe('not_found')->and($h['runCount']->value)->toBe(0);
});

it('fail: known capability wrong surface via job not invokable as that surface [PIPE-005]', function () {
    $h = PipelineHelpers::harness([
        'allowSystemCallers' => true,
        'cap_surfaces' => ['http'], // only http
        'name' => 'surface-narrow-job',
    ]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('job'));
    if ('job' === 'http') {
        // for http, make surfaces only agent so http is wrong
        $h = PipelineHelpers::harness([
            'allowSystemCallers' => true,
            'cap_surfaces' => ['agent'],
            'name' => 'surface-narrow-http-x',
        ]);
        $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http'));
    }
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')->and($h['runCount']->value)->toBe(0);
});

it('happy: known capability correct surface via job invokable [PIPE-003]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('job'));
    expect($result->isOk())->toBeTrue();
});
