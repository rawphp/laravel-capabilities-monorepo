<?php

namespace Rawphp\Capabilities\Boot;

use Rawphp\Capabilities\Adapters\Ai\AiToolAdapter;
use Rawphp\Capabilities\Adapters\Ai\AiToolAdapterV1;
use Rawphp\Capabilities\Adapters\Mcp\McpToolAdapter;
use Rawphp\Capabilities\Adapters\Mcp\McpToolAdapterV1;
use Rawphp\Capabilities\Adapters\PeerVersionProbe;
use Rawphp\Capabilities\Approval\ApprovalManager;
use Rawphp\Capabilities\Audit\AuditLogger;
use Rawphp\Capabilities\Contracts\ApprovalStore;
use Rawphp\Capabilities\Contracts\CapabilityBus;
use Rawphp\Capabilities\Contracts\IdempotencyStore;
use Rawphp\Capabilities\Contracts\Metrics as MetricsContract;
use Rawphp\Capabilities\Contracts\ScopeResolver;
use Rawphp\Capabilities\Contracts\Tracer as TracerContract;
use Illuminate\Database\ConnectionInterface;
use Rawphp\Capabilities\Observability\InMemoryMetrics;
use Rawphp\Capabilities\Observability\InMemoryTracer;
use Rawphp\Capabilities\Observability\LogFallbackMetrics;
use Rawphp\Capabilities\Persistence\ArrayTableGateway;
use Rawphp\Capabilities\Persistence\DatabaseApprovalStore;
use Rawphp\Capabilities\Persistence\DatabaseIdempotencyStore;
use Rawphp\Capabilities\Persistence\MigrationCatalog;
use Rawphp\Capabilities\Persistence\QueryTableGateway;
use Rawphp\Capabilities\Persistence\TableGateway;
use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Rawphp\Capabilities\Support\DefaultScopeResolver;
use Rawphp\Capabilities\Support\InMemoryIdempotencyStore;
use Rawphp\Capabilities\Support\SystemClock;

/**
 * Declarative container binding plan (BOOT-001 / REQ-023 / REQ-033).
 *
 * Pure function of config — unit tests assert without a Laravel app.
 * Database drivers construct Database* stores via {@see QueryTableGateway}
 * (per-table; connection required). Host may inject {@see TableGateway}
 * (e.g. {@see ArrayTableGateway}) for unit isolation. No silent Array fallback.
 */
final class ContainerBindings
{
    /**
     * Logical store keys remapped to MigrationCatalog physical columns (approvals).
     *
     * @var array<string, string>
     */
    public const APPROVAL_COLUMN_MAP = [
        'scope' => 'scope_json',
        'messaging' => 'channel_meta_json',
    ];
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

        $usesDatabaseGateway = $approvalStore['resolved'] === 'database'
            || $idempotency['resolved'] === 'database';

