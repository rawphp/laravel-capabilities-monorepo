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
use Rawphp\Capabilities\Observability\LogFallbackMetrics;
use Rawphp\Capabilities\Persistence\ArrayTableGateway;
use Rawphp\Capabilities\Persistence\DatabaseApprovalStore;
use Rawphp\Capabilities\Persistence\DatabaseIdempotencyStore;
use Rawphp\Capabilities\Persistence\TableGateway;
use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Rawphp\Capabilities\Support\DefaultScopeResolver;
use Rawphp\Capabilities\Support\InMemoryIdempotencyStore;
use Rawphp\Capabilities\Support\SystemClock;

/**
 * Declarative container binding plan (BOOT-001 / REQ-023 / REQ-033).
 *
 * Pure function of config — unit tests assert without a Laravel app.
 * Database drivers construct Database* stores (gateway injectable; default ArrayTableGateway).
 */
final class ContainerBindings
{
    public const PUBLISH_TAGS = [
        'capabilities-config',
        'capabilities-migrations',
    ];

    public const MEMORY_DRIVERS = ['memory', 'in_memory', 'array'];

    public const DATABASE_DRIVERS = ['database', 'db', 'eloquent'];

    /**
     * @param  array<string, mixed>|null  $config
     * @return list<string>
     */
    public static function abstracts(?array $config = null): array
    {
        return array_keys(self::plan($config));
    }

    /**
     * @param  array<string, mixed>|null  $config
     * @return array<string, class-string|string>
     */
    public static function plan(?array $config = null): array
    {
        return self::resolve($config)['bindings'];
    }

    /**
     * @param  array<string, mixed>|null  $config
     * @return array{
     *     bindings: array<string, class-string|string>,
     *     drivers: array{
     *         approval_store: array{requested: string, resolved: string, concrete: class-string, package_default: bool},
     *         audit: array{requested: string, resolved: string, mode: string, concrete: class-string, package_default: bool},
     *         idempotency: array{requested: string, resolved: string, concrete: class-string, package_default: bool}
     *     },
     *     clients: array<string, mixed>,
     *     surfaces: array<string, mixed>,
     *     registry: class-string
     * }
     */
    public static function resolve(?array $config = null): array
    {
        $config = $config === null || $config === []
            ? CapabilitiesConfig::defaults()
            : $config;

        $approvalStore = self::resolveStoreDriver(
            kind: 'approval.store',
            requested: (string) (($config['approval']['store'] ?? null) ?: 'memory'),
            memoryConcrete: DatabaseApprovalStore::class, // plan abstract only; manager wraps store
            databaseConcrete: DatabaseApprovalStore::class,
        );
        // For plan display: memory path still uses in-memory via makeApprovalManager
        if ($approvalStore['resolved'] === 'memory') {
            $approvalStore['concrete'] = \Rawphp\Capabilities\Support\InMemoryApprovalStore::class;
        }

        $auditDriver = self::resolveStoreDriver(
            kind: 'audit.driver',
            requested: (string) (($config['audit']['driver'] ?? null) ?: 'memory'),
            memoryConcrete: AuditLogger::class,
            databaseConcrete: AuditLogger::class, // outbox writer not in this UR
        );

        $auditMode = (string) (($config['audit']['mode'] ?? null) ?: 'best_effort');
        if (! in_array($auditMode, AuditLogger::SUPPORTED_MODES, true)) {
            throw BootException::unknownAuditMode($auditMode);
        }

        $idempotency = self::resolveStoreDriver(
            kind: 'idempotency.driver',
            requested: (string) (($config['idempotency']['driver'] ?? null) ?: 'memory'),
            memoryConcrete: InMemoryIdempotencyStore::class,
            databaseConcrete: DatabaseIdempotencyStore::class,
        );

        $metricsEnabled = (bool) ($config['observability']['metrics'] ?? true);
        $tracingEnabled = (bool) ($config['observability']['tracing'] ?? true);

        $bindings = [
            'CapabilityRegistry' => CapabilityRegistry::class,
            CapabilityRegistry::class => CapabilityRegistry::class,
            'ApprovalManager' => ApprovalManager::class,
            ApprovalManager::class => ApprovalManager::class,
            'IdempotencyStore' => IdempotencyStore::class,
            IdempotencyStore::class => $idempotency['concrete'],
            TableGateway::class => ArrayTableGateway::class,
            'AuditLogger' => AuditLogger::class,
            AuditLogger::class => AuditLogger::class,
            'ScopeResolver' => ScopeResolver::class,
            ScopeResolver::class => DefaultScopeResolver::class,
            'AiToolAdapter' => AiToolAdapter::class,
            AiToolAdapter::class => AiToolAdapterV1::class,
            'McpToolAdapter' => McpToolAdapter::class,
            McpToolAdapter::class => McpToolAdapterV1::class,
            'Metrics' => MetricsContract::class,
            MetricsContract::class => $metricsEnabled ? LogFallbackMetrics::class : InMemoryMetrics::class,
            'Tracer' => TracerContract::class,
            TracerContract::class => InMemoryTracer::class,
            PeerVersionProbe::class => PeerVersionProbe::class,
        ];

        return [
            'bindings' => $bindings,
            'drivers' => [
                'approval_store' => [
                    'requested' => $approvalStore['requested'],
                    'resolved' => $approvalStore['resolved'],
                    'concrete' => $approvalStore['concrete'],
                    'package_default' => $approvalStore['package_default'],
                ],
                'audit' => [
                    'requested' => $auditDriver['requested'],
                    'resolved' => $auditDriver['resolved'],
                    'mode' => $auditMode,
                    'concrete' => $auditDriver['concrete'],
                    'package_default' => $auditDriver['package_default'],
                ],
                'idempotency' => [
                    'requested' => $idempotency['requested'],
                    'resolved' => $idempotency['resolved'],
                    'concrete' => $idempotency['concrete'],
                    'package_default' => $idempotency['package_default'],
                ],
            ],
            'clients' => (array) ($config['clients'] ?? []),
            'surfaces' => (array) ($config['surfaces'] ?? []),
            'registry' => CapabilityRegistry::class,
            'observability' => [
                'metrics' => $metricsEnabled,
                'tracing' => $tracingEnabled,
            ],
        ];
    }

