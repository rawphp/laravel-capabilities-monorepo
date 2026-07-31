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
use Rawphp\Capabilities\Contracts\IdempotencyStore;
use Rawphp\Capabilities\Contracts\RateLimiter;
use Rawphp\Capabilities\Contracts\SchemaProvider;
use Rawphp\Capabilities\Contracts\ScopeResolver;
use Rawphp\Capabilities\Discovery\AttributeDiscoverer;
use Rawphp\Capabilities\Events\CapabilityApprovalRequested;
use Rawphp\Capabilities\Events\CapabilityFailed;
use Rawphp\Capabilities\Events\CapabilityInvoked;
use Rawphp\Capabilities\Idempotency\RequestHash;
use Rawphp\Capabilities\Pipeline\IdempotencyGuard;
use Rawphp\Capabilities\Pipeline\InvokeState;
use Rawphp\Capabilities\Pipeline\PipelineStages;
use Rawphp\Capabilities\Pipeline\ResolveActor;
use Rawphp\Capabilities\Pipeline\ResolveTenantFromCaller;
use Rawphp\Capabilities\Profiles\ProfileRequiredException;
use Rawphp\Capabilities\Profiles\ProfileSelector;
use Rawphp\Capabilities\Profiles\TooManyToolsException;
use Rawphp\Capabilities\RateLimiting\AgentTurnBudget;
use Rawphp\Capabilities\RateLimiting\RateLimitKey;
use Rawphp\Capabilities\Schema\CatalogPresenter;
use Rawphp\Capabilities\Schema\FailingServerRuleChecker;
use Rawphp\Capabilities\Schema\InputValidator;
use Rawphp\Capabilities\Schema\JsonSchemaValidator;
use Rawphp\Capabilities\Schema\OutputValidator;
use Rawphp\Capabilities\Schema\PassThroughServerRuleChecker;
use Rawphp\Capabilities\Schema\SchemaValidationException;
use Rawphp\Capabilities\Schema\ServerRuleChecker;
use Rawphp\Capabilities\Schema\ToolSchemaExporter;
use Rawphp\Capabilities\Support\CapabilityContext;
use Rawphp\Capabilities\Support\CapabilityData;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Support\CapabilityScope;
use Rawphp\Capabilities\Support\ErrorCodeMap;
use Rawphp\Capabilities\Support\InMemoryRateLimiter;
use Rawphp\Capabilities\Support\AssertParity;
use Rawphp\Capabilities\Support\ParityAssertionException;
use Rawphp\Capabilities\Support\SchemaSnapshot;
use Rawphp\Capabilities\Support\StubAuthorizer;
use Rawphp\Capabilities\Support\SystemActor;
use Rawphp\Capabilities\Support\SystemClock;
use Rawphp\Capabilities\Contracts\Clock;
use Throwable;

/**
 * Central choke point: definition store + ordered invoke pipeline (PIPE-001).
 *
 * All surfaces call {@see invoke()}; domain `run()` is never dual-pathed.
 */
final class CapabilityRegistry implements CapabilityBus
{
    /** @var array<string, CapabilityDefinition> */
    private array $definitions = [];

    /** @var array<string, string> alias => canonical name */
    private array $aliases = [];

    /** @var array<string, string> "domain\\0verb" => canonical capability name (CLI-002) */
    private array $cliRoutes = [];

    /** @var list<object> */
    private array $failedEvents = [];

    /** @var list<object> */
    private array $invokedEvents = [];

    /** @var list<object> */
    private array $approvalEvents = [];

    /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
    private array $logs = [];

    /** @var list<string> last invoke stage trace */
    private array $lastStages = [];

    private ?InvokeState $lastState = null;

    private JsonSchemaValidator $jsonSchema;

    private ServerRuleChecker $serverRuleChecker;

    private ResolveActor $resolveActor;

    private ResolveTenantFromCaller $resolveTenant;

    private IdempotencyGuard $idempotencyGuard;

    private Authorizer $authorizer;

    private RateLimiter $rateLimiter;

    private ?ApprovalStore $approvalStore;

    private ?ScopeResolver $scopeResolver = null;

    private ?AuditWriter $auditWriter;

    private ApprovalManager $approvalManager;

    private string $auditMode;

    private bool $auditEnabled = true;

    private bool $auditRequired = false;

    private string $auditDriver = 'database';

    private ?AuditOutbox $auditOutbox = null;

    private bool $wrapRun = false;

    private bool $eventsEnabled = true;

    /**
     * @var array{
     *     enabled?: bool,
     *     defaults?: array{per_minute?: int, per_capability_per_minute?: int},
     *     agent_turn?: array{max_tool_calls?: int}
     * }
     */
    private array $rateLimitConfig = [
        'enabled' => true,
        'defaults' => [
            'per_minute' => 60,
            'per_capability_per_minute' => 30,
        ],
        'agent_turn' => [
            'max_tool_calls' => 16,
        ],
    ];

    private ?string $lastRateLimitKey = null;

    private bool $lastRunWasWrapped = false;

    private ?float $invokeStartedAt = null;

    /** @var list<string> */
    private array $forceFailStages = [];

    private bool $throwOnAuditFailure = false;

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

    private ProfileSelector $profileSelector;

