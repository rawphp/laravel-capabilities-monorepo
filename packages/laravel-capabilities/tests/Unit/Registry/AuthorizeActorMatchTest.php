<?php

declare(strict_types=1);

use Rawphp\Capabilities\Support\CapabilityContext;
use Rawphp\Capabilities\Support\SystemActor;
use Rawphp\Capabilities\Tests\Fixtures\PipelineHelpers;

it('happy: authorize can match User actor [D-002]', function () {
    $seen = null;
    $h = PipelineHelpers::harness([
        'allowSystemCallers' => true,
        'authorize_cb' => function ($input, $ctx) use (&$seen) {
            $seen = $ctx->actor();

            return true;
        },
    ]);
    $user = PipelineHelpers::userActor(99);
    $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http', ['actor' => $user]));
    expect($seen)->toBe($user);
});

it('happy: authorize can match SystemActor [D-002]', function () {
    $seen = null;
    $h = PipelineHelpers::harness([
        'allowSystemCallers' => true,
        'authorize_cb' => function ($input, $ctx) use (&$seen) {
            $seen = $ctx->actor();

            return true;
        },
    ]);
    $sys = PipelineHelpers::systemActor('billing-worker');
    $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('job', [
        'actor' => $sys,
        'job' => ['tenant_id' => 't-1'],
    ]));
    expect($seen)->toBeInstanceOf(SystemActor::class)->and($seen->name)->toBe('billing-worker');
});

it('fail: authorize default denies unknown actor kinds [D-002]', function () {
    $h = PipelineHelpers::harness([
        'allowSystemCallers' => true,
        'authorize_cb' => function ($input, $ctx) {
            $a = $ctx->actor();
            if ($a instanceof SystemActor) {
                return true;
            }
            if (is_object($a) && isset($a->id)) {
                return true;
            }

            return false;
        },
    ]);
    $weird = new class {};
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http', ['actor' => $weird]));
    expect($result->errorCode())->toBe('forbidden');
});

it('fail: authorize must not use is user null allow pattern [D-002]', function () {
    // Registry always builds a non-null actor into CapabilityContext; authorize never sees null user as allow.
    $h = PipelineHelpers::harness([
        'allowSystemCallers' => true,
        'authorize_cb' => function ($input, $ctx) {
            // Anti-pattern: if ($user === null) return true; — context always has actor
            expect($ctx->actor())->not->toBeNull();

            return true;
        },
    ]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http'));
    expect($result->isOk())->toBeTrue();
});

it('edge: authorize receives caller agent in context [CTX-001]', function () {
    $seen = null;
    $h = PipelineHelpers::harness([
        'allowSystemCallers' => true,
        'authorize_cb' => function ($input, $ctx) use (&$seen) {
            $seen = $ctx->caller();

            return true;
        },
    ]);
    $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('agent'));
    expect($seen)->toBe('agent');
});

it('edge: authorize receives caller mcp in context [CTX-001]', function () {
    $seen = null;
    $h = PipelineHelpers::harness([
        'allowSystemCallers' => true,
        'authorize_cb' => function ($input, $ctx) use (&$seen) {
            $seen = $ctx->caller();

            return true;
        },
    ]);
    $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('mcp'));
    expect($seen)->toBe('mcp');
});

it('edge: authorize receives caller http in context [CTX-001]', function () {
    $seen = null;
    $h = PipelineHelpers::harness([
        'allowSystemCallers' => true,
        'authorize_cb' => function ($input, $ctx) use (&$seen) {
            $seen = $ctx->caller();

            return true;
        },
    ]);
    $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http'));
    expect($seen)->toBe('http');
});

it('edge: authorize receives caller cli in context [CTX-001]', function () {
    $seen = null;
    $h = PipelineHelpers::harness([
        'allowSystemCallers' => true,
        'authorize_cb' => function ($input, $ctx) use (&$seen) {
            $seen = $ctx->caller();

            return true;
        },
    ]);
    $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('cli'));
    expect($seen)->toBe('cli');
});

it('edge: authorize receives caller job in context [CTX-001]', function () {
    $seen = null;
    $h = PipelineHelpers::harness([
        'allowSystemCallers' => true,
        'authorize_cb' => function ($input, $ctx) use (&$seen) {
            $seen = $ctx->caller();

            return true;
        },
    ]);
    $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('job'));
    expect($seen)->toBe('job');
});
