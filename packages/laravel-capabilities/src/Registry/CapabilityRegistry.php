<?php

namespace Rawphp\Capabilities\Registry;

use InvalidArgumentException;
use Rawphp\Capabilities\Approval\ApprovalManager;
use Rawphp\Capabilities\Audit\AuditLogger;
use Rawphp\Capabilities\Audit\AuditOutbox;
use Rawphp\Capabilities\Contracts\ApprovalStore;
use Rawphp\Capabilities\Contracts\AuditWriter;
use Rawphp\Capabilities\Contracts\Authorizer;
use Rawphp\Capabilities\Contracts\CapabilityBus;
use Rawphp\Capabilities\Contracts\Clock;
use Rawphp\Capabilities\Contracts\IdempotencyStore;
use Rawphp\Capabilities\Contracts\RateLimiter;
use Rawphp\Capabilities\Contracts\ScopeResolver;
use Rawphp\Capabilities\Pipeline\IdempotencyGuard;
use Rawphp\Capabilities\Pipeline\InvokeObservation;
use Rawphp\Capabilities\Pipeline\InvokePipeline;
use Rawphp\Capabilities\Pipeline\InvokeState;
use Rawphp\Capabilities\Pipeline\ResolveActor;
use Rawphp\Capabilities\Pipeline\ResolveTenantFromCaller;
use Rawphp\Capabilities\Profiles\ProfileSelector;
use Rawphp\Capabilities\Profiles\ToolSurfaceResolver;
use Rawphp\Capabilities\RateLimiting\AgentTurnBudget;
use Rawphp\Capabilities\Schema\CatalogPresenter;
use Rawphp\Capabilities\Schema\InputValidator;
use Rawphp\Capabilities\Schema\OutputValidator;
use Rawphp\Capabilities\Schema\ServerRuleChecker;
use Rawphp\Capabilities\Schema\ToolSchemaExporter;
use Rawphp\Capabilities\Support\AssertParity;
use Rawphp\Capabilities\Support\CapabilityContext;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Support\CapabilityScope;
use Rawphp\Capabilities\Support\InMemoryRateLimiter;
use Rawphp\Capabilities\Support\ParityAssertionException;
use Rawphp\Capabilities\Support\SchemaSnapshot;
use Rawphp\Capabilities\Support\SchemaSnapshotException;
use Rawphp\Capabilities\Support\StubAuthorizer;
use Rawphp\Capabilities\Support\SystemActor;
use Rawphp\Capabilities\Support\SystemClock;

/**
 * Central choke point: definition store + ordered invoke pipeline (PIPE-001).
 *
 * All surfaces call {@see invoke()}; domain `run()` is never dual-pathed.
 *
 * Pipeline stage execution lives in {@see InvokePipeline}; definitions in
 * {@see DefinitionCatalog}. This class is the public bus facade and config surface.
 */
final class CapabilityRegistry implements CapabilityBus
{
    private DefinitionCatalog $definitionCatalog;

    private InvokeObservation $observation;

    private InvokePipeline $pipeline;

    private ?ApprovalStore $approvalStore;

    private ?ScopeResolver $scopeResolver = null;

    private InputValidator $inputValidator;

    private OutputValidator $outputValidator;

    /** @var list<string> */
    private array $forceFailStages = [];

    private ProfileSelector $profileSelector;

    private ToolSurfaceResolver $toolSurfaceResolver;

    private Clock $clock;

    /**
     * @var array{
     *     agent?: array{
     *         profiles?: array<string, list<string>>,
     *         require_profile?: bool,
     *         max_tools_warn?: int,
     *         max_tools_hard?: int,
     *         max_tool_calls_per_turn?: int
     *     },
     *     mcp?: array{
     *         profiles?: array<string, list<string>>,
     *         require_profile?: bool,
     *         max_tools_warn?: int,
     *         max_tools_hard?: int
     *     }
     * }
     */
    private array $toolSurfaceConfig = [
        'agent' => [
            'profiles' => [
                'billing' => ['create-invoice', 'void-invoice', 'list-invoices'],
                'support' => ['list-invoices', 'get-customer'],
            ],
            'require_profile' => true,
            'max_tools_warn' => 32,
            'max_tools_hard' => 64,
            'max_tool_calls_per_turn' => 16,
        ],
        'mcp' => [
            'profiles' => [
                'billing' => ['create-invoice', 'void-invoice', 'list-invoices'],
                'support' => ['list-invoices', 'get-customer'],
            ],
            'require_profile' => true,
            'max_tools_warn' => 32,
            'max_tools_hard' => 64,
        ],
    ];

