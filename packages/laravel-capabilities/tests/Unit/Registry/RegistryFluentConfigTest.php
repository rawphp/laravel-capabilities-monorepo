<?php

declare(strict_types=1);

// CapabilityRegistry fluent config, alias collisions, and forced stage failures.

use Rawphp\Capabilities\Approval\ApprovalManager;
use Rawphp\Capabilities\Pipeline\PipelineStages;
use Rawphp\Capabilities\Registry\CapabilityDefinition;
use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Rawphp\Capabilities\Schema\FailingServerRuleChecker;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Support\CapabilityScope;
use Rawphp\Capabilities\Support\DefaultScopeResolver;
use Rawphp\Capabilities\Support\FixedClock;
use Rawphp\Capabilities\Support\InMemoryApprovalStore;
use Rawphp\Capabilities\Support\InMemoryAuditWriter;
use Rawphp\Capabilities\Support\InMemoryIdempotencyStore;
use Rawphp\Capabilities\Support\InMemoryRateLimiter;
use Rawphp\Capabilities\Support\StubAuthorizer;
use Rawphp\Capabilities\Support\SystemActor;

it('CapabilityRegistry rejects alias collisions, applies fluent config to its getters, and fails forced stages', function () {
    $reg = new CapabilityRegistry;
    $reg->register(new CapabilityDefinition(
        name: 'alpha',
        description: 'a',
        aliases: ['a1'],
        readOnly: true,
        run: static fn () => CapabilityResult::ok(['ok' => true]),
    ));

    expect(fn () => $reg->register(new CapabilityDefinition(
        name: 'beta',
        description: 'b',
        aliases: ['a1'],
        readOnly: true,
    )))->toThrow(InvalidArgumentException::class);

    expect(fn () => $reg->get('missing'))->toThrow(InvalidArgumentException::class);

    $clock = new FixedClock(new DateTimeImmutable('2026-01-01T00:00:00Z'));
    $reg
        ->withValidationConfig(['validate_output' => false, 'audit_mode' => 'best_effort'])
        ->withAuthorizer(StubAuthorizer::allow())
        ->withServerRuleChecker(new FailingServerRuleChecker)
        ->withAuditWriter(new InMemoryAuditWriter($clock))
        ->withAuditConfig([
            'mode' => 'best_effort',
            'enabled' => true,
            'required' => false,
            'driver' => 'log',
        ])
        ->withAuditOutbox(null)
        ->withTransactionsConfig(['wrap_run' => true])
        ->withEventsConfig(['enabled' => true])
        ->withRateLimitConfig(['enabled' => true, 'defaults' => ['per_minute' => 60]])
        ->withApprovalStore(new InMemoryApprovalStore($clock))
        ->withIdempotencyStore(new InMemoryIdempotencyStore($clock))
        ->withRateLimiter(new InMemoryRateLimiter)
        ->withScopeResolver(new DefaultScopeResolver)
        ->withToolSurfaceConfig(['agent' => ['profile' => 'default']])
        ->withSurfaceHealthOverrides(['agent' => 'up'])
        ->withClock($clock)
        ->forceFailStages('json_schema_validate')
        ->throwOnAuditFailure(false);

    expect($reg->validateOutputEnabled())->toBeFalse()
        ->and($reg->auditOutbox())->toBeNull()
        ->and($reg->auditMode())->toBeString()
        ->and($reg->auditRequired())->toBeFalse()
        ->and($reg->auditDriver())->toBeString()
        ->and($reg->transactionsWrapRun())->toBeTrue()
        ->and($reg->lastRunWasWrapped())->toBeBool()
        ->and($reg->eventsEnabled())->toBeTrue()
        ->and($reg->rateLimitConfig())->toBeArray()
        ->and($reg->lastRateLimitKey())->toBeNull()
        ->and($reg->agentTurnBudget())->not->toBeNull()
        ->and($reg->toolSurfaceConfig())->toHaveKey('agent')
        ->and($reg->surfaceHealthOverrides())->toHaveKey('agent')
        ->and($reg->clock())->toBeInstanceOf(FixedClock::class)
        ->and($reg->catalog())->not->toBeNull()
        ->and($reg->toolSchemas())->not->toBeNull()
        ->and($reg->approvals())->toBeInstanceOf(ApprovalManager::class)
        ->and($reg->audit())->not->toBeNull();

    // forceFail single string form covered; clear via empty and force array form
    $reg->forceFailStages([]);
    $reg->register(new CapabilityDefinition(
        name: 'probe',
        description: 'p',
        readOnly: true,
        run: static fn () => CapabilityResult::ok(['p' => 1]),
    ));
    $reg->forceFailStages([
        PipelineStages::JSON_SCHEMA_VALIDATE,
        PipelineStages::HYDRATE_DTO,
        PipelineStages::SERVER_ONLY_VALIDATE,
        PipelineStages::RESOLVE_ACTOR,
    ]);
    $r = $reg->invoke('probe', [], [
        'caller' => 'http',
        'actor' => SystemActor::named('t'),
        'scope' => new CapabilityScope(tenantId: 't1'),
    ]);
    expect($r->isOk())->toBeFalse()->and($r->errorCode())->toBe('validation_failed');
});
