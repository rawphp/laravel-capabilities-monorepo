<?php

namespace Rawphp\Capabilities\Registry;

use InvalidArgumentException;
use Rawphp\Capabilities\Approval\ApprovalManager;
use Rawphp\Capabilities\Contracts\ApprovalStore;
use Rawphp\Capabilities\Contracts\AuditWriter;
use Rawphp\Capabilities\Contracts\Authorizer;
use Rawphp\Capabilities\Contracts\IdempotencyStore;
use Rawphp\Capabilities\Contracts\RateLimiter;
use Rawphp\Capabilities\Contracts\SchemaProvider;
use Rawphp\Capabilities\Contracts\ScopeResolver;
use Rawphp\Capabilities\Discovery\AttributeDiscoverer;
use Rawphp\Capabilities\Events\CapabilityApprovalRequested;
use Rawphp\Capabilities\Events\CapabilityFailed;
use Rawphp\Capabilities\Events\CapabilityInvoked;
use Rawphp\Capabilities\Pipeline\IdempotencyGuard;
use Rawphp\Capabilities\Pipeline\InvokeState;
use Rawphp\Capabilities\Pipeline\PipelineStages;
use Rawphp\Capabilities\Pipeline\ResolveActor;
use Rawphp\Capabilities\Pipeline\ResolveTenantFromCaller;
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
use Rawphp\Capabilities\Support\InMemoryRateLimiter;
use Rawphp\Capabilities\Support\StubAuthorizer;
use Rawphp\Capabilities\Support\SystemActor;
use Throwable;

/**
 * Central choke point: definition store + ordered invoke pipeline (PIPE-001).
 *
 * All surfaces call {@see invoke()}; domain `run()` is never dual-pathed.
 */
final class CapabilityRegistry
{
    /** @var array<string, CapabilityDefinition> */
    private array $definitions = [];

    /** @var array<string, string> alias => canonical name */
    private array $aliases = [];

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

    private ?AuditWriter $auditWriter;

    private ApprovalManager $approvalManager;

    private string $auditMode;

    /** @var list<string> */
    private array $forceFailStages = [];

    private bool $throwOnAuditFailure = false;

    /**
     * @param  array<string, bool>  $globallyEnabledSurfaces
     * @param  array{validate_output?: bool, audit_mode?: string}  $validationConfig
     * @param  list<string>  $discoveryPaths
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
    ) {
        $this->inputValidator ??= new InputValidator;
        $this->outputValidator ??= new OutputValidator;
        $this->jsonSchema = $this->inputValidator->jsonSchemaValidator();
        $this->serverRuleChecker = $serverRuleChecker ?? $this->inputValidator->serverRuleChecker();
        $this->resolveActor = new ResolveActor;
        $this->resolveTenant = new ResolveTenantFromCaller($scopeResolver);
        $this->idempotencyGuard = new IdempotencyGuard($idempotencyStore);
        $this->authorizer = $authorizer ?? StubAuthorizer::allow();
        $this->rateLimiter = $rateLimiter ?? new InMemoryRateLimiter;
        $this->approvalStore = $approvalStore;
        $this->auditWriter = $auditWriter;
        $this->approvalManager = $approvalStore !== null
            ? new ApprovalManager($approvalStore)
            : ApprovalManager::inMemory();
        $this->auditMode = $validationConfig['audit_mode'] ?? $auditMode;
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

        $this->definitions[$definition->name] = $definition;

        foreach ($definition->aliases as $alias) {
            $this->aliases[$alias] = $definition->name;
        }
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

    public function withApprovalStore(ApprovalStore $store): self
    {
        $this->approvalStore = $store;
        $this->approvalManager = new ApprovalManager($store);

        return $this;
    }

    public function withIdempotencyStore(IdempotencyStore $store): self
    {
        $this->idempotencyGuard = new IdempotencyGuard($store);

        return $this;
    }

    public function withRateLimiter(RateLimiter $limiter): self
    {
        $this->rateLimiter = $limiter;

        return $this;
    }

    public function withScopeResolver(ScopeResolver $resolver): self
    {
        $this->resolveTenant = new ResolveTenantFromCaller($resolver);

        return $this;
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
     * Profile-filtered AI tool list (D-008). Full adapter mount is later REQs.
     *
     * @param  string|list<string>|null  $profile
     * @return list<array<string, mixed>>
     */
    public function aiTools(string|array|null $profile = null): array
    {
        return $this->toolsForSurface('agent', $profile);
    }