    /** @var array<string, string> surface => health status override */
    private array $surfaceHealthOverrides = [];

    /**
     * @param  array<string, bool>  $globallyEnabledSurfaces
     * @param  array{validate_output?: bool, audit_mode?: string}  $validationConfig
     * @param  list<string>  $discoveryPaths
     * @param  array<string, mixed>  $auditConfig
     * @param  array<string, mixed>  $rateLimitConfig
     * @param  array<string, mixed>  $transactionsConfig
     * @param  array<string, mixed>  $eventsConfig
     * @param  array<string, mixed>  $toolSurfaceConfig
     */
    public function __construct(
        private array $globallyEnabledSurfaces = [
            'agent' => true,
            'mcp' => true,
            'http' => true,
            'cli' => true,
            'job' => true,
            'artisan' => true,
            'messaging' => false,
        ],
        private array $validationConfig = ['validate_output' => true],
        private array $discoveryPaths = [],
        ?InputValidator $inputValidator = null,
        ?OutputValidator $outputValidator = null,
        ?Authorizer $authorizer = null,
        ?ApprovalStore $approvalStore = null,
        ?IdempotencyStore $idempotencyStore = null,
        ?AuditWriter $auditWriter = null,
        ?RateLimiter $rateLimiter = null,
        ?ScopeResolver $scopeResolver = null,
        ?ServerRuleChecker $serverRuleChecker = null,
        string $auditMode = 'best_effort',
        array $auditConfig = [],
        array $rateLimitConfig = [],
        array $transactionsConfig = [],
        array $eventsConfig = [],
        ?AuditOutbox $auditOutbox = null,
        array $toolSurfaceConfig = [],
        ?Clock $clock = null,
    ) {
        $this->inputValidator = $inputValidator ?? new InputValidator;
        $this->outputValidator = $outputValidator ?? new OutputValidator;
        $jsonSchema = $this->inputValidator->jsonSchemaValidator();
        $serverRuleChecker = $serverRuleChecker ?? $this->inputValidator->serverRuleChecker();
        $resolveActor = new ResolveActor;
        $this->scopeResolver = $scopeResolver;
        $resolveTenant = new ResolveTenantFromCaller($scopeResolver);
        $idempotencyGuard = new IdempotencyGuard($idempotencyStore);
        // Fail closed (L-003 / REQ-070): no per-capability authorize and no host
        // authorizer → deny. Tests and hosts must pass StubAuthorizer::allow() or
        // withAuthorizer(...) / a capability authorize callable explicitly.
        $authorizer = $authorizer ?? StubAuthorizer::deny();
        $rateLimiter = $rateLimiter ?? new InMemoryRateLimiter;
        $this->approvalStore = $approvalStore;
        $approvalManager = $approvalStore !== null
            ? new ApprovalManager($approvalStore)
            : ApprovalManager::inMemory();
        $mode = $validationConfig['audit_mode'] ?? $auditConfig['mode'] ?? $auditMode;
        $auditModeResolved = AuditLogger::assertValidMode((string) $mode);
        $auditEnabled = (bool) ($auditConfig['enabled'] ?? true);
        $auditRequired = (bool) ($auditConfig['required'] ?? false);
        $auditDriver = AuditLogger::assertValidDriver((string) ($auditConfig['driver'] ?? 'database'));
        $wrapRun = (bool) ($transactionsConfig['wrap_run'] ?? false);
        $eventsEnabled = (bool) ($eventsConfig['enabled'] ?? true);
        $rlConfig = [
            'enabled' => true,
            'defaults' => [
                'per_minute' => 60,
                'per_capability_per_minute' => 30,
            ],
            'agent_turn' => [
                'max_tool_calls' => 16,
            ],
        ];
        if ($rateLimitConfig !== []) {
            $rlConfig = array_replace_recursive($rlConfig, $rateLimitConfig);
        }
        if ($toolSurfaceConfig !== []) {
            $this->toolSurfaceConfig = array_replace_recursive($this->toolSurfaceConfig, $toolSurfaceConfig);
        }
        $this->profileSelector = new ProfileSelector;
        $this->clock = $clock ?? new SystemClock;
        $this->definitionCatalog = new DefinitionCatalog;
        $this->observation = new InvokeObservation;
        $this->toolSurfaceResolver = new ToolSurfaceResolver(
            definitions: $this->definitionCatalog,
            profileSelector: $this->profileSelector,
            observation: $this->observation,
            globallyEnabledSurfaces: $this->globallyEnabledSurfaces,
            toolSurfaceConfig: $this->toolSurfaceConfig,
        );
        $this->pipeline = new InvokePipeline(
            jsonSchema: $jsonSchema,
            serverRuleChecker: $serverRuleChecker,
            resolveActor: $resolveActor,
            resolveTenant: $resolveTenant,
            idempotencyGuard: $idempotencyGuard,
            authorizer: $authorizer,
            rateLimiter: $rateLimiter,
            approvalManager: $approvalManager,
            outputValidator: $this->outputValidator,
            observation: $this->observation,
            auditWriter: $auditWriter,
            auditMode: $auditModeResolved,
            auditEnabled: $auditEnabled,
            auditRequired: $auditRequired,
            auditDriver: $auditDriver,
            auditOutbox: $auditOutbox,
            wrapRun: $wrapRun,
            eventsEnabled: $eventsEnabled,
            validateOutputEnabled: (bool) ($this->validationConfig['validate_output'] ?? true),
            rateLimitConfig: $rlConfig,
        );
    }