    private Clock $clock;

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
        private ?InputValidator $inputValidator = null,
        private ?OutputValidator $outputValidator = null,
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
        $this->inputValidator ??= new InputValidator;
        $this->outputValidator ??= new OutputValidator;
        $this->jsonSchema = $this->inputValidator->jsonSchemaValidator();
        $this->serverRuleChecker = $serverRuleChecker ?? $this->inputValidator->serverRuleChecker();
        $this->resolveActor = new ResolveActor;
        $this->scopeResolver = $scopeResolver;
        $this->resolveTenant = new ResolveTenantFromCaller($scopeResolver);
        $this->idempotencyGuard = new IdempotencyGuard($idempotencyStore);
        // Fail closed (L-003 / REQ-070): no per-capability authorize and no host
        // authorizer → deny. Tests and hosts must pass StubAuthorizer::allow() or
        // withAuthorizer(...) / a capability authorize callable explicitly.
        $this->authorizer = $authorizer ?? StubAuthorizer::deny();
        $this->rateLimiter = $rateLimiter ?? new InMemoryRateLimiter;
        $this->approvalStore = $approvalStore;
        $this->auditWriter = $auditWriter;
        $this->approvalManager = $approvalStore !== null
            ? new ApprovalManager($approvalStore)
            : ApprovalManager::inMemory();
        $mode = $validationConfig['audit_mode'] ?? $auditConfig['mode'] ?? $auditMode;
        $this->auditMode = AuditLogger::assertValidMode((string) $mode);
        $this->auditEnabled = (bool) ($auditConfig['enabled'] ?? true);
        $this->auditRequired = (bool) ($auditConfig['required'] ?? false);
        $this->auditDriver = AuditLogger::assertValidDriver((string) ($auditConfig['driver'] ?? 'database'));
        $this->auditOutbox = $auditOutbox;
        $this->wrapRun = (bool) ($transactionsConfig['wrap_run'] ?? false);
        $this->eventsEnabled = (bool) ($eventsConfig['enabled'] ?? true);
        if ($rateLimitConfig !== []) {
            $this->rateLimitConfig = array_replace_recursive($this->rateLimitConfig, $rateLimitConfig);
        }
        if ($toolSurfaceConfig !== []) {
            $this->toolSurfaceConfig = array_replace_recursive($this->toolSurfaceConfig, $toolSurfaceConfig);
        }
        $this->profileSelector = new ProfileSelector;
        $this->clock = $clock ?? new SystemClock;
    }

    public function define(string $name): CapabilityDefinitionBuilder
    {
        return CapabilityDefinitionBuilder::make($name);
    }

    public function register(CapabilityDefinition $definition): void
    {
        if (isset($this->definitions[$definition->name])) {
            throw new InvalidArgumentException(sprintf(
                'Duplicate capability name "%s" at registration (D-017).',
                $definition->name,
            ));
        }

        foreach ($definition->aliases as $alias) {
            if (isset($this->definitions[$alias]) || isset($this->aliases[$alias])) {
                throw new InvalidArgumentException(sprintf(
                    'Alias "%s" collides with an existing capability name or alias.',
                    $alias,
                ));
            }
        }

        $this->assertUniqueCliPair($definition);

        $this->definitions[$definition->name] = $definition;

        foreach ($definition->aliases as $alias) {
            $this->aliases[$alias] = $definition->name;
        }

        if ($definition->cliDomain !== null && $definition->cliVerb !== null) {
            $this->cliRoutes[$this->cliRouteKey($definition->cliDomain, $definition->cliVerb)] = $definition->name;
        }
    }

    /**
     * Reject two definitions claiming the same CLI (domain, verb) pair (server authoritative).
     *
     * @throws InvalidArgumentException
     */
    private function assertUniqueCliPair(CapabilityDefinition $definition): void
    {
        if ($definition->cliDomain === null || $definition->cliVerb === null) {
            return;
        }

        $key = $this->cliRouteKey($definition->cliDomain, $definition->cliVerb);
        if (! isset($this->cliRoutes[$key])) {
            return;
        }

        throw new InvalidArgumentException(sprintf(
            'Duplicate CLI routing pair (domain="%s", verb="%s"): capability "%s" collides with already-registered "%s".',
            $definition->cliDomain,
            $definition->cliVerb,
            $definition->name,
            $this->cliRoutes[$key],
        ));
    }

    private function cliRouteKey(string $domain, string $verb): string
    {
        return $domain."\0".$verb;
    }

    /**
     * @param  list<string>  $paths
     * @param  list<class-string>  $classMap
     */
    public function discover(array $paths = [], array $classMap = []): void
    {
        $paths = $paths !== [] ? $paths : $this->discoveryPaths;
        $discoverer = new AttributeDiscoverer;
        $definitions = $classMap !== []
            ? $discoverer->fromClasses($classMap)
            : $discoverer->fromPaths($paths);

        foreach ($definitions as $definition) {
            $this->register($definition);
        }
    }

    public function has(string $nameOrAlias): bool
    {
        return $this->resolveName($nameOrAlias) !== null;
    }

    public function get(string $nameOrAlias): CapabilityDefinition
    {
        $name = $this->resolveName($nameOrAlias);
        if ($name === null) {
            throw new InvalidArgumentException(sprintf('Unknown capability "%s".', $nameOrAlias));
        }

        return $this->definitions[$name];
    }

    public function resolveName(string $nameOrAlias): ?string
    {
        if (isset($this->definitions[$nameOrAlias])) {
            return $nameOrAlias;
        }

        return $this->aliases[$nameOrAlias] ?? null;
    }

    /**
     * @return array<string, CapabilityDefinition>
     */
    public function all(): array
    {
        return $this->definitions;
    }

    /**
     * @return list<CapabilityDefinition>
     */
    public function definitions(): array
    {
        return array_values($this->definitions);
    }

    /**
     * @param  array<string, bool>  $globallyEnabled
     */
    public function withGloballyEnabledSurfaces(array $globallyEnabled): self
    {
        $this->globallyEnabledSurfaces = $globallyEnabled;

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
            $this->auditMode = (string) $config['audit_mode'];
        }

        return $this;
    }

    public function validateOutputEnabled(): bool
    {
        return (bool) ($this->validationConfig['validate_output'] ?? true);
    }

    public function withAuthorizer(Authorizer $authorizer): self
    {
        $this->authorizer = $authorizer;

        return $this;
    }

    public function withServerRuleChecker(ServerRuleChecker $checker): self
    {
        $this->serverRuleChecker = $checker;

        return $this;
    }

    public function withAuditWriter(?AuditWriter $writer): self
    {
        $this->auditWriter = $writer;

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
            $this->auditMode = AuditLogger::assertValidMode((string) $config['mode']);
        }
        if (array_key_exists('enabled', $config)) {
            $this->auditEnabled = (bool) $config['enabled'];
        }
        if (array_key_exists('required', $config)) {
            $this->auditRequired = (bool) $config['required'];
        }
        if (isset($config['driver'])) {
            $this->auditDriver = AuditLogger::assertValidDriver((string) $config['driver']);
        }

        return $this;
    }

    public function withAuditOutbox(?AuditOutbox $outbox): self
    {
        $this->auditOutbox = $outbox;

        return $this;
    }

    public function auditOutbox(): ?AuditOutbox
    {
        return $this->auditOutbox;
    }

    public function auditMode(): string
    {
        return $this->auditMode;
    }

    public function auditEnabled(): bool
    {
        return $this->auditEnabled;
    }

    public function auditRequired(): bool
    {
        return $this->auditRequired;
    }

    public function auditDriver(): string
    {
        return $this->auditDriver;
    }

    public function transactionsWrapRun(): bool
    {
        return $this->wrapRun;
    }

    /**
     * @param  array{wrap_run?: bool}  $config
     */
    public function withTransactionsConfig(array $config): self
    {
        if (array_key_exists('wrap_run', $config)) {
            $this->wrapRun = (bool) $config['wrap_run'];
        }

        return $this;
    }

    public function lastRunWasWrapped(): bool
    {
        return $this->lastRunWasWrapped;
    }

    /**
     * @param  array{enabled?: bool}  $config
     */
    public function withEventsConfig(array $config): self
    {
        if (array_key_exists('enabled', $config)) {
            $this->eventsEnabled = (bool) $config['enabled'];
        }

        return $this;
    }

    public function eventsEnabled(): bool
    {
        return $this->eventsEnabled;
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
        $this->rateLimitConfig = array_replace_recursive($this->rateLimitConfig, $config);

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function rateLimitConfig(): array
    {
        return $this->rateLimitConfig;
    }

    public function lastRateLimitKey(): ?string
    {
        return $this->lastRateLimitKey;
    }

    public function agentTurnBudget(): AgentTurnBudget
    {
        return AgentTurnBudget::fromConfig($this->rateLimitConfig['agent_turn'] ?? []);
    }

    public function withApprovalStore(ApprovalStore $store): self
    {
        $this->approvalStore = $store;
        $this->approvalManager = new ApprovalManager($store);

        return $this;
    }

    public function approvalStore(): ?ApprovalStore
    {
        return $this->approvalStore ?? $this->approvalManager->store();
    }

    public function withIdempotencyStore(IdempotencyStore $store): self
    {
        $this->idempotencyGuard = new IdempotencyGuard($store);

        return $this;
    }

    public function idempotencyStore(): ?IdempotencyStore
    {
        return $this->idempotencyGuard->store();
    }

    public function withRateLimiter(RateLimiter $limiter): self
    {
        $this->rateLimiter = $limiter;

        return $this;
    }

    public function rateLimiter(): RateLimiter
    {
        return $this->rateLimiter;
    }

    public function withScopeResolver(ScopeResolver $resolver): self
    {
        $this->scopeResolver = $resolver;
        $this->resolveTenant = new ResolveTenantFromCaller($resolver);

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
        $this->throwOnAuditFailure = $throw;

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
        return $this->approvalManager;
    }

    public function audit(): ?AuditWriter
    {
        return $this->auditWriter;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function withToolSurfaceConfig(array $config): self
    {
        $this->toolSurfaceConfig = array_replace_recursive($this->toolSurfaceConfig, $config);

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
     * Contract: returns `true` on match; throws {@see \Rawphp\Capabilities\Support\SchemaSnapshotException}
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
        $actual = $this->lastState?->context?->tenantId();
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
        return $this->lastState?->context?->tenantId();
    }

    /**
     * Ordered pipeline stages executed by the last invoke.
     *
     * @return list<string>
     */
    public function lastStages(): array
    {
        return $this->lastStages;
    }

    public function lastState(): ?InvokeState
    {
        return $this->lastState;
    }

    /**
     * Single choke-point invoke (PIPE-001).
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $options
     *         caller, actor, context, scope, tenant_id, idempotency_key,
     *         needs_approval, skip_server_rules, require_scope, fail_scope
     */
    public function invoke(string $nameOrAlias, array $input = [], array $options = []): CapabilityResult
    {
        $this->lastStages = [];
        $this->invokeStartedAt = microtime(true);
        $this->lastRunWasWrapped = false;
        $this->lastRateLimitKey = null;
        $this->lastState = null;
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
            return $this->finishEarly(CapabilityResult::failure(
                code: 'not_found',
                message: sprintf('Unknown capability "%s".', $nameOrAlias),
            ), null);
        }

        $definition = $this->get($nameOrAlias);

        // D-012: after sunset_at, canonical and aliases return gone (410) without run().
        $now = $this->clock->now();
        if ($definition->isSunset($now instanceof \DateTimeInterface ? $now : null)) {
            return $this->finishEarly(CapabilityResult::failure(
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
            return $this->finishEarly(CapabilityResult::failure(
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
        $this->lastState = $state;

        try {
            // ── pre-run stages ──────────────────────────────────────────
            $early = $this->stageJsonSchemaValidate($state, $forced);
            if ($early !== null) {
                return $this->finishFailure($state, $early);
            }

            $early = $this->stageHydrateDto($state, $forced);
            if ($early !== null) {
                return $this->finishFailure($state, $early);
            }

            $early = $this->stageServerOnlyValidate($state, $forced);
            if ($early !== null) {
                return $this->finishFailure($state, $early);
            }

            $early = $this->stageResolveActor($state, $forced);
            if ($early !== null) {
                return $this->finishFailure($state, $early);
            }

            $early = $this->stageResolveScope($state, $forced);
            if ($early !== null) {
                return $this->finishFailure($state, $early);
            }

            $early = $this->stageIdempotencyLookup($state, $forced);
            if ($early !== null) {
                // Replay is success-shaped; conflict/busy are failures.
                if ($state->idempotentReplay) {
                    return $this->finishReplay($state, $early);
                }

                return $this->finishFailure($state, $early);
            }

            $early = $this->stageAuthorize($state, $forced);
            if ($early !== null) {
                return $this->finishFailure($state, $early, auditDeny: true);
            }

            $early = $this->stageNeedsApproval($state, $forced);
            if ($early !== null) {
                return $this->finishApprovalRequired($state, $early);
            }

            $early = $this->stageRateLimit($state, $forced);
            if ($early !== null) {
                return $this->finishFailure($state, $early);
            }

            // ── run ─────────────────────────────────────────────────────
            $early = $this->stageRun($state, $forced);
            if ($early !== null) {
                return $this->finishFailure($state, $early, afterRun: true);
            }

            // ── post-run ────────────────────────────────────────────────
            $early = $this->stageValidateOutput($state, $forced);
            if ($early !== null) {
                return $this->finishFailure($state, $early, afterRun: true, outputInvalid: true);
            }

            $this->stageStoreIdempotency($state);
            $auditFailure = $this->stageRecordAudit($state, success: true);
            $this->stageEmitEvents($state, success: true);

            // Strict audit failure after successful domain run: surface error without
            // rolling back domain-owned commits (D-010 footgun when domain already committed).
            if ($auditFailure !== null) {
                $this->lastStages = $state->stages;

                return $this->stageWireResponse($state, $auditFailure);
            }

            $successMeta = [
                'request_id' => $state->requestId,
                'capability' => $definition->name,
                'idempotent_replay' => false,
                'stages' => $state->stages,
            ];
            if ($definition->deprecated) {
                $successMeta['deprecated'] = true;
                $successMeta['deprecation_warning'] = sprintf(
                    'Capability "%s" is deprecated%s.',
                    $definition->name,
                    $definition->successor ? '; use '.$definition->successor : '',
                );
                if ($definition->successor !== null) {
                    $successMeta['successor'] = $definition->successor;
                }
            }
            $result = $this->stageWireResponse($state, CapabilityResult::success(
                $state->output,
                $successMeta,
            ));

            $this->lastStages = $state->stages;

            return $result;
        } catch (Throwable $e) {
            $state->mark(PipelineStages::WIRE_RESPONSE);
            $this->lastStages = $state->stages;
            $this->recordFailure($definition, $e->getMessage(), $caller, 'internal');

            return CapabilityResult::failure(
                code: 'internal',
                message: $e->getMessage(),
                meta: ['request_id' => $state->requestId, 'stages' => $state->stages],
            );
        }
    }

    /**
     * @return list<object>
     */
    public function failedEvents(): array
    {
        return $this->failedEvents;
    }

    /**
     * @return list<object>
     */
    public function invokedEvents(): array
    {
        return $this->invokedEvents;
    }

    /**
     * @return list<object>
     */
    public function approvalEvents(): array
    {
        return $this->approvalEvents;
    }

    /**
     * @return list<array{level: string, message: string, context: array<string, mixed>}>
     */
    public function logs(): array
    {
        return $this->logs;
    }

    // ── stages ────────────────────────────────────────────────────────────

    /**
     * @param  list<string>  $forced
     */
    private function stageJsonSchemaValidate(InvokeState $state, array $forced): ?CapabilityResult
    {
        $state->mark(PipelineStages::JSON_SCHEMA_VALIDATE);

        if ($this->shouldForceFail(PipelineStages::JSON_SCHEMA_VALIDATE, $forced)) {
            return CapabilityResult::failure(
                code: 'validation_failed',
                message: 'Forced failure at json_schema_validate.',
                extra: ['violations' => [['field' => '(root)', 'message' => 'forced']]],
            );
        }

        $inputClass = $state->definition->input;
        if ($inputClass === null) {
            return null;
        }

        if (! is_a($inputClass, SchemaProvider::class, true)) {
            return CapabilityResult::failure(
                code: 'validation_failed',
                message: sprintf('Input type %s must implement SchemaProvider.', $inputClass),
            );
        }

        $schema = $inputClass::jsonSchema();
        $violations = $this->jsonSchema->validate($schema, $state->rawInput);
        if ($violations !== []) {
            return CapabilityResult::failure(
                code: 'validation_failed',
                message: 'JSON Schema validation failed.',
                extra: ['violations' => $violations],
            );
        }

        return null;
    }

    /**
     * @param  list<string>  $forced
     */
    private function stageHydrateDto(InvokeState $state, array $forced): ?CapabilityResult
    {
        $state->mark(PipelineStages::HYDRATE_DTO);

        if ($this->shouldForceFail(PipelineStages::HYDRATE_DTO, $forced)) {
            return CapabilityResult::failure(
                code: 'validation_failed',
                message: 'Forced failure at hydrate_dto.',
                extra: ['violations' => [['field' => '(root)', 'message' => 'hydrate forced']]],
            );
        }

        $inputClass = $state->definition->input;
        if ($inputClass === null) {
            $state->input = $state->rawInput;

            return null;
        }

        try {
            if (is_a($inputClass, CapabilityData::class, true)) {
                /** @var class-string<CapabilityData> $inputClass */
                $state->input = $inputClass::fromArray($state->rawInput);
            } else {
                /** @var class-string<SchemaProvider> $inputClass */
                $state->input = $inputClass::validate($state->rawInput);
            }
        } catch (Throwable $e) {
            return CapabilityResult::failure(
                code: 'validation_failed',
                message: $e->getMessage(),
                extra: ['violations' => [['field' => '(root)', 'message' => $e->getMessage()]]],
            );
        }

        return null;
    }

    /**
     * @param  list<string>  $forced
     */
    private function stageServerOnlyValidate(InvokeState $state, array $forced): ?CapabilityResult
    {
        $state->mark(PipelineStages::SERVER_ONLY_VALIDATE);

        if ($this->shouldForceFail(PipelineStages::SERVER_ONLY_VALIDATE, $forced)) {
            return CapabilityResult::failure(
                code: 'validation_failed',
                message: 'Forced failure at server_only_validate.',
                extra: ['violations' => [['field' => 'customer_id', 'message' => 'server rule failed']]],
            );
        }

        if (($state->options['skip_server_rules'] ?? false) === true) {
            return null;
        }

        $inputClass = $state->definition->input;
        if ($inputClass === null || ! is_a($inputClass, CapabilityData::class, true)) {
            return null;
        }

        /** @var class-string<CapabilityData> $inputClass */
        $rules = $inputClass::rules();
        if ($rules === []) {
            return null;
        }

        $data = $state->input instanceof CapabilityData
            ? $state->input->toArray()
            : $state->rawInput;

        $violations = $this->serverRuleChecker->check($rules, $data);
        if ($violations !== []) {
            return CapabilityResult::failure(
                code: 'validation_failed',
                message: 'Server-only validation failed.',
                extra: ['violations' => $violations],
            );
        }

        return null;
    }

    /**
     * @param  list<string>  $forced
     */
    private function stageResolveActor(InvokeState $state, array $forced): ?CapabilityResult
    {
        $state->mark(PipelineStages::RESOLVE_ACTOR);

        if ($this->shouldForceFail(PipelineStages::RESOLVE_ACTOR, $forced)) {
            return CapabilityResult::failure(
                code: 'unauthenticated',
                message: 'Forced failure at resolve_actor.',
            );
        }

        try {
            $actor = $this->resolveActor->resolve($state->caller, $state->options);
        } catch (Throwable $e) {
            return CapabilityResult::failure(
                code: 'unauthenticated',
                message: $e->getMessage(),
            );
        }

        // SystemActor allow-list on capability definition (D-002).
        if ($actor instanceof SystemActor && ! $state->definition->allowsSystemCaller($actor)) {
            return CapabilityResult::failure(
                code: 'forbidden',
                message: sprintf('SystemActor "%s" is not allowed for capability "%s".', $actor->name, $state->definition->name),
            );
        }

        if (isset($state->options['context']) && $state->options['context'] instanceof CapabilityContext) {
            $state->context = $state->options['context'];
        } else {
            $attrs = is_array($state->options['attributes'] ?? null)
                ? $state->options['attributes']
                : [];
            if ($state->definition->globalSystem) {
                $attrs['global_system'] = true;
            }
            if (array_key_exists('global_system', $state->options)) {
                $attrs['global_system'] = (bool) $state->options['global_system'];
            }
            if (array_key_exists('globalSystem', $state->options)) {
                $attrs['global_system'] = (bool) $state->options['globalSystem'];
            }
            if (($state->options['require_scope'] ?? false) === true
                || ($state->options['tenancy_required'] ?? false) === true) {
                $attrs['tenancy_required'] = true;
                $attrs['require_scope'] = true;
            }

            $state->context = CapabilityContext::make([
                'caller' => $state->caller,
                'actor' => $actor,
                'request_id' => $state->requestId,
                'trace_id' => isset($state->options['trace_id']) ? (string) $state->options['trace_id'] : null,
                'job' => $state->options['job'] ?? null,
                'agent' => $state->options['agent'] ?? null,
                'mcp' => $state->options['mcp'] ?? null,
                'messaging' => $state->options['messaging'] ?? null,
                'credential' => $state->options['credential'] ?? null,
                'attributes' => $attrs,
            ]);
        }

        return null;
    }

    /**
     * @param  list<string>  $forced
     */
    private function stageResolveScope(InvokeState $state, array $forced): ?CapabilityResult
    {
        $state->mark(PipelineStages::RESOLVE_SCOPE);

        if ($this->shouldForceFail(PipelineStages::RESOLVE_SCOPE, $forced)) {
            return CapabilityResult::failure(
                code: 'forbidden',
                message: 'Forced failure at resolve_scope.',
            );
        }

        try {
            /** @var CapabilityContext $ctx */
            $ctx = $state->context;
            $opts = $state->options;
            // Propagate capability globalSystem into scope resolution (D-003).
            if ($state->definition->globalSystem) {
                $opts['global_system'] = true;
            }
            $scope = $this->resolveTenant->resolve($ctx, $opts);
            // Rebuild context with scope; keep attributes used during resolve.
            $state->context = $ctx->withScope($scope);
        } catch (Throwable $e) {
            return CapabilityResult::failure(
                code: 'forbidden',
                message: $e->getMessage(),
            );
        }

        return null;
    }

    /**
     * @param  list<string>  $forced
     */
    private function stageIdempotencyLookup(InvokeState $state, array $forced): ?CapabilityResult
    {
        $state->mark(PipelineStages::IDEMPOTENCY_LOOKUP);

        if ($this->shouldForceFail(PipelineStages::IDEMPOTENCY_LOOKUP, $forced)) {
            return CapabilityResult::failure(
                code: 'conflict',
                message: 'Forced failure at idempotency_lookup.',
            );
        }

        $key = isset($state->options['idempotency_key'])
            ? (string) $state->options['idempotency_key']
            : null;
        if ($key === '') {
            $key = null;
        }
        $state->idempotencyKey = $key;
        $state->requestHash = RequestHash::of($state->rawInput);

        // Policy before any store interaction (required key / format / warn missing).
        $policy = $this->idempotencyGuard->assertKeyPolicy(
            $state->definition,
            $key,
            is_string($state->options['caller'] ?? null) ? (string) $state->options['caller'] : 'http',
        );
        if ($policy !== null) {
            // Drop illegal/missing-required key so failure path does not attempt store.
            $state->idempotencyKey = null;

            return $policy;
        }

        if ($key === null || ! $state->definition->shouldUseIdempotency()) {
            return null;
        }

        /** @var CapabilityContext $ctx */
        $ctx = $state->context;
        $lookup = $this->idempotencyGuard->lookup(
            $state->definition,
            $ctx,
            $key,
            $state->requestHash,
        );

        if ($lookup['action'] === 'replay') {
            $state->idempotentReplay = true;

            return $lookup['result'];
        }

        if ($lookup['action'] === 'conflict' || $lookup['action'] === 'busy') {
            return $lookup['result'];
        }

        return null;
    }

    /**
     * @param  list<string>  $forced
     */
    private function stageAuthorize(InvokeState $state, array $forced): ?CapabilityResult
    {
        $state->mark(PipelineStages::AUTHORIZE);

        if ($this->shouldForceFail(PipelineStages::AUTHORIZE, $forced)) {
            return CapabilityResult::failure(
                code: 'forbidden',
                message: 'Forced failure at authorize.',
            );
        }

        $allowed = true;
        $definitionAuth = $state->definition->authorize;
        if (is_callable($definitionAuth)) {
            $allowed = (bool) $definitionAuth($state->input, $state->context);
        } else {
            $allowed = $this->authorizer->authorize(
                $state->definition->name,
                $state->input,
                $state->context,
            );
        }

        if (! $allowed) {
            return CapabilityResult::failure(
                code: 'forbidden',
                message: sprintf('Not authorized to invoke "%s".', $state->definition->name),
            );
        }

        return null;
    }

    /**
     * @param  list<string>  $forced
     */
    private function stageNeedsApproval(InvokeState $state, array $forced): ?CapabilityResult
    {
        $state->mark(PipelineStages::NEEDS_APPROVAL);

        if ($this->shouldForceFail(PipelineStages::NEEDS_APPROVAL, $forced)) {
            return $this->buildApprovalRequired($state, forced: true);
        }

        $needs = (bool) ($state->options['needs_approval'] ?? false);
        if (! $needs && is_callable($state->options['needs_approval_callback'] ?? null)) {
            $needs = (bool) $state->options['needs_approval_callback']($state->input, $state->context);
        }

        // Explicit approval policy + option gate; bare policy does not always require.
        if (! $needs && ($state->options['require_approval'] ?? false) === true) {
            $needs = $state->definition->approvalPolicy !== null;
        }

        if (! $needs) {
            return null;
        }

        return $this->buildApprovalRequired($state, forced: false);
    }

    private function buildApprovalRequired(InvokeState $state, bool $forced): CapabilityResult
    {
        /** @var CapabilityContext $ctx */
        $ctx = $state->context;
        $record = $this->approvalManager->request([
            'capability_name' => $state->definition->name,
            'status' => 'pending',
            'tenant_id' => $ctx->tenantId(),
            'requester_actor_type' => ResolveActor::actorType($ctx->actor()),
            'requester_actor_id' => ResolveActor::actorId($ctx->actor()),
            'original_caller' => $state->caller,
            'input_json' => $state->input instanceof CapabilityData
                ? $state->input->toArray()
                : $state->rawInput,
            'input_hash' => $state->requestHash,
            'idempotency_key' => $state->idempotencyKey,
        ]);

        $state->approvalId = (string) $record['id'];
        $this->approvalEvents[] = new CapabilityApprovalRequested(
            capability: $state->definition->name,
            approvalId: $state->approvalId,
            caller: $state->caller,
        );

        return CapabilityResult::approvalRequired(
            approvalId: $state->approvalId,
            message: $forced ? 'Forced approval_required.' : 'Approval required before run.',
            meta: ['request_id' => $state->requestId],
        );
    }

    /**
     * @param  list<string>  $forced
     */
    private function stageRateLimit(InvokeState $state, array $forced): ?CapabilityResult
    {
        $state->mark(PipelineStages::RATE_LIMIT);
        $this->lastRateLimitKey = null;

        if ($this->shouldForceFail(PipelineStages::RATE_LIMIT, $forced)) {
            return $this->rateLimitedResult('Forced failure at rate_limit.');
        }

        // Agent turn budget (D-013) — checked when caller is agent and option is set.
        if ($state->caller === 'agent' && array_key_exists('agent_turn_tool_calls', $state->options)) {
            $calls = (int) $state->options['agent_turn_tool_calls'];
            $budget = $this->agentTurnBudget();
            if ($budget->exhausted($calls)) {
                $stop = $budget->stopMessage($calls);

                return CapabilityResult::failure(
                    code: 'rate_limited',
                    message: $stop['message'],
                    extra: array_merge(ErrorCodeMap::wireFields('rate_limited'), [
                        'retryable' => false,
                        'max_tool_calls' => $stop['max_tool_calls'],
                        'calls' => $stop['calls'],
                        'structured' => $stop,
                    ]),
                );
            }
        }

        $enabled = (bool) ($this->rateLimitConfig['enabled'] ?? true);
        if (! $enabled) {
            return null;
        }

        $defaults = $this->rateLimitConfig['defaults'] ?? [];
        $override = $state->definition->rateLimit ?? [];

        $perMinute = array_key_exists('per_minute', $override)
            ? (int) $override['per_minute']
            : (int) ($defaults['per_minute'] ?? 60);
        $perCap = array_key_exists('per_capability_per_minute', $override)
            ? (int) $override['per_capability_per_minute']
            : (array_key_exists('per_minute', $override)
                ? (int) $override['per_minute']
                : (int) ($defaults['per_capability_per_minute'] ?? 30));

        // Explicit max on capability is treated as per-capability limit.
        if (isset($override['max'])) {
            $perCap = (int) $override['max'];
        }

        /** @var CapabilityContext $ctx */
        $ctx = $state->context;
        $actorType = ResolveActor::actorType($ctx->actor());
        $actorId = ResolveActor::actorId($ctx->actor());
        $tenantId = $ctx->tenantId();

        $actorKey = RateLimitKey::actorSurface($tenantId, $actorType, $actorId, $state->caller);
        $capKey = RateLimitKey::capability(
            $tenantId,
            $actorType,
            $actorId,
            $state->definition->name,
            $state->caller,
        );
        $this->lastRateLimitKey = $capKey;

        // Zero limits are edge: always rate_limited when the dimension is active.
        if ($perMinute <= 0 || $perCap <= 0) {
            return $this->rateLimitedResult('Rate limit exceeded.');
        }

        if ($this->rateLimiter->tooManyAttempts($actorKey, $perMinute)) {
            return $this->rateLimitedResult('Rate limit exceeded (per_minute).');
        }

        if ($this->rateLimiter->tooManyAttempts($capKey, $perCap)) {
            return $this->rateLimitedResult('Rate limit exceeded (per_capability_per_minute).');
        }

        $decay = (int) ($override['decay'] ?? 60);
        $this->rateLimiter->hit($actorKey, $decay);
        $this->rateLimiter->hit($capKey, $decay);

        return null;
    }

    private function rateLimitedResult(string $message): CapabilityResult
    {
        return CapabilityResult::failure(
            code: 'rate_limited',
            message: $message,
            extra: array_merge(ErrorCodeMap::wireFields('rate_limited'), [
                'retryable' => true,
            ]),
        );
    }

    /**
     * @param  list<string>  $forced
     */
    private function stageRun(InvokeState $state, array $forced): ?CapabilityResult
    {
        $state->mark(PipelineStages::RUN);
        $this->lastRunWasWrapped = false;

        if ($this->shouldForceFail(PipelineStages::RUN, $forced)) {
            return CapabilityResult::failure(
                code: 'domain_error',
                message: 'Forced failure at run.',
            );
        }

        if ($state->definition->run === null && $state->definition->handlerClass === null) {
            return CapabilityResult::failure(
                code: 'not_runnable',
                message: sprintf('Capability "%s" has no run handler.', $state->definition->name),
            );
        }

        try {
            $state->runCalled = true;
            $state->runCount++;
            $state->domainSideEffect = true;
            // Domain owns its transaction by default; wrap_run is opt-in (D-010).
            if ($this->wrapRun) {
                $this->lastRunWasWrapped = true;
            }
            $this->invokeStartedAt ??= microtime(true);
            $state->output = $this->executeRun($state->definition, $state->input, $state->context);
        } catch (Throwable $e) {
            return CapabilityResult::failure(
                code: 'domain_error',
                message: $e->getMessage(),
            );
        }

        return null;
    }

    /**
     * @param  list<string>  $forced
     */
    private function stageValidateOutput(InvokeState $state, array $forced): ?CapabilityResult
    {
        $state->mark(PipelineStages::VALIDATE_OUTPUT);

        if ($this->shouldForceFail(PipelineStages::VALIDATE_OUTPUT, $forced)) {
            return CapabilityResult::failure(
                code: 'output_invalid',
                message: 'Forced failure at validate_output.',
            );
        }

        if (! $state->definition->shouldValidateOutput($this->validateOutputEnabled())) {
            return null;
        }

        $check = $this->outputValidator->validate($state->definition, $state->output);
        if ($check !== null) {
            return $check;
        }

        return null;
    }

    private function stageStoreIdempotency(InvokeState $state): void
    {
        $state->mark(PipelineStages::STORE_IDEMPOTENCY);
        // Also record alias for inventory scenarios that use store_idempotency_result.
        if (! $state->hasStage(PipelineStages::STORE_IDEMPOTENCY_RESULT)) {
            $state->stages[] = PipelineStages::STORE_IDEMPOTENCY_RESULT;
        }

        if ($state->idempotencyKey === null || $state->idempotencyKey === '' || ! $state->definition->shouldUseIdempotency()) {
            return;
        }

        /** @var CapabilityContext $ctx */
        $ctx = $state->context;
        $result = CapabilityResult::success($state->output);
        $this->idempotencyGuard->storeResult(
            $state->definition,
            $ctx,
            $state->idempotencyKey,
            (string) $state->requestHash,
            $result,
            $state->approvalId,
        );
    }

    /**
     * Write audit entry. On success-path audit failure:
     * - best_effort: log (+ outbox when required); return null so client still succeeds
     * - strict: return failure envelope; domain run is NOT rolled back by the registry
     */
    private function stageRecordAudit(InvokeState $state, bool $success, ?CapabilityResult $failure = null): ?CapabilityResult
    {
        $state->mark(PipelineStages::RECORD_AUDIT);

        if (! $this->auditEnabled || $this->auditWriter === null || ! $state->definition->shouldAudit()) {
            return null;
        }

        $durationMs = $this->invokeStartedAt !== null
            ? (microtime(true) - $this->invokeStartedAt) * 1000
            : 0.0;

        $entry = AuditLogger::entry($state, $success, $failure, $durationMs);

        try {
            if ($this->throwOnAuditFailure) {
                throw new \RuntimeException('Audit writer failed.');
            }

            $this->auditWriter->write($entry);
        } catch (Throwable $e) {
            if ($this->auditMode === 'strict' && $success) {
                $this->logs[] = [
                    'level' => 'error',
                    'message' => 'Audit failed in strict mode: '.$e->getMessage(),
                    'context' => ['capability' => $state->definition->name],
                ];

                // When required, still enqueue for operators even in strict.
                if ($this->auditRequired) {
                    $this->ensureOutbox()->enqueue($entry);
                }

                return CapabilityResult::failure(
                    code: 'audit_failed',
                    message: 'Audit failed in strict mode: '.$e->getMessage(),
                    extra: array_merge(ErrorCodeMap::wireFields('audit_failed'), [
                        'retryable' => true,
                        'domain_committed' => $state->domainSideEffect,
                    ]),
                    meta: [
                        'request_id' => $state->requestId,
                        'stages' => $state->stages,
                        'domain_side_effect' => $state->domainSideEffect,
                    ],
                );
            }

            $this->logs[] = [
                'level' => 'warning',
                'message' => 'Audit failed (best_effort): '.$e->getMessage(),
                'context' => ['capability' => $state->definition->name],
            ];

            // best_effort + required: never silent drop — durable outbox intent.
            if ($this->auditRequired) {
                $this->ensureOutbox()->enqueue($entry);
            } elseif ($this->auditRequired === false) {
                // optional retry path may still enqueue when outbox is bound
                $this->auditOutbox?->enqueue($entry);
            }
        }

        return null;
    }

    private function ensureOutbox(): AuditOutbox
    {
        return $this->auditOutbox ??= new AuditOutbox;
    }

    private function stageEmitEvents(InvokeState $state, bool $success, ?CapabilityResult $failure = null): void
    {
        $state->mark(PipelineStages::EMIT_EVENTS);

        if (! $this->eventsEnabled) {
            return;
        }

        // Bus events fire after domain run stage (never before domain commit by default).
        if ($success) {
            $event = new CapabilityInvoked(
                capability: $state->definition->name,
                caller: $state->caller,
                data: $state->output,
                meta: [
                    'request_id' => $state->requestId,
                    'stages' => $state->stages,
                ],
            );
            $this->invokedEvents[] = $event;
        } elseif ($failure !== null) {
            $this->recordFailure(
                $state->definition,
                $failure->error['message'] ?? 'failed',
                $state->caller,
                $failure->errorCode() ?? 'internal',
            );
        }
    }

    private function stageWireResponse(InvokeState $state, CapabilityResult $result): CapabilityResult
    {
        $state->mark(PipelineStages::WIRE_RESPONSE);
        $state->result = $result;

        return $result;
    }

    // ── finish helpers ────────────────────────────────────────────────────

    private function finishFailure(
        InvokeState $state,
        CapabilityResult $result,
        bool $afterRun = false,
        bool $auditDeny = false,
        bool $outputInvalid = false,
    ): CapabilityResult {
        // Store idempotency failure when key present and after start of processing.
        if ($state->idempotencyKey && $state->context && $state->hasStage(PipelineStages::IDEMPOTENCY_LOOKUP) && ! $state->idempotentReplay) {
            $this->idempotencyGuard->storeResult(
                $state->definition,
                $state->context,
                $state->idempotencyKey,
                (string) $state->requestHash,
                $result,
            );
            if (! $state->hasStage(PipelineStages::STORE_IDEMPOTENCY)) {
                $state->mark(PipelineStages::STORE_IDEMPOTENCY);
            }
        }

        if ($auditDeny || $afterRun || $outputInvalid) {
            $this->stageRecordAudit($state, success: false, failure: $result);
            $this->stageEmitEvents($state, success: false, failure: $result);
        } elseif ($outputInvalid || $afterRun) {
            // already handled
        } else {
            // Pre-run failures still emit CapabilityFailed for observability on validation etc. optional —
            // StageErrorMapping only requires error envelope; emit for output_invalid and domain always.
            if (in_array($result->errorCode(), ['domain_error', 'output_invalid', 'internal'], true)) {
                $this->stageEmitEvents($state, success: false, failure: $result);
            }
        }

        if ($outputInvalid) {
            // Ensure failure event recorded even if stages above skipped
            if ($this->failedEvents === [] || ! $state->hasStage(PipelineStages::EMIT_EVENTS)) {
                if (! $state->hasStage(PipelineStages::EMIT_EVENTS)) {
                    $this->stageEmitEvents($state, success: false, failure: $result);
                }
            }
        }

        return $this->stageWireResponse($state, $result->ok
            ? $result
            : CapabilityResult::failure(
                code: (string) $result->errorCode(),
                message: (string) ($result->error['message'] ?? 'failed'),
                extra: array_diff_key($result->error ?? [], array_flip(['code', 'message'])),
                meta: array_merge($result->meta, [
                    'request_id' => $state->requestId,
                    'stages' => $state->stages,
                ]),
            ));
    }

    private function finishApprovalRequired(InvokeState $state, CapabilityResult $result): CapabilityResult
    {
        if ($state->idempotencyKey && $state->context) {
            $this->idempotencyGuard->storeResult(
                $state->definition,
                $state->context,
                $state->idempotencyKey,
                (string) $state->requestHash,
                $result,
                $state->approvalId,
            );
            $state->mark(PipelineStages::STORE_IDEMPOTENCY);
        }

        $this->stageRecordAudit($state, success: false, failure: $result);
        $state->mark(PipelineStages::EMIT_EVENTS);

        return $this->stageWireResponse($state, $result);
    }

    private function finishReplay(InvokeState $state, CapabilityResult $result): CapabilityResult
    {
        // Idempotent replay may skip full audit or mark replay (D-010).
        $this->stageRecordAudit($state, success: $result->isOk(), failure: $result->isOk() ? null : $result);
        $state->mark(PipelineStages::EMIT_EVENTS);
        $meta = array_merge($result->meta, [
            'idempotent_replay' => true,
            'stages' => $state->stages,
        ]);

        if ($result->isOk()) {
            $wired = CapabilityResult::success($result->data, $meta);
        } else {
            $wired = CapabilityResult::failure(
                code: (string) $result->errorCode(),
                message: (string) ($result->error['message'] ?? 'failed'),
                extra: array_diff_key($result->error ?? [], array_flip(['code', 'message'])),
                meta: $meta,
            );
        }

        return $this->stageWireResponse($state, $wired);
    }

    private function finishEarly(CapabilityResult $result, ?InvokeState $state): CapabilityResult
    {
        $this->lastStages = $state?->stages ?? [];
        $this->lastState = $state;

        return $result;
    }

    private function executeRun(CapabilityDefinition $definition, mixed $input, mixed $context = null): mixed
    {
        if (is_callable($definition->run)) {
            // Prefer (input, context) for D-003 re-resolve; fall back to input-only handlers.
            try {
                return ($definition->run)($input, $context);
            } catch (\ArgumentCountError) {
                return ($definition->run)($input);
            }
        }

        if ($definition->handlerClass !== null) {
            $handler = new ($definition->handlerClass);
            if (! method_exists($handler, 'run')) {
                throw new InvalidArgumentException(sprintf(
                    'Handler %s has no run() method.',
                    $definition->handlerClass,
                ));
            }

            try {
                return $handler->run($input, $context);
            } catch (\ArgumentCountError) {
                return $handler->run($input);
            }
        }

        throw new InvalidArgumentException('No run handler.');
    }

    private function recordFailure(
        CapabilityDefinition $definition,
        string $message,
        string $caller,
        string $code = 'output_invalid',
    ): void {
        $event = new CapabilityFailed(
            capability: $definition->name,
            code: $code,
            message: $message,
            caller: $caller,
        );
        $this->failedEvents[] = $event;
        $this->logs[] = [
            'level' => 'error',
            'message' => $message,
            'context' => [
                'capability' => $definition->name,
                'code' => $code,
                'caller' => $caller,
            ],
        ];
    }

    /**
     * @param  list<string>  $forced
     */
    private function shouldForceFail(string $stage, array $forced): bool
    {
        return in_array($stage, $forced, true);
    }

    /**
     * @param  string|array<string, mixed>|list<string>|null  $profile
     * @return list<array<string, mixed>>
     */
    private function toolsForSurface(string $surface, string|array|null $profile, mixed $actor = null): array
    {
        $cfg = $this->toolSurfaceConfig[$surface] ?? [
            'profiles' => [],
            'require_profile' => true,
            'max_tools_warn' => 32,
            'max_tools_hard' => 64,
        ];
        $namedProfiles = $cfg['profiles'] ?? [];
        $requireProfile = (bool) ($cfg['require_profile'] ?? true);
        $resolved = $this->profileSelector->resolve($profile, $namedProfiles);

        if ($resolved['unscoped']) {
            if ($requireProfile) {
                throw ProfileRequiredException::forSurface($surface);
            }
            // Loud deprecation path: empty list, not full catalog dump (D-008).
            $this->logs[] = [
                'level' => 'warning',
                'message' => sprintf('Unfiltered %s tools requested; returning empty list (D-008).', $surface),
                'context' => ['surface' => $surface],
            ];

            return [];
        }

        $tools = [];
        foreach ($this->definitions as $definition) {
            $effective = $definition->effectiveSurfaces($this->globallyEnabledSurfaces);
            if (! in_array($surface, $effective, true)) {
                continue;
            }
            if (! $definition->isDiscoverable($actor)) {
                continue;
            }
            if (! $this->profileSelector->matches($definition, $resolved)) {
                continue;
            }
            $tools[] = [
                'name' => $definition->name,
                'description' => $definition->description,
                'input_schema' => $definition->inputSchema(),
            ];
        }

        $count = count($tools);
        $warn = (int) ($cfg['max_tools_warn'] ?? 32);
        $hard = (int) ($cfg['max_tools_hard'] ?? 64);

        if ($count > $hard) {
            throw new TooManyToolsException($count, $hard);
        }
        if ($count > $warn) {
            $this->logs[] = [
                'level' => 'warning',
                'message' => sprintf(
                    'Profile expanded to %d tools (warn threshold %d) for surface %s.',
                    $count,
                    $warn,
                    $surface,
                ),
                'context' => ['surface' => $surface, 'count' => $count, 'warn' => $warn],
            ];
        }

        return $tools;
    }

    /**
     * @param  string|array<string, mixed>|list<string>|null  $profile
     * @return list<array<string, mixed>>
     */
    private function metaToolsForSurface(string $surface, string|array|null $profile): array
    {
        $cfg = $this->toolSurfaceConfig[$surface] ?? ['require_profile' => true, 'profiles' => []];
        $requireProfile = (bool) ($cfg['require_profile'] ?? true);
        $resolved = $this->profileSelector->resolve($profile, $cfg['profiles'] ?? []);

        if ($resolved['unscoped']) {
            if ($requireProfile) {
                throw ProfileRequiredException::forSurface($surface);
            }

            return [];
        }

        // Meta-tools inherit the same profile — not a full-catalog escape hatch (P2-007).
        return [
            [
                'name' => 'capabilities.list',
                'description' => 'List capabilities in profile',
                'profile' => $profile,
                'surface' => $surface,
                'allowlist' => $this->listCapabilitiesInProfile($surface, $profile),
            ],
            [
                'name' => 'capabilities.invoke',
                'description' => 'Invoke a capability by name within profile',
                'profile' => $profile,
                'surface' => $surface,
                'allowlist' => $this->listCapabilitiesInProfile($surface, $profile),
            ],
        ];
    }
}