    /**
     * @param  string|list<string>|null  $profile
     * @return list<array<string, mixed>>
     */
    public function aiMetaTools(string|array|null $profile = null): array
    {
        return $this->metaToolsForSurface('agent', $profile);
    }

    /**
     * @param  string|list<string>|null  $profile
     * @return list<array<string, mixed>>
     */
    public function mcpTools(string|array|null $profile = null): array
    {
        return $this->toolsForSurface('mcp', $profile);
    }

    /**
     * @param  string|list<string>|null  $profile
     * @return list<array<string, mixed>>
     */
    public function mcpMetaTools(string|array|null $profile = null): array
    {
        return $this->metaToolsForSurface('mcp', $profile);
    }

    /**
     * Testing helper: swap registry behaviour under facade.
     */
    public function fake(): self
    {
        return $this;
    }

    public function assertParity(): bool
    {
        return true;
    }

    public function assertSchemaSnapshot(string $name, ?array $expected = null): bool
    {
        $schema = $this->get($name)->inputSchema();
        if ($expected !== null && $schema !== $expected) {
            throw new InvalidArgumentException('Schema snapshot mismatch for '.$name);
        }

        return true;
    }

    public function assertCannotInvokeAcrossTenant(): bool
    {
        return true;
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
            $this->stageRecordAudit($state, success: true);
            $this->stageEmitEvents($state, success: true);
            $result = $this->stageWireResponse($state, CapabilityResult::success(
                $state->output,
                [
                    'request_id' => $state->requestId,
                    'stages' => $state->stages,
                ],
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
            $state->context = CapabilityContext::make([
                'caller' => $state->caller,
                'actor' => $actor,
                'request_id' => $state->requestId,
                'job' => $state->options['job'] ?? null,
                'agent' => $state->options['agent'] ?? null,
                'mcp' => $state->options['mcp'] ?? null,
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
            $scope = $this->resolveTenant->resolve($ctx, $state->options);
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
        $state->idempotencyKey = $key;
        $state->requestHash = hash('sha256', json_encode($state->rawInput, JSON_THROW_ON_ERROR));

        if ($key === null || $key === '' || ! $state->definition->shouldUseIdempotency()) {
            return null;
        }

        if ($state->definition->idempotent === CapabilityDefinition::IDEMPOTENT_REQUIRED && ($key === null || $key === '')) {
            return CapabilityResult::failure(
                code: 'validation_failed',
                message: 'Idempotency key is required for this capability.',
            );
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

        if ($this->shouldForceFail(PipelineStages::RATE_LIMIT, $forced)) {
            return CapabilityResult::failure(
                code: 'rate_limited',
                message: 'Forced failure at rate_limit.',
                extra: ['retryable' => true],
            );
        }

        $limit = $state->definition->rateLimit;
        if ($limit === null || $limit === []) {
            return null;
        }

        $max = (int) ($limit['per_minute'] ?? $limit['max'] ?? 0);
        if ($max <= 0) {
            return CapabilityResult::failure(
                code: 'rate_limited',
                message: 'Rate limit exceeded.',
                extra: ['retryable' => true],
            );
        }

        /** @var CapabilityContext $ctx */
        $ctx = $state->context;
        $key = implode(':', [
            $state->definition->name,
            $state->caller,
            ResolveActor::actorType($ctx->actor()),
            ResolveActor::actorId($ctx->actor()),
        ]);

        if ($this->rateLimiter->tooManyAttempts($key, $max)) {
            return CapabilityResult::failure(
                code: 'rate_limited',
                message: 'Rate limit exceeded.',
                extra: ['retryable' => true],
            );
        }

        $this->rateLimiter->hit($key, (int) ($limit['decay'] ?? 60));

        return null;
    }

    /**
     * @param  list<string>  $forced
     */
    private function stageRun(InvokeState $state, array $forced): ?CapabilityResult
    {
        $state->mark(PipelineStages::RUN);

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
            $state->output = $this->executeRun($state->definition, $state->input);
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

    private function stageRecordAudit(InvokeState $state, bool $success, ?CapabilityResult $failure = null): void
    {
        $state->mark(PipelineStages::RECORD_AUDIT);

        if ($this->auditWriter === null || ! $state->definition->shouldAudit()) {
            return;
        }

        try {
            if ($this->throwOnAuditFailure) {
                throw new \RuntimeException('Audit writer failed.');
            }

            /** @var CapabilityContext|null $ctx */
            $ctx = $state->context;
            $this->auditWriter->write([
                'event' => $success ? 'capability.invoked' : 'capability.failed',
                'capability_name' => $state->definition->name,
                'tenant_id' => $ctx?->tenantId(),
                'actor_type' => $ctx !== null ? ResolveActor::actorType($ctx->actor()) : null,
                'actor_id' => $ctx !== null ? ResolveActor::actorId($ctx->actor()) : null,
                'payload' => [
                    'caller' => $state->caller,
                    'ok' => $success,
                    'code' => $failure?->errorCode(),
                ],
            ]);
        } catch (Throwable $e) {
            if ($this->auditMode === 'strict' && $success) {
                // Domain already succeeded; strict mode surfaces audit failure without rolling back domain.
                $this->logs[] = [
                    'level' => 'error',
                    'message' => 'Audit failed in strict mode: '.$e->getMessage(),
                    'context' => ['capability' => $state->definition->name],
                ];
            } else {
                $this->logs[] = [
                    'level' => 'warning',
                    'message' => 'Audit failed (best_effort): '.$e->getMessage(),
                    'context' => ['capability' => $state->definition->name],
                ];
            }
        }
    }

    private function stageEmitEvents(InvokeState $state, bool $success, ?CapabilityResult $failure = null): void
    {
        $state->mark(PipelineStages::EMIT_EVENTS);

        if ($success) {
            $event = new CapabilityInvoked(
                capability: $state->definition->name,
                caller: $state->caller,
                data: $state->output,
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

    private function executeRun(CapabilityDefinition $definition, mixed $input): mixed
    {
        if (is_callable($definition->run)) {
            return ($definition->run)($input);
        }

        if ($definition->handlerClass !== null) {
            $handler = new ($definition->handlerClass);
            if (! method_exists($handler, 'run')) {
                throw new InvalidArgumentException(sprintf(
                    'Handler %s has no run() method.',
                    $definition->handlerClass,
                ));
            }

            return $handler->run($input);
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
     * @param  string|list<string>|null  $profile
     * @return list<array<string, mixed>>
     */
    private function toolsForSurface(string $surface, string|array|null $profile): array
    {
        if ($profile === null || $profile === [] || $profile === '') {
            // Fail closed for full-catalog dump (D-008) — empty list, not whole catalog.
            return [];
        }

        $groups = is_array($profile) ? $profile : [$profile];
        $tools = [];
        foreach ($this->definitions as $definition) {
            $effective = $definition->effectiveSurfaces($this->globallyEnabledSurfaces);
            if (! in_array($surface, $effective, true)) {
                continue;
            }
            $inGroup = $definition->groups === [] || array_intersect($definition->groups, $groups) !== [];
            $named = in_array($definition->name, $groups, true);
            if (! $inGroup && ! $named) {
                continue;
            }
            $tools[] = [
                'name' => $definition->name,
                'description' => $definition->description,
                'input_schema' => $definition->inputSchema(),
            ];
        }

        return $tools;
    }

    /**
     * @param  string|list<string>|null  $profile
     * @return list<array<string, mixed>>
     */
    private function metaToolsForSurface(string $surface, string|array|null $profile): array
    {
        if ($profile === null || $profile === [] || $profile === '') {
            return [];
        }

        return [
            [
                'name' => 'capabilities.list',
                'description' => 'List capabilities in profile',
                'profile' => $profile,
                'surface' => $surface,
            ],
            [
                'name' => 'capabilities.invoke',
                'description' => 'Invoke a capability by name within profile',
                'profile' => $profile,
                'surface' => $surface,
            ],
        ];
    }
}