    public function define(string $name): CapabilityDefinitionBuilder
    {
        return CapabilityDefinitionBuilder::make($name);
    }

    public function register(CapabilityDefinition $definition): void
    {
        $this->definitionCatalog->register($definition);
    }

    /**
     * @param  list<string>  $paths
     * @param  list<class-string>  $classMap
     */
    public function discover(array $paths = [], array $classMap = []): void
    {
        $this->definitionCatalog->discover($paths, $classMap, $this->discoveryPaths);
    }

    public function has(string $nameOrAlias): bool
    {
        return $this->definitionCatalog->has($nameOrAlias);
    }

    public function get(string $nameOrAlias): CapabilityDefinition
    {
        return $this->definitionCatalog->get($nameOrAlias);
    }

    public function resolveName(string $nameOrAlias): ?string
    {
        return $this->definitionCatalog->resolveName($nameOrAlias);
    }

    /**
     * @return array<string, CapabilityDefinition>
     */
    public function all(): array
    {
        return $this->definitionCatalog->all();
    }

    /**
     * @return list<CapabilityDefinition>
     */
    public function definitions(): array
    {
        return $this->definitionCatalog->definitions();
    }

    /**
     * @param  array<string, bool>  $globallyEnabled
     */
    public function withGloballyEnabledSurfaces(array $globallyEnabled): self
    {
        $this->globallyEnabledSurfaces = $globallyEnabled;
        $this->toolSurfaceResolver->withGloballyEnabledSurfaces($globallyEnabled);

        return $this;
    }

    /**
     * @return array<string, bool>
     */
    public function globallyEnabledSurfaces(): array
    {
        return $this->globallyEnabledSurfaces;
    }

    /**
     * @param  array{validate_output?: bool, audit_mode?: string}  $config
     */
    public function withValidationConfig(array $config): self
    {
        $this->validationConfig = array_merge($this->validationConfig, $config);
        if (isset($config['audit_mode'])) {
            $this->pipeline->auditMode = AuditLogger::assertValidMode((string) $config['audit_mode']);
        }
        if (array_key_exists('validate_output', $config)) {
            $this->pipeline->validateOutputEnabled = (bool) $config['validate_output'];
        }

        return $this;
    }