    public static function binds(string $abstract, ?array $config = null): bool
    {
        $plan = self::plan($config);

        return isset($plan[$abstract]) || in_array($abstract, $plan, true);
    }

    public static function hasPublishTag(string $tag): bool
    {
        return in_array($tag, self::PUBLISH_TAGS, true);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function makeRegistry(array $config = []): CapabilityRegistry
    {
        unset($config);

        return new CapabilityRegistry;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function makeIdempotencyStore(array $config = [], ?TableGateway $gateway = null): IdempotencyStore
    {
        $resolved = self::resolve($config === [] ? null : $config);
        $driver = $resolved['drivers']['idempotency']['resolved'];
        $clock = new SystemClock;

        return match ($driver) {
            'memory' => new InMemoryIdempotencyStore($clock),
            'database' => new DatabaseIdempotencyStore($gateway ?? new ArrayTableGateway, $clock),
            default => throw BootException::unknownDriver('idempotency.driver', $driver),
        };
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function makeApprovalManager(array $config = [], ?TableGateway $gateway = null): ApprovalManager
    {
        $full = $config === [] ? CapabilitiesConfig::defaults() : $config;
        $resolved = self::resolve($full);
        $driver = $resolved['drivers']['approval_store']['resolved'];
        $approvalConfig = (array) ($full['approval'] ?? []);
        $clock = new SystemClock;

        if ($driver === 'memory') {
            return ApprovalManager::inMemory($clock)->withConfig($approvalConfig);
        }

        if ($driver === 'database') {
            $store = new DatabaseApprovalStore($gateway ?? new ArrayTableGateway, $clock);

            return (new ApprovalManager($store, $clock, $approvalConfig))->withConfig($approvalConfig);
        }

        throw BootException::unknownDriver('approval.store', $driver);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function makeAuditLogger(array $config = []): AuditLogger
    {
        self::resolve($config === [] ? null : $config);

        return new AuditLogger;
    }

    /**
     * @return array{requested: string, resolved: string, concrete: class-string, package_default: bool}
     */
    private static function resolveStoreDriver(
        string $kind,
        string $requested,
        string $memoryConcrete,
        string $databaseConcrete,
    ): array {
        $normalized = strtolower(trim($requested));
        if ($normalized === '') {
            $normalized = 'memory';
        }

        if (in_array($normalized, self::MEMORY_DRIVERS, true)) {
            return [
                'requested' => $normalized,
                'resolved' => 'memory',
                'concrete' => $memoryConcrete,
                'package_default' => false,
            ];
        }

        if (in_array($normalized, self::DATABASE_DRIVERS, true)) {
            return [
                'requested' => $normalized,
                'resolved' => 'database',
                'concrete' => $databaseConcrete,
                'package_default' => false,
            ];
        }

        throw BootException::unknownDriver($kind, $requested);
    }
}
