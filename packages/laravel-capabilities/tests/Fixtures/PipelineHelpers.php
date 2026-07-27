<?php

declare(strict_types=1);

namespace Rawphp\Capabilities\Tests\Fixtures;

use Rawphp\Capabilities\Capability;
use Rawphp\Capabilities\Contracts\Authorizer;
use Rawphp\Capabilities\Pipeline\PipelineStages;
use Rawphp\Capabilities\Pipeline\ResolveActor;
use Rawphp\Capabilities\Registry\CapabilityDefinition;
use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Rawphp\Capabilities\Schema\FailingServerRuleChecker;
use Rawphp\Capabilities\Schema\ServerRuleChecker;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Support\InMemoryRateLimiter;
use Rawphp\Capabilities\Support\StubAuthorizer;
use Rawphp\Capabilities\Support\SystemActor;
use Rawphp\Capabilities\Tests\Support\SharedFakes;
use stdClass;

/**
 * Shared builders for Registry / Pipeline / Facades unit tests.
 */
final class PipelineHelpers
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
     * @param  array<string, mixed>  $opts
     * @return array{registry: CapabilityRegistry, fakes: SharedFakes, runCount: \stdClass}
     */
    public static function harness(array $opts = []): array
    {
        $authorize = $opts['authorize'] ?? true;
        $fakes = SharedFakes::create(authorize: (bool) $authorize);

        $serverRules = $opts['server_rules'] ?? null;
        if ($serverRules === 'fail') {
            $serverRules = new FailingServerRuleChecker(['customer_id']);
        }

        $registry = new CapabilityRegistry(
            globallyEnabledSurfaces: array_merge([
                'agent' => true,
                'mcp' => true,
                'http' => true,
                'cli' => true,
                'job' => true,
                'artisan' => true,
                'messaging' => false,
            ], $opts['surfaces'] ?? []),
            validationConfig: array_merge(['validate_output' => true], $opts['validation'] ?? []),
            authorizer: $opts['authorizer'] ?? $fakes->authorizer,
            approvalStore: $fakes->approvals,
            idempotencyStore: $fakes->idempotency,
            auditWriter: $fakes->audit,
            rateLimiter: $opts['rate_limiter'] ?? $fakes->rateLimiter,
            serverRuleChecker: $serverRules instanceof ServerRuleChecker ? $serverRules : null,
            auditMode: $opts['audit_mode'] ?? 'best_effort',
        );

        $runCount = new stdClass;
        $runCount->value = 0;
        $runCount->sideEffect = false;

        $name = $opts['name'] ?? 'pipe-cap';
        $builder = Capability::define($name)
            ->description('pipeline test capability')
            ->surfaces($opts['cap_surfaces'] ?? ['agent', 'mcp', 'http', 'cli', 'job', 'artisan'])
            ->input($opts['input'] ?? CreateInvoiceInput::class)
            ->output($opts['output'] ?? CreateInvoiceResult::class)
            ->idempotent($opts['idempotent'] ?? 'optional')
            ->audit($opts['audit'] ?? true);

        if (isset($opts['approvalPolicy'])) {
            $builder->approvalPolicy($opts['approvalPolicy']);
        }
        if (isset($opts['rateLimit'])) {
            $builder->rateLimit($opts['rateLimit']);
        }
        $builder->allowSystemCallers($opts['allowSystemCallers'] ?? true);
        if (isset($opts['authorize_cb']) && is_callable($opts['authorize_cb'])) {
            $builder->authorize($opts['authorize_cb']);
        }

        $run = $opts['run'] ?? function ($in) use ($runCount, $opts) {
            $runCount->value++;
            $runCount->sideEffect = true;
            if (isset($opts['run_throws'])) {
                throw new \RuntimeException((string) $opts['run_throws']);
            }
            if (array_key_exists('run_output', $opts)) {
                return $opts['run_output'];
            }

            return new CreateInvoiceResult(invoice_id: 42);
        };
        $builder->run($run)->register($registry);

        return [
            'registry' => $registry,
            'fakes' => $fakes,
            'runCount' => $runCount,
            'name' => $name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function validInput(): array
    {
        return [
            'customer_id' => 1,
            'amount_cents' => 100,
            'currency' => 'USD',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function invalidInput(): array
    {
        return [
            'customer_id' => 'not-int',
            'amount_cents' => 100,
            'currency' => 'USD',
        ];
    }

    public static function userActor(int|string $id = 7): object
    {
        return ResolveActor::defaultUser($id);
    }

    public static function systemActor(string $name = 'billing-worker'): SystemActor
    {
        return SystemActor::named($name);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function options(string $caller = 'http', array $extra = []): array
    {
        $base = [
            'caller' => $caller,
            'actor' => $caller === 'job'
                ? self::systemActor('billing-worker')
                : self::userActor(),
            'tenant_id' => 't-1',
        ];

        if ($caller === 'job') {
            $base['job'] = ['tenant_id' => 't-1'];
            $base['allow'] = true;
        }

        return array_merge($base, $extra);
    }

    public static function assertPreRunFailed(
        CapabilityRegistry $registry,
        CapabilityResult $result,
        object $runCount,
        string $expectedCode,
        string $failedStage,
    ): void {
        expect($result->isOk())->toBeFalse()
            ->and($result->errorCode())->toBe($expectedCode)
            ->and($runCount->value)->toBe(0)
            ->and($runCount->sideEffect)->toBeFalse()
            ->and($registry->lastStages())->toContain($failedStage)
            ->and($registry->lastStages())->not->toContain(PipelineStages::RUN);
    }

    public static function assertFullSuccessOrder(CapabilityRegistry $registry): void
    {
        $stages = $registry->lastStages();
        $ordered = PipelineStages::ordered();
        $prev = -1;
        foreach ($ordered as $stage) {
            // store_idempotency_result is alias recorded alongside store_idempotency
            if ($stage === PipelineStages::STORE_IDEMPOTENCY) {
                expect($stages)->toContain(PipelineStages::STORE_IDEMPOTENCY);
            } else {
                expect($stages)->toContain($stage);
            }
            $idx = array_search($stage === PipelineStages::STORE_IDEMPOTENCY
                ? PipelineStages::STORE_IDEMPOTENCY
                : $stage, $stages, true);
            if ($idx !== false) {
                expect($idx)->toBeGreaterThan($prev);
                $prev = (int) $idx;
            }
        }
    }
}