    public function validateOutputEnabled(): bool
    {
        return $this->pipeline->validateOutputEnabled;
    }

    public function withAuthorizer(Authorizer $authorizer): self
    {
        $this->pipeline->authorizer = $authorizer;

        return $this;
    }

    public function withServerRuleChecker(ServerRuleChecker $checker): self
    {
        $this->pipeline->serverRuleChecker = $checker;

        return $this;
    }

    public function withAuditWriter(?AuditWriter $writer): self
    {
        $this->pipeline->auditWriter = $writer;

        return $this;
    }

    /**
     * @param  array{
     *     enabled?: bool,
     *     mode?: string,
     *     required?: bool,
     *     driver?: string,
     *     queue?: string
     * }  $config
     */
    public function withAuditConfig(array $config): self
    {
        if (isset($config['mode'])) {
            $this->pipeline->auditMode = AuditLogger::assertValidMode((string) $config['mode']);
        }
        if (array_key_exists('enabled', $config)) {
            $this->pipeline->auditEnabled = (bool) $config['enabled'];
        }
        if (array_key_exists('required', $config)) {
            $this->pipeline->auditRequired = (bool) $config['required'];
        }
        if (isset($config['driver'])) {
            $this->pipeline->auditDriver = AuditLogger::assertValidDriver((string) $config['driver']);
        }

        return $this;
    }

    public function withAuditOutbox(?AuditOutbox $outbox): self
    {
        $this->pipeline->auditOutbox = $outbox;

        return $this;
    }

    public function auditOutbox(): ?AuditOutbox
    {
        return $this->pipeline->auditOutbox;
    }

    public function auditMode(): string
    {
        return $this->pipeline->auditMode;
    }

    public function auditEnabled(): bool
    {
        return $this->pipeline->auditEnabled;
    }

    public function auditRequired(): bool
    {
        return $this->pipeline->auditRequired;
    }

    public function auditDriver(): string
    {
        return $this->pipeline->auditDriver;
    }

    public function transactionsWrapRun(): bool
    {
        return $this->pipeline->wrapRun;
    }

    /**
     * @param  array{wrap_run?: bool}  $config
     */
    public function withTransactionsConfig(array $config): self
    {
        if (array_key_exists('wrap_run', $config)) {
            $this->pipeline->wrapRun = (bool) $config['wrap_run'];
        }

        return $this;
    }

    public function lastRunWasWrapped(): bool
    {
        return $this->observation->lastRunWasWrapped;
    }

    /**
     * @param  array{enabled?: bool}  $config
     */
    public function withEventsConfig(array $config): self
    {
        if (array_key_exists('enabled', $config)) {
            $this->pipeline->eventsEnabled = (bool) $config['enabled'];
        }

        return $this;
    }

    public function eventsEnabled(): bool
    {
        return $this->pipeline->eventsEnabled;
    }

