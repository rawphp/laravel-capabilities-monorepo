<?php

declare(strict_types=1);

/**
 * Guards core `capabilities:integration-health` probes of this package.
 *
 * Core does not require the AI package (D-007), so it probes AI contracts by
 * class-string and reads the AI config shape (D-024 "AI-chat mode (health only)").
 * Nothing in core fails when those names drift; these tests do.
 */

use Rawphp\Capabilities\Support\IntegrationHealthChecker;
use Rawphp\CapabilitiesAi\Contracts\ProgressStoreReadiness;
use Rawphp\CapabilitiesAi\Support\AlwaysReadyIdempotency;
use Rawphp\CapabilitiesAi\Support\StoreBoundIdempotencyReadiness;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function aiHealthConfig(array $overrides = []): array
{
    $config = require dirname(__DIR__, 3).'/config/capabilities-ai.php';

    return array_replace_recursive($config, $overrides);
}

/**
 * @param  array<string, mixed>|null  $ai
 * @return array<string, mixed> mode plus checks keyed by code
 */
function coreHealthChecks(?array $ai, ?callable $idempotencyReadinessClass = null): array
{
    $report = (new IntegrationHealthChecker)->check(
        ['surfaces' => ['mcp' => ['enabled' => false]]],
        $ai,
        static fn (string $abstract): bool => true,
        null,
        $idempotencyReadinessClass,
    );

    $byCode = [];
    foreach ($report->checks as $check) {
        $byCode[$check['code']] = $check;
    }

    return ['mode' => $report->mode] + $byCode;
}

it('every core class-string probe resolves to an AI package contract', function (string $constant) {
    $class = constant(IntegrationHealthChecker::class.'::'.$constant);

    expect(interface_exists($class))->toBeTrue("{$constant} = {$class} is not an AI interface");
})->with([
    'AI_CONTEXT',
    'AI_TOOL_CATALOG',
    'AI_IDEMPOTENCY_READINESS',
    'AI_PROGRESS_READINESS',
]);

it('ProgressStoreReadiness exposes the isReady(): bool method core pings', function () {
    expect(IntegrationHealthChecker::AI_PROGRESS_READINESS)->toBe(ProgressStoreReadiness::class);

    $method = new ReflectionMethod(ProgressStoreReadiness::class, 'isReady');
    expect($method->getNumberOfRequiredParameters())->toBe(0)
        ->and((string) $method->getReturnType())->toBe('bool');
});

it('core health reads the AI config keys that switch on AI-chat mode', function () {
    expect(coreHealthChecks(aiHealthConfig(['routes' => ['enabled' => false], 'queue' => ['name' => null]]))['mode'])
        ->toBe('bus-only')
        ->and(coreHealthChecks(aiHealthConfig(['routes' => ['enabled' => true]]))['mode'])->toBe('ai-chat')
        ->and(coreHealthChecks(aiHealthConfig(['queue' => ['name' => 'ai-turns']]))['mode'])->toBe('ai-chat');
});

it('core health reads claim_ttl, progress.driver and proposals.enabled from the AI config', function () {
    $checks = coreHealthChecks(aiHealthConfig([
        'routes' => ['enabled' => true],
        'queue' => ['name' => 'ai-turns'],
        'claim_ttl' => 90,
        'progress' => ['driver' => 'redis'],
        'proposals' => ['enabled' => false],
    ]));

    expect($checks['ai_claim_ttl']['message'])->toBe('claim_ttl is 90.')
        ->and($checks['ai_progress_array']['message'])->toBe('progress.driver is redis.')
        ->and($checks['ai_always_ready']['level'])->toBe('skip');
});

it('core health flags the AI AlwaysReadyIdempotency binding as unsafe with proposals on', function () {
    $ai = aiHealthConfig(['routes' => ['enabled' => true], 'proposals' => ['enabled' => true]]);

    expect(coreHealthChecks($ai, static fn (): string => AlwaysReadyIdempotency::class)['ai_always_ready']['level'])
        ->toBe('fail')
        ->and(coreHealthChecks($ai, static fn (): string => StoreBoundIdempotencyReadiness::class)['ai_always_ready']['level'])
        ->toBe('ok');
});