        $bindings = [
            'CapabilityRegistry' => CapabilityRegistry::class,
            CapabilityRegistry::class => CapabilityRegistry::class,
            // HTTP controller type-hints CapabilityBus; must resolve to the registry singleton (REQ-057).
            'CapabilityBus' => CapabilityBus::class,
            CapabilityBus::class => CapabilityRegistry::class,
            'ApprovalManager' => ApprovalManager::class,
            ApprovalManager::class => ApprovalManager::class,
            'IdempotencyStore' => IdempotencyStore::class,
            IdempotencyStore::class => $idempotency['concrete'],
            // Production: QueryTableGateway per table + connection. Memory-only plans
            // still advertise ArrayTableGateway for unit isolation / host overrides.
            TableGateway::class => $usesDatabaseGateway
                ? QueryTableGateway::class
                : ArrayTableGateway::class,
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
     * Build a fully config-wired registry (approval/idempotency/audit/scope + surface knobs).
     *
     * Reuses {@see CapabilityRegistry} with* injectors and the same driver factories as
     * {@see makeApprovalManager} / {@see makeIdempotencyStore}. Does not reimplement the pipeline.
     *
     * When $approvalStore / $idempotencyStore are provided (service-provider path), those
     * instances are injected as-is so invoke and accept paths cannot diverge (REQ-048).
     * Standalone database drivers without prebuilt stores build per-table
     * {@see QueryTableGateway} instances (approvals vs idempotency) from $connection.
     * Optional host $gateway (typically {@see ArrayTableGateway} in unit tests) overrides.
     *
     * @param  array<string, mixed>  $config
     */
    public static function makeRegistry(
        array $config = [],
        ?TableGateway $gateway = null,
        ?ApprovalStore $approvalStore = null,
        ?IdempotencyStore $idempotencyStore = null,
        ?ConnectionInterface $connection = null,
    ): CapabilityRegistry {
        $full = $config === [] ? CapabilitiesConfig::defaults() : $config;
        // Validate drivers/modes early (fail closed) using the shared resolve path.
        self::resolve($full);

        $registry = new CapabilityRegistry(clock: new SystemClock);

        $registry->withGloballyEnabledSurfaces(CapabilitiesConfig::globallyEnabledSurfaces($full));

        if ($approvalStore === null) {
            $approvalStore = self::makeApprovalManager($full, $gateway, $connection)->store();
        }
        $registry->withApprovalStore($approvalStore);

        if ($idempotencyStore === null) {
            $idempotencyStore = self::makeIdempotencyStore($full, $gateway, $connection);
        }
        $registry->withIdempotencyStore($idempotencyStore);

        $registry->withScopeResolver(new DefaultScopeResolver);

        $audit = (array) ($full['audit'] ?? []);
        if ($audit !== []) {
            $registry->withAuditConfig(self::registryAuditConfig($audit));
        }

        $rateLimits = (array) ($full['rate_limits'] ?? []);
        if ($rateLimits !== []) {
            $registry->withRateLimitConfig($rateLimits);
        }

        $validation = (array) ($full['validation'] ?? []);
        if ($validation !== []) {
            $registry->withValidationConfig($validation);
        }

        $transactions = (array) ($full['transactions'] ?? []);
        if ($transactions !== []) {
            $registry->withTransactionsConfig($transactions);
        }

        $events = (array) ($full['events'] ?? []);
        if ($events !== []) {
            $registry->withEventsConfig($events);
        }

        $toolSurface = self::toolSurfaceConfigFromSurfaces((array) ($full['surfaces'] ?? []));
        if ($toolSurface !== []) {
            $registry->withToolSurfaceConfig($toolSurface);
        }

        return $registry;
    }

    /**
     * Map package audit config onto registry-accepted audit keys (D-010 drivers).
     *
     * Boot resolve accepts memory/database store aliases; registry auditDriver is
     * database|log|queue only — memory aliases are omitted so the registry default stands.
     *
     * @param  array<string, mixed>  $audit
     * @return array{
     *     enabled?: bool,
     *     mode?: string,
     *     required?: bool,
     *     driver?: string,
     *     queue?: string
     * }
     */
    private static function registryAuditConfig(array $audit): array
    {
        $out = [];
        if (array_key_exists('enabled', $audit)) {
            $out['enabled'] = (bool) $audit['enabled'];
        }
        if (isset($audit['mode'])) {
            $out['mode'] = (string) $audit['mode'];
        }
        if (array_key_exists('required', $audit)) {
            $out['required'] = (bool) $audit['required'];
        }
        if (isset($audit['queue'])) {
            $out['queue'] = (string) $audit['queue'];
        }
        if (isset($audit['driver'])) {
            $driver = strtolower(trim((string) $audit['driver']));
            if (in_array($driver, AuditLogger::SUPPORTED_DRIVERS, true)) {
                $out['driver'] = $driver;
            } elseif (in_array($driver, self::DATABASE_DRIVERS, true)) {
                $out['driver'] = 'database';
            }
            // memory/in_memory/array → leave registry default (no invalid driver pass-through)
        }

        return $out;
    }

    /**
     * Lift agent/mcp tool profile knobs from surfaces.* into tool surface config.
     *
     * @param  array<string, mixed>  $surfaces
     * @return array<string, mixed>
     */
    private static function toolSurfaceConfigFromSurfaces(array $surfaces): array
    {
        $tool = [];
        foreach (['agent', 'mcp'] as $name) {
            if (! isset($surfaces[$name]) || ! is_array($surfaces[$name])) {
                continue;
            }
            $surface = $surfaces[$name];
            $slice = [];
            foreach (['profiles', 'require_profile', 'max_tools_warn', 'max_tools_hard', 'max_tool_calls_per_turn'] as $key) {
                if (array_key_exists($key, $surface)) {
                    $slice[$key] = $surface[$key];
                }
            }
            if ($slice !== []) {
                $tool[$name] = $slice;
            }
        }

        return $tool;
    }

    /**
     * Resolve a TableGateway for a database-backed store.
     *
     * Host-provided $gateway wins (unit isolation / custom backends). Otherwise builds
     * {@see QueryTableGateway} for $table using $connection. Never substitutes ArrayTableGateway.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, string>  $columnMap
     */
    public static function makeDatabaseTableGateway(
        string $table,
        array $config = [],
        ?TableGateway $gateway = null,
        ?ConnectionInterface $connection = null,
        array $columnMap = [],
    ): TableGateway {
        if ($gateway !== null) {
            return $gateway;
        }

        // Named connection resolution lives in CapabilitiesServiceProvider (db manager).
        // Pure factories require an injected ConnectionInterface — never invent ArrayTableGateway.
        if ($connection === null) {
            throw BootException::missingDatabaseConnection($table);
        }

        return new QueryTableGateway(
            $connection,
            $table,
            columnMap: $columnMap,
        );
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function makeIdempotencyStore(
        array $config = [],
        ?TableGateway $gateway = null,
        ?ConnectionInterface $connection = null,
    ): IdempotencyStore {
        $full = $config === [] ? CapabilitiesConfig::defaults() : $config;
        $resolved = self::resolve($full);
        $driver = $resolved['drivers']['idempotency']['resolved'];
        $clock = new SystemClock;

        return match ($driver) {
            'memory' => new InMemoryIdempotencyStore($clock),
            'database' => new DatabaseIdempotencyStore(
                self::makeDatabaseTableGateway(
                    MigrationCatalog::TABLE_IDEMPOTENCY,
                    $full,
                    $gateway,
                    $connection,
                ),
                $clock,
            ),
            default => throw BootException::unknownDriver('idempotency.driver', $driver),
        };
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function makeApprovalManager(
        array $config = [],
        ?TableGateway $gateway = null,
        ?ConnectionInterface $connection = null,
    ): ApprovalManager {
        $full = $config === [] ? CapabilitiesConfig::defaults() : $config;
        $resolved = self::resolve($full);
        $driver = $resolved['drivers']['approval_store']['resolved'];
        $approvalConfig = (array) ($full['approval'] ?? []);
        $clock = new SystemClock;

        if ($driver === 'memory') {
            return ApprovalManager::inMemory($clock)->withConfig($approvalConfig);
        }

        if ($driver === 'database') {
            $store = new DatabaseApprovalStore(
                self::makeDatabaseTableGateway(
                    MigrationCatalog::TABLE_APPROVALS,
                    $full,
                    $gateway,
                    $connection,
                    self::APPROVAL_COLUMN_MAP,
                ),
                $clock,
            );

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
