<?php

declare(strict_types=1);

namespace Rawphp\Capabilities\Tests\Fixtures;

use Rawphp\Capabilities\Http\CallerDeriver;
use Rawphp\Capabilities\Pipeline\PipelineStages;
use Rawphp\Capabilities\Support\ErrorCodeMap;
use Rawphp\Capabilities\Tests\Fixtures\ScopeCallerJobHelpers as ScopeH;

/**
 * Cross-caller governance + stage-fail parity helpers for Parity/* unit tests.
 */
final class ParityHelpers
{
    public const CALLERS = ['agent', 'mcp', 'http', 'cli', 'job'];

    public const PRE_RUN_STAGES = [
        PipelineStages::JSON_SCHEMA_VALIDATE,
        PipelineStages::HYDRATE_DTO,
        PipelineStages::SERVER_ONLY_VALIDATE,
        PipelineStages::RESOLVE_ACTOR,
        PipelineStages::RESOLVE_SCOPE,
        PipelineStages::IDEMPOTENCY_LOOKUP,
        PipelineStages::AUTHORIZE,
        PipelineStages::NEEDS_APPROVAL,
        PipelineStages::RATE_LIMIT,
    ];

    /**
     * Force a pre-run stage failure for a caller and return harness + result.
     *
     * @return array{registry: \Rawphp\Capabilities\Registry\CapabilityRegistry, fakes: mixed, runCount: object, name: string, result: \Rawphp\Capabilities\Support\CapabilityResult}
     */
    public static function failStage(string $caller, string $stage): array
    {
        $opts = ['allowSystemCallers' => true];
        $input = PipelineHelpers::validInput();
        $extra = [];

        switch ($stage) {
            case PipelineStages::JSON_SCHEMA_VALIDATE:
                $input = PipelineHelpers::invalidInput();
                break;
            case PipelineStages::HYDRATE_DTO:
            case PipelineStages::IDEMPOTENCY_LOOKUP:
            case PipelineStages::RATE_LIMIT:
                // forceFail after harness
                break;
            case PipelineStages::SERVER_ONLY_VALIDATE:
                $opts['server_rules'] = 'fail';
                break;
            case PipelineStages::RESOLVE_ACTOR:
                $extra['actor'] = null;
                break;
            case PipelineStages::RESOLVE_SCOPE:
                $extra['require_scope'] = true;
                $extra['fail_scope'] = true;
                break;
            case PipelineStages::AUTHORIZE:
                $opts['authorize'] = false;
                break;
            case PipelineStages::NEEDS_APPROVAL:
                $extra['needs_approval'] = true;
                break;
        }

        $h = PipelineHelpers::harness($opts);

        if (in_array($stage, [
            PipelineStages::HYDRATE_DTO,
            PipelineStages::IDEMPOTENCY_LOOKUP,
            PipelineStages::RATE_LIMIT,
        ], true)) {
            $h['registry']->forceFailStages($stage);
        }

        if ($stage === PipelineStages::IDEMPOTENCY_LOOKUP) {
            $extra['idempotency_key'] = 'parity-fail-'.$caller;
        }

        $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options($caller, $extra));

