<?php

namespace Rawphp\Capabilities\Boot;

use Rawphp\Capabilities\Adapters\Ai\AiToolAdapter;
use Rawphp\Capabilities\Adapters\Ai\AiToolAdapterV1;
use Rawphp\Capabilities\Adapters\Mcp\McpToolAdapter;
use Rawphp\Capabilities\Adapters\Mcp\McpToolAdapterV1;
use Rawphp\Capabilities\Adapters\PeerVersionProbe;
use Rawphp\Capabilities\Approval\ApprovalManager;
use Rawphp\Capabilities\Audit\AuditLogger;
use Rawphp\Capabilities\Contracts\IdempotencyStore;
use Rawphp\Capabilities\Contracts\Metrics as MetricsContract;
use Rawphp\Capabilities\Contracts\ScopeResolver;
use Rawphp\Capabilities\Contracts\Tracer as TracerContract;
use Rawphp\Capabilities\Observability\InMemoryMetrics;
use Rawphp\Capabilities\Observability\InMemoryTracer;
use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Rawphp\Capabilities\Support\DefaultScopeResolver;
use Rawphp\Capabilities\Support\InMemoryIdempotencyStore;

/**
 * Declarative container binding plan (BOOT-001).
 *
 * Unit tests assert the plan without booting a full Laravel container.
 * Tests rebind via {@see ArrayContainer}.
 */
final class ContainerBindings
{
    public const PUBLISH_TAGS = [
        'capabilities-config',
        'capabilities-migrations',
    ];

    /**
     * Abstract names bound by the service provider.
     *
     * @return list<string>
     */
    public static function abstracts(): array
    {
        return array_keys(self::plan());
    }

    /**
     * @return array<string, class-string|string>
     */
    public static function plan(): array
    {
        return [
            'CapabilityRegistry' => CapabilityRegistry::class,
            CapabilityRegistry::class => CapabilityRegistry::class,
            'ApprovalManager' => ApprovalManager::class,
            ApprovalManager::class => ApprovalManager::class,
            'IdempotencyStore' => IdempotencyStore::class,
            IdempotencyStore::class => InMemoryIdempotencyStore::class,
            'AuditLogger' => AuditLogger::class,
            AuditLogger::class => AuditLogger::class,
            'ScopeResolver' => ScopeResolver::class,
            ScopeResolver::class => DefaultScopeResolver::class,
            'AiToolAdapter' => AiToolAdapter::class,
            AiToolAdapter::class => AiToolAdapterV1::class,
            'McpToolAdapter' => McpToolAdapter::class,
            McpToolAdapter::class => McpToolAdapterV1::class,
            'Metrics' => MetricsContract::class,
            MetricsContract::class => InMemoryMetrics::class,
            'Tracer' => TracerContract::class,
            TracerContract::class => InMemoryTracer::class,
            PeerVersionProbe::class => PeerVersionProbe::class,
        ];
    }

    public static function binds(string $abstract): bool
    {
        $plan = self::plan();

        return isset($plan[$abstract]) || in_array($abstract, $plan, true);
    }

    public static function hasPublishTag(string $tag): bool
    {
        return in_array($tag, self::PUBLISH_TAGS, true);
    }
}