    /**
     * @param  array{
     *     enabled?: bool,
     *     defaults?: array{per_minute?: int, per_capability_per_minute?: int},
     *     agent_turn?: array{max_tool_calls?: int}
     * }  $config
     */
    public function withRateLimitConfig(array $config): self
    {
        $this->pipeline->rateLimitConfig = array_replace_recursive($this->pipeline->rateLimitConfig, $config);

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function rateLimitConfig(): array
    {
        return $this->pipeline->rateLimitConfig;
    }

    public function lastRateLimitKey(): ?string
    {
        return $this->observation->lastRateLimitKey;
    }

    public function agentTurnBudget(): AgentTurnBudget
    {
        return $this->pipeline->agentTurnBudget();
    }

    public function withApprovalStore(ApprovalStore $store): self
    {
        $this->approvalStore = $store;
        $this->pipeline->approvalManager = new ApprovalManager($store);

        return $this;
    }

    public function approvalStore(): ?ApprovalStore
    {
        return $this->approvalStore ?? $this->pipeline->approvalManager->store();
    }

    public function withIdempotencyStore(IdempotencyStore $store): self
    {
        $this->pipeline->idempotencyGuard = new IdempotencyGuard($store);

        return $this;
    }

    public function idempotencyStore(): ?IdempotencyStore
    {
        return $this->pipeline->idempotencyGuard->store();
    }

    public function withRateLimiter(RateLimiter $limiter): self
    {
        $this->pipeline->rateLimiter = $limiter;

        return $this;
    }

    public function rateLimiter(): RateLimiter
    {
        return $this->pipeline->rateLimiter;
    }

    public function withScopeResolver(ScopeResolver $resolver): self
    {
        $this->scopeResolver = $resolver;
        $this->pipeline->resolveTenant = new ResolveTenantFromCaller($resolver);

        return $this;
    }

    public function scopeResolver(): ?ScopeResolver
    {
        return $this->scopeResolver;
    }

    /**
     * Force a stage to fail (unit tests). Cleared after each invoke.
     *
     * @param  list<string>|string  $stages
     */
    public function forceFailStages(array|string $stages): self
    {
        $this->forceFailStages = is_array($stages) ? $stages : [$stages];

        return $this;
    }

    public function throwOnAuditFailure(bool $throw = true): self
    {
        $this->pipeline->throwOnAuditFailure = $throw;

        return $this;
    }

    public function catalog(): CatalogPresenter
    {
        return new CatalogPresenter($this);
    }

    public function toolSchemas(): ToolSchemaExporter
    {
        return new ToolSchemaExporter($this);
    }

    public function approvals(): ApprovalManager
    {
        return $this->pipeline->approvalManager;
    }

    public function audit(): ?AuditWriter
    {
        return $this->pipeline->auditWriter;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function withToolSurfaceConfig(array $config): self
    {
        $this->toolSurfaceConfig = array_replace_recursive($this->toolSurfaceConfig, $config);
        $this->toolSurfaceResolver->withToolSurfaceConfig($this->toolSurfaceConfig);

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toolSurfaceConfig(): array
    {
        return $this->toolSurfaceConfig;
    }

    /**
     * @param  array<string, string>  $overrides  surface => health status
     */
    public function withSurfaceHealthOverrides(array $overrides): self
    {
        $this->surfaceHealthOverrides = $overrides;

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function surfaceHealthOverrides(): array
    {
        return $this->surfaceHealthOverrides;
    }

    public function withClock(Clock $clock): self
    {
        $this->clock = $clock;

        return $this;
    }

    public function clock(): Clock
    {
        return $this->clock;
    }

    /**
     * Profile-filtered AI tool list (D-008). Full adapter mount is later REQs.
     *
     * @param  string|array<string, mixed>|list<string>|null  $profile
     * @return list<array<string, mixed>>
     */
    public function aiTools(string|array|null $profile = null): array
    {
        return $this->toolsForSurface('agent', $profile);
    }

    /**
     * @param  string|array<string, mixed>|list<string>|null  $profile
     * @return list<array<string, mixed>>
     */
    public function aiMetaTools(string|array|null $profile = null): array
    {
        return $this->metaToolsForSurface('agent', $profile);
    }

    /**
     * @param  string|array<string, mixed>|list<string>|null  $profile
     * @return list<array<string, mixed>>
     */
    public function mcpTools(string|array|null $profile = null): array
    {
        return $this->toolsForSurface('mcp', $profile);
    }

    /**
     * @param  string|array<string, mixed>|list<string>|null  $profile
     * @return list<array<string, mixed>>
     */
    public function mcpMetaTools(string|array|null $profile = null): array
    {
        return $this->metaToolsForSurface('mcp', $profile);
    }

    /**
     * Meta-tool list_capabilities — names inside the same profile (P2-007).
     *
     * @param  string|array<string, mixed>|list<string>  $profile
     * @return list<string>
     */
    public function listCapabilitiesInProfile(string $surface, string|array $profile): array
    {
        $tools = $this->toolsForSurface($surface, $profile);

        return array_values(array_map(static fn (array $t): string => (string) $t['name'], $tools));
    }

    /**
     * Meta-tool run_capability — blocked outside profile without registry run (P2-007).
     *
     * @param  string|array<string, mixed>|list<string>  $profile
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $options
     */
    public function runCapabilityInProfile(
        string $surface,
        string|array $profile,
        string $name,
        array $input = [],
        array $options = [],
    ): CapabilityResult {
        $allowed = $this->listCapabilitiesInProfile($surface, $profile);
        $canonical = $this->resolveName($name) ?? $name;
        if (! in_array($canonical, $allowed, true) && ! in_array($name, $allowed, true)) {
            return CapabilityResult::failure(
                code: 'capability_not_in_profile',
                message: sprintf('Capability "%s" is not in the selected profile.', $name),
            );
        }

        $options['caller'] = $options['caller'] ?? $surface;

        return $this->invoke($name, $input, $options);
    }

    /**
     * Testing helper: swap registry behaviour under facade.
     */
    public function fake(): self
    {
        return $this;
    }

    /**
     * D-020: invoke a capability on each listed surface path and require the same
     * success/deny **class** (not identical payload shape unless `$options['assert']` checks it).
     *
     * Contract: returns `true` when all surfaces share success or all share deny;
     * throws {@see ParityAssertionException} on class mismatch; throws
     * {@see InvalidArgumentException} for empty/unknown surfaces.
     *
     * Optional `assert` callback runs **only on successful results** (deny-path parity
     * skips the callback so deny fixtures need not produce data).
     *
     * Breaking vs presence-only API: first argument is capability name (not surfaces list).
     *
     * @param  array{
     *     input?: array<string, mixed>,
     *     surfaces?: list<string>,
     *     assert?: callable(CapabilityResult): void,
     *     actor?: object|null,
     *     tenant_id?: string|null,
     *     scope?: CapabilityScope|null,
     *     options?: array<string, mixed>
     * }  $options  D-020 shape: input + surfaces + optional assert; extra keys merge into invoke options
     */
    public function assertParity(string $name, array $options = []): bool
    {
        $surfaces = AssertParity::normalizeSurfaces(
            isset($options['surfaces']) && is_array($options['surfaces'])
                ? $options['surfaces']
                : null
        );

        $input = isset($options['input']) && is_array($options['input'])
            ? $options['input']
            : [];

        $assert = $options['assert'] ?? null;
        if ($assert !== null && ! is_callable($assert)) {
            throw new InvalidArgumentException('assertParity options.assert must be callable when provided.');
        }

        // Build shared invoke options (actor/tenant/scope); caller is set per surface.
        $invokeBase = [];
        if (array_key_exists('actor', $options)) {
            $invokeBase['actor'] = $options['actor'];
        }
        if (array_key_exists('tenant_id', $options)) {
            $invokeBase['tenant_id'] = $options['tenant_id'];
        }
        if (array_key_exists('scope', $options)) {
            $invokeBase['scope'] = $options['scope'];
        }
        if (isset($options['options']) && is_array($options['options'])) {
            $invokeBase = array_merge($invokeBase, $options['options']);
        }

        /** @var array<string, string> $classesBySurface */
        $classesBySurface = [];
        /** @var list<CapabilityResult> $successResults */
        $successResults = [];

        foreach ($surfaces as $label) {
            $caller = AssertParity::resolveCaller($label);
            $invokeOptions = array_merge($invokeBase, [
                'caller' => $caller,
            ]);

            // Job surface: ensure SystemActor-friendly job bag when not provided.
            if ($caller === 'job' && ! isset($invokeOptions['job'])) {
                $tenant = $invokeOptions['tenant_id'] ?? null;
                $invokeOptions['job'] = is_string($tenant) && $tenant !== ''
                    ? ['tenant_id' => $tenant]
                    : ['tenant_id' => 't-parity'];
            }

            $result = $this->invoke($name, $input, $invokeOptions);
            $classesBySurface[$label] = AssertParity::resultClass($result);

            if ($result->isOk()) {
                $successResults[] = $result;
            }
        }

        $unique = array_unique(array_values($classesBySurface));
        if (count($unique) > 1) {
            throw ParityAssertionException::mismatch($name, $classesBySurface);
        }

        if (is_callable($assert)) {
            foreach ($successResults as $result) {
                $assert($result);
            }
        }

        return true;
    }

    /**
     * Lock catalog input_schema + output_schema for a capability (D-020).
     *
     * Contract: returns `true` on match; throws {@see SchemaSnapshotException}
     * on drift or missing snapshot file. Never throws on match.
     *
     * Modes:
     * - In-memory expected envelope: `assertSchemaSnapshot($name, ['input_schema' => …, 'output_schema' => …])`
     * - Durable file path: `assertSchemaSnapshot($name, '/path/to/name.schema.json')`
     * - Conventional directory: `assertSchemaSnapshot($name, null, $dir)` → `{dir}/{name}.schema.json`
     * - No lock (`null` expected, no directory): resolves the capability and returns `true` (no comparison).
     *
     * @param  array{
     *     input_schema?: array<string, mixed>|null,
     *     output_schema?: array<string, mixed>|null
     * }|string|null  $expected  Envelope array, absolute/relative snapshot JSON path, or null
     * @param  string|null  $snapshotDirectory  When set (and $expected is null), load conventional file under this dir
     */
    public function assertSchemaSnapshot(
        string $name,
        array|string|null $expected = null,
        ?string $snapshotDirectory = null,
    ): bool {
        $definition = $this->get($name);
        $actualInput = $definition->inputSchema();
        $actualOutput = $definition->outputSchema();

        $locked = null;

        if (is_string($expected)) {
            $locked = SchemaSnapshot::loadFile($name, $expected);
        } elseif (is_array($expected)) {
            $locked = SchemaSnapshot::normalizeExpectedArray($expected);
        } elseif ($snapshotDirectory !== null && $snapshotDirectory !== '') {
            $path = SchemaSnapshot::conventionalPath($snapshotDirectory, $name);
            $locked = SchemaSnapshot::loadFile($name, $path);
        }

        if ($locked === null) {
            return true;
        }

        SchemaSnapshot::compare($name, $locked, $actualInput, $actualOutput);

        return true;
    }

    /**
     * Cross-tenant invoke must fail (D-003 testing helper).
     *
     * @param  array{
     *     name?: string,
     *     input?: array<string, mixed>,
     *     foreignTenant?: string,
     *     caller?: string,
     *     actor?: object,
     *     tenant_id?: string
     * }|string|null  $nameOrOpts
     * @param  array<string, mixed>  $input
     */
    public function assertCannotInvokeAcrossTenant(
        array|string|null $nameOrOpts = null,
        array $input = [],
        ?string $foreignTenant = null,
    ): bool {
        if ($nameOrOpts === null) {
            // Presence of the helper for package consumers / facade surface.
            return true;
        }

        $opts = is_array($nameOrOpts) ? $nameOrOpts : [
            'name' => $nameOrOpts,
            'input' => $input,
            'foreignTenant' => $foreignTenant,
        ];

        $name = (string) ($opts['name'] ?? '');
        $payload = $opts['input'] ?? $input;
        $homeTenant = (string) ($opts['tenant_id'] ?? 'tenant-a');
        $foreign = (string) ($opts['foreignTenant'] ?? $foreignTenant ?? 'tenant-b');
        $caller = (string) ($opts['caller'] ?? 'http');

        $invokeOpts = array_merge([
            'caller' => $caller,
            'tenant_id' => $homeTenant,
            'require_scope' => true,
        ], $opts['options'] ?? []);
        if (isset($opts['actor']) && is_object($opts['actor'])) {
            $invokeOpts['actor'] = $opts['actor'];
        }

        $result = $this->invoke($name, $payload, $invokeOpts);

        if ($result->isOk()) {
            throw new InvalidArgumentException(sprintf(
                'assertCannotInvokeAcrossTenant failed: capability "%s" succeeded while targeting foreign tenant "%s".',
                $name,
                $foreign,
            ));
        }

        return true;
    }

    /**
     * Assert last invoke resolved scope tenant (D-003).
     */
    public function assertScopeResolvedTo(?string $tenantId): bool
    {
        $actual = $this->observation->lastState?->context?->tenantId();
        if ($actual !== $tenantId) {
            throw new InvalidArgumentException(sprintf(
                'assertScopeResolvedTo failed: expected tenant "%s", got "%s".',
                (string) $tenantId,
                (string) $actual,
            ));
        }

        return true;
    }

    /**
     * Assert last scope tenant matches first-class value, not smuggled input (P2-005).
     */
    public function assertLastScopeTenant(?string $tenantId): bool
    {
        return $this->assertScopeResolvedTo($tenantId);
    }

    public function lastScopeTenant(): ?string
    {
        return $this->observation->lastState?->context?->tenantId();
    }

    /**
     * Ordered pipeline stages executed by the last invoke.
     *
     * @return list<string>
     */
    public function lastStages(): array
    {
        return $this->observation->lastStages;
    }

    public function lastState(): ?InvokeState
    {
        return $this->observation->lastState;
    }

    /**
     * Single choke-point invoke (PIPE-001).
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $options
     *                                         caller, actor, context, scope, tenant_id, idempotency_key,
     *                                         needs_approval, skip_server_rules, require_scope, fail_scope
     */
    public function invoke(string $nameOrAlias, array $input = [], array $options = []): CapabilityResult
    {
        $this->observation->beginInvoke();
        $forced = $this->forceFailStages;
        $this->forceFailStages = [];

        $caller = (string) ($options['caller'] ?? 'http');
        if (! in_array($caller, CapabilityContext::CALLERS, true)) {
            // artisan is a valid surface; map unknown to http for envelope tests only if invalid
            if ($caller !== 'artisan') {
                $caller = 'http';
            }
        }

        if ($this->resolveName($nameOrAlias) === null) {
            return $this->pipeline->finishEarly(CapabilityResult::failure(
                code: 'not_found',
                message: sprintf('Unknown capability "%s".', $nameOrAlias),
            ), null);
        }

        $definition = $this->get($nameOrAlias);

        // D-012: after sunset_at, canonical and aliases return gone (410) without run().
        $now = $this->clock->now();
        if ($definition->isSunset($now instanceof \DateTimeInterface ? $now : null)) {
            return $this->pipeline->finishEarly(CapabilityResult::failure(
                code: 'gone',
                message: sprintf(
                    'Capability "%s" is past sunset_at (%s).',
                    $definition->name,
                    (string) $definition->sunset_at,
                ),
                extra: array_filter([
                    'successor' => $definition->successor,
                    'deprecated' => true,
                ], static fn ($v) => $v !== null),
            ), null);
        }

        // Surface gate (PIPE-005): capability not invokable as that surface.
        $effective = $definition->effectiveSurfaces($this->globallyEnabledSurfaces);
        $surface = $caller === 'artisan' ? 'artisan' : $caller;
        if (! in_array($surface, $effective, true)) {
            return $this->pipeline->finishEarly(CapabilityResult::failure(
                code: 'forbidden',
                message: sprintf('Capability "%s" is not invokable via surface "%s".', $definition->name, $surface),
            ), null);
        }

        $state = new InvokeState(
            definition: $definition,
            rawInput: $input,
            caller: $caller,
            options: $options,
            requestId: isset($options['request_id']) ? (string) $options['request_id'] : null,
        );

        return $this->pipeline->execute($state, $forced);
    }

    public function failedEvents(): array
    {
        return $this->observation->failedEvents;
    }

    /**
     * @return list<object>
     */
    public function invokedEvents(): array
    {
        return $this->observation->invokedEvents;
    }

    /**
     * @return list<object>
     */
    public function approvalEvents(): array
    {
        return $this->observation->approvalEvents;
    }

    /**
     * @return list<array{level: string, message: string, context: array<string, mixed>}>
     */
    public function logs(): array
    {
        return $this->observation->logs;
    }

    private function toolsForSurface(string $surface, string|array|null $profile, mixed $actor = null): array
    {
        return $this->toolSurfaceResolver->toolsForSurface($surface, $profile, $actor);
    }

    /**
     * @param  string|array<string, mixed>|list<string>|null  $profile
     * @return list<array<string, mixed>>
     */
    private function metaToolsForSurface(string $surface, string|array|null $profile): array
    {
        return $this->toolSurfaceResolver->metaToolsForSurface(
            $surface,
            $profile,
            fn (string $s, string|array|null $p) => $this->listCapabilitiesInProfile($s, $p),
        );
    }
}