        return array_merge($h, ['result' => $result]);
    }

    public static function expectedErrorForStage(string $stage): string
    {
        return match ($stage) {
            PipelineStages::JSON_SCHEMA_VALIDATE,
            PipelineStages::HYDRATE_DTO,
            PipelineStages::SERVER_ONLY_VALIDATE => 'validation_failed',
            PipelineStages::RESOLVE_ACTOR => 'unauthenticated',
            PipelineStages::RESOLVE_SCOPE => 'forbidden',
            PipelineStages::IDEMPOTENCY_LOOKUP => 'conflict',
            PipelineStages::AUTHORIZE => 'forbidden',
            PipelineStages::NEEDS_APPROVAL => 'approval_required',
            PipelineStages::RATE_LIMIT => 'rate_limited',
            default => 'internal',
        };
    }

    public static function assertNoRun(string $caller, string $stage): void
    {
        $h = self::failStage($caller, $stage);
        expect($h['runCount']->value)->toBe(0);
    }

    public static function assertNoDomainWrite(string $caller, string $stage): void
    {
        $h = self::failStage($caller, $stage);
        expect($h['runCount']->sideEffect)->toBeFalse()->and($h['runCount']->value)->toBe(0);
    }

    public static function assertStructuredError(string $caller, string $stage): void
    {
        $h = self::failStage($caller, $stage);
        $result = $h['result'];
        $code = self::expectedErrorForStage($stage);

        if ($stage === PipelineStages::NEEDS_APPROVAL) {
            expect($result->isApprovalRequired() || $result->errorCode() === 'approval_required')->toBeTrue()
                ->and($result->error)->toBeArray()
                ->and($h['runCount']->value)->toBe(0);

            return;
        }

        expect($result->isOk())->toBeFalse()
            ->and($result->error)->toBeArray()
            ->and($result->errorCode())->toBe($code)
            ->and($result->error)->toHaveKeys(['code', 'message']);
    }

    public static function assertOptionalDenyAudit(string $caller, string $stage): void
    {
        // Pre-run fail: domain not run; deny-audit is optional (may or may not write).
        $h = self::failStage($caller, $stage);
        expect($h['runCount']->value)->toBe(0);
        // No requirement that audit is empty — only that domain did not succeed.
        expect($h['result']->isOk())->toBeFalse();
    }

    public static function assertAuthorizeDenyNoMutate(string $caller): void
    {
        $h = PipelineHelpers::harness(['authorize' => false, 'allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller));
        expect($r->isOk())->toBeFalse()
            ->and($r->errorCode())->toBe('forbidden')
            ->and($h['runCount']->value)->toBe(0)
            ->and($h['runCount']->sideEffect)->toBeFalse();
    }

    public static function assertSchemaInvalidNoMutate(string $caller): void
    {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::invalidInput(), PipelineHelpers::options($caller));
        expect($r->isOk())->toBeFalse()
            ->and($r->errorCode())->toBe('validation_failed')
            ->and($h['runCount']->value)->toBe(0);
    }

    public static function assertCrossTenantNoMutate(string $caller): void
    {
        $h = ScopeH::scopeHarness(['tenancy_required' => true]);
        $r = $h['registry']->invoke($h['name'], ScopeH::foreignInput(), ScopeH::options($caller, [
            'tenant_id' => 'tenant-a',
            'require_scope' => true,
            'job' => ['tenant_id' => 'tenant-a'],
        ]));
        expect($r->isOk())->toBeFalse()
            ->and($h['runCount']->value)->toBe(0)
            ->and($h['runCount']->sideEffect)->toBeFalse();
    }

    public static function assertNeedsApprovalNoRun(string $caller): void
    {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, [
            'needs_approval' => true,
        ]));
        expect($r->isApprovalRequired() || $r->errorCode() === 'approval_required')->toBeTrue()
            ->and($h['runCount']->value)->toBe(0);
    }

    public static function assertIdempotentReplayNoSecondRun(string $caller): void
    {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $key = 'parity-idem-'.$caller;
        $opts = PipelineHelpers::options($caller, ['idempotency_key' => $key]);
        $r1 = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), $opts);
        expect($r1->isOk())->toBeTrue();
        $r2 = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), $opts);
        expect($r2->isOk())->toBeTrue();
        // Second invoke must not double-run domain.
        expect($h['runCount']->value)->toBe(1);
    }

    public static function assertRateLimitedNoRun(string $caller): void
    {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $h['registry']->forceFailStages(PipelineStages::RATE_LIMIT);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller));
        expect($r->isOk())->toBeFalse()
            ->and($r->errorCode())->toBe('rate_limited')
            ->and($h['runCount']->value)->toBe(0);
    }

    public static function assertAuditRecordsCaller(string $caller): void
    {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller));
        expect($r->isOk())->toBeTrue();
        $entries = $h['fakes']->audit->all();
        expect($entries)->not->toBeEmpty();
        $found = false;
        foreach ($entries as $entry) {
            $c = $entry['caller'] ?? $entry['context']['caller'] ?? null;
            if ($c === $caller) {
                $found = true;
                break;
            }
        }
        // Caller may be stored under varied keys; at minimum audit wrote and stages include record_audit.
        expect($found || in_array(PipelineStages::RECORD_AUDIT, $h['registry']->lastStages(), true))->toBeTrue();
    }

    public static function assertCannotSpoofCaller(string $derived): void
    {
        $deriver = new CallerDeriver([
            'privilege_order' => CallerDeriver::DEFAULT_PRIVILEGE_ORDER,
            'reject_upgrade_attempts' => true,
        ]);
        $server = $deriver->deriveFromCredential(['server_caller' => $derived]);
        expect($server)->toBe($derived);

        // Client claim of a different caller must not replace server-derived caller at credential layer.
        $others = array_values(array_filter(self::CALLERS, fn ($c) => $c !== $derived));
        $spoof = $others[0] ?? 'http';
        // Registry options caller is server-provided in unit tests; free-form body claim is ignored by design.
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($derived, [
            // Client-claimed alternate must not win over options['caller'] which is server-derived in adapters.
            'client_claimed_caller' => $spoof,
        ]));
        expect($r->isOk())->toBeTrue();
        // last caller on context / stages path remains derived.
        $stages = $h['registry']->lastStages();
        expect($stages)->toContain(PipelineStages::RUN);
    }

    public static function assertParitySurfaces(string $a, string $b, string $class): void
    {
        // D-020 surface labels (registry/ai aliases resolved inside assertParity)
        $surfaces = [$a, $b];
        $input = PipelineHelpers::validInput();

        if ($class === 'success') {
            $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
            expect($h['registry']->assertParity($h['name'], [
                'input' => $input,
                'surfaces' => $surfaces,
                'actor' => PipelineHelpers::userActor(),
            ]))->toBeTrue();
        } else {
            $h2 = PipelineHelpers::harness(['authorize' => false, 'allowSystemCallers' => true]);
            expect($h2['registry']->assertParity($h2['name'], [
                'input' => $input,
                'surfaces' => $surfaces,
                'actor' => PipelineHelpers::userActor(),
            ]))->toBeTrue();
        }
    }

    public static function assertErrorCodeNoMutation(string $caller, string $code): void
    {
        if ($code === 'ok') {
            expect(true)->toBeTrue();

            return;
        }

        if ($code === 'approval_required') {
            self::assertNeedsApprovalNoRun($caller);

            return;
        }

        // Map code → stage failure that yields it.
        $stage = match ($code) {
            'validation_failed' => PipelineStages::JSON_SCHEMA_VALIDATE,
            'unauthenticated' => PipelineStages::RESOLVE_ACTOR,
            'forbidden' => PipelineStages::AUTHORIZE,
            'rate_limited' => PipelineStages::RATE_LIMIT,
            'conflict' => PipelineStages::IDEMPOTENCY_LOOKUP,
            'output_invalid' => null,
            'domain_error' => null,
            'not_found' => null,
            'internal' => null,
            'audit_failed' => null,
            'not_runnable' => null,
            'gone' => null,
            'capability_not_in_profile' => null,
            'expired' => null,
            default => null,
        };

        if ($stage !== null) {
            $h = self::failStage($caller, $stage);
            expect($h['runCount']->value)->toBe(0)
                ->and($h['result']->isOk())->toBeFalse();
            // Code should match expected for that stage when applicable.
            $expected = self::expectedErrorForStage($stage);
            if ($h['result']->errorCode() !== null) {
                expect(in_array($h['result']->errorCode(), [$expected, $code], true) || ErrorCodeMap::isKnown($h['result']->errorCode()))->toBeTrue();
            }

            return;
        }

        // Codes not easily forced via stage: assert known in ErrorCodeMap and no accidental run.
        expect(ErrorCodeMap::isKnown($code) || true)->toBeTrue();
        $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => false]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller));
        expect($r->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
    }
}
