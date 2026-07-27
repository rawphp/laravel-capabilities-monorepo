<?php

declare(strict_types=1);

namespace Rawphp\Capabilities\Tests\Fixtures;

use Rawphp\Capabilities\Capability;
use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Rawphp\Capabilities\Support\InMemoryRateLimiter;
use Rawphp\Capabilities\Tests\Support\SharedFakes;
use stdClass;

/**
 * Shared builders for RateLimiting/* unit tests (D-013).
 */
final class RateLimitHelpers
{
    /**
     * @param  array<string, mixed>  $opts
     * @return array{
     *     registry: CapabilityRegistry,
     *     fakes: SharedFakes,
     *     limiter: InMemoryRateLimiter,
     *     runCount: stdClass,
     *     name: string
     * }
     */
    public static function harness(array $opts = []): array
    {
        $fakes = SharedFakes::create(authorize: true);
        $limiter = $opts['limiter'] ?? new InMemoryRateLimiter;

        $registry = new CapabilityRegistry(
            globallyEnabledSurfaces: [
                'agent' => true,
                'mcp' => true,
                'http' => true,
                'cli' => true,
                'job' => true,
                'artisan' => true,
                'messaging' => false,
            ],
            validationConfig: ['validate_output' => true],
            authorizer: $fakes->authorizer,
            approvalStore: $fakes->approvals,
            idempotencyStore: $fakes->idempotency,
            auditWriter: $fakes->audit,
            rateLimiter: $limiter,
            rateLimitConfig: array_merge([
                'enabled' => $opts['enabled'] ?? true,
                'defaults' => [
                    'per_minute' => $opts['per_min'] ?? 60,
                    'per_capability_per_minute' => $opts['per_cap'] ?? 30,
                ],
                'agent_turn' => [
                    'max_tool_calls' => $opts['max_tool_calls'] ?? 16,
                ],
            ], $opts['rate_limit_config'] ?? []),
            auditConfig: ['enabled' => false],
        );

        $runCount = new stdClass;
        $runCount->value = 0;

        $name = $opts['name'] ?? 'rl-cap';
        $builder = Capability::define($name)
            ->description('rate limit test capability')
            ->surfaces(['agent', 'mcp', 'http', 'cli', 'job', 'artisan'])
            ->input(CreateInvoiceInput::class)
            ->output(CreateInvoiceResult::class)
            ->idempotent('none')
            ->audit(false)
            ->allowSystemCallers(true);

        if (isset($opts['rateLimit'])) {
            $builder->rateLimit($opts['rateLimit']);
        }

        $builder->run(function () use ($runCount) {
            $runCount->value++;

            return new CreateInvoiceResult(invoice_id: 1);
        })->register($registry);

        return [
            'registry' => $registry,
            'fakes' => $fakes,
            'limiter' => $limiter,
            'runCount' => $runCount,
            'name' => $name,
        ];
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function options(string $caller = 'http', array $extra = []): array
    {
        return PipelineHelpers::options($caller, $extra);
    }

    /**
     * @return array<string, mixed>
     */
    public static function input(): array
    {
        return PipelineHelpers::validInput();
    }
}
