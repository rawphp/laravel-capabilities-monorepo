<?php

// Spec-derived bulk caller × scenario matrix for D-005. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Support\SystemActor;
use Rawphp\Capabilities\Tests\Fixtures\IdempotencyHelpers;

$callers = IdempotencyHelpers::CALLERS;
$scenarios = [
    'first',
    'replay_same',
    'conflict_diff',
    'processing',
    'failed_replay',
    'readonly_ignore',
    'required_missing',
];

foreach ($callers as $caller) {
    foreach ($scenarios as $scenario) {
        $type = match ($scenario) {
            'first', 'replay_same', 'readonly_ignore' => 'happy',
            'conflict_diff', 'required_missing' => 'fail',
            default => 'edge',
        };
        // Keep inventory titles: happy|fail|edge: idempotency {scenario} for {caller}
        $title = match ($scenario) {
            'first' => "happy: idempotency first for {$caller} [D-005]",
            'replay_same' => "happy: idempotency replay_same for {$caller} [D-005]",
            'conflict_diff' => "fail: idempotency conflict_diff for {$caller} [D-005]",
            'processing' => "edge: idempotency processing for {$caller} [D-005]",
            'failed_replay' => "edge: idempotency failed_replay for {$caller} [D-005]",
            'readonly_ignore' => "edge: idempotency readonly_ignore for {$caller} [D-005]",
            'required_missing' => "fail: idempotency required_missing for {$caller} [D-005]",
        };

        it($title, function () use ($caller, $scenario) {
            $key = "bulk-{$caller}-{$scenario}";
            $actor = $caller === 'job'
                ? SystemActor::named('billing-worker')
                : IdempotencyHelpers::actorFromLabel('user:7');

            if ($scenario === 'readonly_ignore') {
                $def = IdempotencyHelpers::mutatingDefinition('list-bulk', readOnly: true);
                expect($def->shouldUseIdempotency())->toBeFalse();
                $guard = IdempotencyHelpers::guard();
                $out = $guard->lookup($def, IdempotencyHelpers::context($caller, $actor), $key, 'h');
                expect($out['action'])->toBe('continue');

                return;
            }

            if ($scenario === 'required_missing') {
                $h = IdempotencyHelpers::harness([
                    'name' => "req-{$caller}",
                    'idempotent' => 'required',
                ]);
                $r = $h['registry']->invoke(
                    $h['name'],
                    IdempotencyHelpers::inputA(),
                    IdempotencyHelpers::options($caller, ['actor' => $actor]),
                );
                expect($r->errorCode())->toBe('validation_failed')
                    ->and($h['runCount']->value)->toBe(0);

                return;
            }

            if ($scenario === 'processing') {
                $clock = IdempotencyHelpers::clock();
                $store = IdempotencyHelpers::store($clock);
                $guard = IdempotencyHelpers::guard($store, $clock);
                $def = IdempotencyHelpers::mutatingDefinition();
                $ctx = IdempotencyHelpers::context($caller, $actor);
                $hash = IdempotencyHelpers::hash(IdempotencyHelpers::inputA());
                $guard->lookup($def, $ctx, $key, $hash);
                $again = $guard->lookup($def, $ctx, $key, $hash);
                expect($again['action'])->toBe('busy');

                return;
            }

            if ($scenario === 'failed_replay') {
                $clock = IdempotencyHelpers::clock();
                $store = IdempotencyHelpers::store($clock);
                $guard = IdempotencyHelpers::guard($store, $clock);
                $def = IdempotencyHelpers::mutatingDefinition();
                $ctx = IdempotencyHelpers::context($caller, $actor);
                $hash = IdempotencyHelpers::hash(IdempotencyHelpers::inputA());
                $guard->lookup($def, $ctx, $key, $hash);
                $guard->storeResult($def, $ctx, $key, $hash, CapabilityResult::failure('validation_failed', 'nope'));
                $out = $guard->lookup($def, $ctx, $key, $hash);
                expect($out['action'])->toBe('replay')
                    ->and($out['result']->isFailed())->toBeTrue()
                    ->and($out['result']->isReplay())->toBeTrue();

                return;
            }

            if ($scenario === 'conflict_diff') {
                $h = IdempotencyHelpers::harness(['name' => "conf-{$caller}"]);
                $opts = IdempotencyHelpers::options($caller, ['actor' => $actor, 'idempotency_key' => $key]);
                $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), $opts);
                $r = $h['registry']->invoke($h['name'], IdempotencyHelpers::inputB(), $opts);
                expect($r->errorCode())->toBe('conflict')
                    ->and($h['runCount']->value)->toBe(1);

                return;
            }

            // first / replay_same via registry
            $h = IdempotencyHelpers::harness(['name' => "ok-{$caller}-{$scenario}"]);
            $opts = IdempotencyHelpers::options($caller, ['actor' => $actor, 'idempotency_key' => $key]);
            $a = $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), $opts);

            if ($scenario === 'first') {
                expect($a->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);

                return;
            }

            $b = $h['registry']->invoke($h['name'], IdempotencyHelpers::inputA(), $opts);
            expect($a->isOk())->toBeTrue()
                ->and($b->isReplay())->toBeTrue()
                ->and($h['runCount']->value)->toBe(1);
        });
    }
}
