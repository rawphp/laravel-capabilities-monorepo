<?php

namespace Rawphp\Capabilities\Pipeline;

use Closure;
use Error;
use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;
use PDOException;
use Rawphp\Capabilities\Approval\ApprovalManager;
use Rawphp\Capabilities\Contracts\Authorizer;
use Rawphp\Capabilities\Contracts\RateLimiter;
use Rawphp\Capabilities\Contracts\SchemaProvider;
use Rawphp\Capabilities\Events\CapabilityApprovalRequested;
use Rawphp\Capabilities\Idempotency\IdempotencyKey;
use Rawphp\Capabilities\Idempotency\RequestHash;
use Rawphp\Capabilities\RateLimiting\AgentTurnBudget;
use Rawphp\Capabilities\RateLimiting\RateLimitKey;
use Rawphp\Capabilities\Registry\CapabilityDefinition;
use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Rawphp\Capabilities\Schema\JsonSchemaValidator;
use Rawphp\Capabilities\Schema\OutputValidator;
use Rawphp\Capabilities\Schema\ServerRuleChecker;
use Rawphp\Capabilities\Support\CapabilityContext;
use Rawphp\Capabilities\Support\CapabilityData;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Support\ErrorCodeMap;
use Rawphp\Capabilities\Support\FailureReporter;
use Rawphp\Capabilities\Support\SystemActor;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use Throwable;

/**
 * Ordered capability invoke pipeline (PIPE-001).
 *
 * Extracted from {@see CapabilityRegistry} so the
 * registry remains a thin bus facade over definition catalog + pipeline.
 * Finish / wire / event policy lives in {@see InvokeResultFinalizer}.
 */
final class InvokePipeline
{
    /**
     * Builds the #[Capability] class handler (D-017). Defaults to the container so
     * constructor dependencies resolve; units inject a factory.
     *
     * @var Closure(class-string): object
     */
    public Closure $handlerFactory;

    /**
     * Connection for the opt-in outer transaction (D-010 transactions.wrap_run).
     * Null with wrap_run on is a configuration error and fails closed.
     */
    public ?ConnectionInterface $transactionConnection = null;

    /**
     * Host event dispatcher for bus events (D-010 §5 / L-007); null keeps events in-memory only.
     */
    public ?Dispatcher $events = null;

    /**
     * Late source for the host Authorizer (container binding), called on every authorize
     * decision so a binding made after the registry was built still gates and a request-scoped
     * Authorizer is never reused across requests. An explicit `$authorizer` always wins.
     * Returns null when nothing is bound; throws when the binding cannot be resolved (fail closed).
     *
     * @var (Closure(): ?Authorizer)|null
     */
    public ?Closure $authorizerResolver = null;

    /**
     * @param  array{
     *     enabled?: bool,
     *     defaults?: array{per_minute?: int, per_capability_per_minute?: int},
     *     agent_turn?: array{max_tool_calls?: int}
     * }  $rateLimitConfig
     * @param  (Closure(class-string): object)|null  $handlerFactory
     */
    public function __construct(
        public JsonSchemaValidator $jsonSchema,
        public ServerRuleChecker $serverRuleChecker,
        public ResolveActor $resolveActor,
        public ResolveTenantFromCaller $resolveTenant,
        public IdempotencyGuard $idempotencyGuard,
        public ?Authorizer $authorizer,
        public RateLimiter $rateLimiter,
        public ApprovalManager $approvalManager,
        public OutputValidator $outputValidator,
        public InvokeObservation $observation,
        public InvokeAuditStage $auditStage,
        public bool $wrapRun = false,
        public bool $eventsEnabled = true,
        public bool $validateOutputEnabled = true,
        public array $rateLimitConfig = [
            'enabled' => true,
            'defaults' => [
                'per_minute' => 60,
                'per_capability_per_minute' => 30,
            ],
            'agent_turn' => [
                'max_tool_calls' => 16,
            ],
        ],
        ?Closure $handlerFactory = null,
    ) {
        $this->handlerFactory = $handlerFactory
            ?? static fn (string $class): object => Container::getInstance()->make($class);
    }

    public function agentTurnBudget(): AgentTurnBudget
    {
        return AgentTurnBudget::fromConfig($this->rateLimitConfig['agent_turn'] ?? []);
    }

    /**
     * Run ordered pipeline stages for an already-resolved capability (PIPE-001).
     *
     * @param  list<string>  $forced
     */
    public function execute(InvokeState $state, array $forced = []): CapabilityResult
    {
        $this->observation->lastState = $state;

        try {
            // ── pre-run stages ──────────────────────────────────────────
            $early = $this->stageJsonSchemaValidate($state, $forced);
            if ($early !== null) {
                return $this->results()->finishFailure($state, $early);
            }

            $early = $this->stageHydrateDto($state, $forced);
            if ($early !== null) {
                return $this->results()->finishFailure($state, $early);
            }

            $early = $this->stageServerOnlyValidate($state, $forced);
            if ($early !== null) {
                return $this->results()->finishFailure($state, $early);
            }

            $early = $this->stageResolveActor($state, $forced);
            if ($early !== null) {
                return $this->results()->finishFailure($state, $early);
            }

            $early = $this->stageResolveScope($state, $forced);
            if ($early !== null) {
                return $this->results()->finishFailure($state, $early);
            }

            $early = $this->stageIdempotencyLookup($state, $forced);
            if ($early !== null) {
                // Replay is success-shaped; conflict/busy are failures.
                if ($state->idempotentReplay) {
                    // Replays still spend turn budget and rate limits (D-013): a loop
                    // resending one idempotency key must not bypass loop protection.
                    $limited = $this->stageRateLimit($state, $forced);
                    if ($limited !== null) {
                        return $this->results()->finishFailure($state, $limited);
                    }

                    return $this->results()->finishReplay($state, $early);
                }

                return $this->results()->finishFailure($state, $early);
            }

            $early = $this->stageAuthorize($state, $forced);
            if ($early !== null) {
                return $this->results()->finishFailure($state, $early, auditDeny: true);
            }

            // Rate limit before approval so approval requests cannot flood the store/notifiers (D-013).
            $early = $this->stageRateLimit($state, $forced);
            if ($early !== null) {
                return $this->results()->finishFailure($state, $early, auditDeny: true);
            }

            $early = $this->stageNeedsApproval($state, $forced);
            if ($early !== null) {
                return $this->results()->finishApprovalRequired($state, $early);
            }

            // ── run ─────────────────────────────────────────────────────
            $early = $this->stageRun($state, $forced);
            if ($early !== null) {
                $this->settleOpenWrap($state, commit: false);

                return $this->results()->finishFailure($state, $early, afterRun: true);
            }

            // ── post-run ────────────────────────────────────────────────
            $early = $this->stageValidateOutput($state, $forced);
            if ($early !== null) {
                // Output checks run after the domain call. A held wrap commits here
                // so an invalid output does not pretend the domain write vanished.
                $this->settleOpenWrap($state, commit: true);

                return $this->results()->finishFailure($state, $early, afterRun: true, outputInvalid: true);
            }

            if ($state->definition->auditMode($this->auditStage->auditMode) === 'strict') {
                return $this->finishStrict($state);
            }

            $this->stageStoreIdempotency($state);
            $auditFailure = $this->stageRecordAudit($state, success: true);

            // best_effort: audit failure still returns the domain success (D-010).
            if ($auditFailure !== null) {
                $this->results()->emitEvents($state, success: false, failure: $auditFailure);

                return $this->results()->wireResponse($state, $auditFailure);
            }

            $this->results()->emitEvents($state, success: true);

            return $this->results()->wireResponse($state, CapabilityResult::success(
                $state->output,
                $this->successMeta($state),
            ));
        } catch (Throwable $e) {
            $this->settleOpenWrap($state, commit: false);

            return $this->finishUncaught($state, $e);
        }
    }

    /**
     * A throwable escaping any stage (authorize callables, stores, rate limiter, output
     * validation, strict audit) is a bug-class failure: report it, hide the message, and
     * still run the failure finish so a claimed Idempotency-Key is stored as failed rather
     * than left `processing` until its TTL (D-005). The finish itself may be what is broken,
     * so a second throw falls back to a bare envelope.
     */
    private function finishUncaught(InvokeState $state, Throwable $e): CapabilityResult
    {
        $this->reportThrowable($e);
        $failure = CapabilityResult::failure(code: 'internal', message: 'Internal error.');

        try {
            return $this->results()->finishFailure($state, $failure);
        } catch (Throwable $finishFailed) {
            $this->reportThrowable($finishFailed);
            $state->mark(PipelineStages::WIRE_RESPONSE);
            $this->observation->lastState = $state;
            $this->observation->lastStages = $state->stages;

            return CapabilityResult::failure(
                code: 'internal',
                message: 'Internal error.',
                meta: ['request_id' => $state->requestId, 'stages' => $state->stages],
            );
        }
    }

    private function reportThrowable(Throwable $e): void
    {
        FailureReporter::report($e);
    }

    /**
     * Registry gate deny (sunset / surface): audited and emitted like an authorize deny.
     */
    public function finishGateDeny(InvokeState $state, CapabilityResult $result): CapabilityResult
    {
        return $this->results()->finishFailure($state, $result, auditDeny: true);
    }

    /**
     * Unknown capability: no definition to audit against, but the attempt is still a failed invoke.
     */
    public function finishUnknown(string $name, string $caller, CapabilityResult $result): CapabilityResult
    {
        $this->results()->recordFailure($name, (string) ($result->error['message'] ?? 'not found'), $caller, 'not_found');

        return $this->results()->finishEarly($result, null);
    }

    /**
     * Finish / wire / events collaborator (built per call so config mutations stay consistent).
     */
    private function results(): InvokeResultFinalizer
    {
        return new InvokeResultFinalizer(
            observation: $this->observation,
            auditStage: $this->auditStage,
            idempotencyGuard: $this->idempotencyGuard,
            eventsEnabled: $this->eventsEnabled,
            events: $this->events,
        );
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

        try {
            $state->input = $this->hydrate($state->definition, $state->rawInput);
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

        $violations = $this->serverRuleChecker->check($rules, $state->sentInput());
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
        if ($key === null) {
            $key = IdempotencyKey::derive($state->definition->idempotencyKeyFields, $state->rawInput);
        }
        $state->idempotencyKey = $key;
        // Hash the validated, defaults-applied input so omitted vs explicit-default
        // optional fields are the same request (D-005 canonical input JSON).
        $state->requestHash = RequestHash::of($state->input instanceof CapabilityData
            ? $state->input->toArray()
            : $state->rawInput);

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
        $executing = $state->options['executing_approval_id'] ?? null;
        $lookup = $this->idempotencyGuard->lookup(
            $state->definition,
            $ctx,
            $key,
            $state->requestHash,
            is_scalar($executing) ? (string) $executing : null,
        );

        if ($lookup['action'] === 'replay') {
            $state->idempotentReplay = true;

            return $lookup['result'];
        }

        if ($lookup['action'] === 'conflict' || $lookup['action'] === 'busy') {
            // This request never claimed the key: its refusal must not overwrite the owning
            // request's row (a different body, or the run still in flight) — D-005.
            $state->idempotencyKey = null;

            return $lookup['result'];
        }

        return null;
    }

    /**
     * Authorize stored raw input for an actor outside a live invoke — approval
     * accept re-checks the original requester (spec: re-validation on accept, step 4).
     * Same decision as the authorize stage; input that no longer hydrates is denied.
     *
     * @param  array<string, mixed>  $rawInput
     */
    public function authorizes(CapabilityDefinition $definition, array $rawInput, CapabilityContext $context): bool
    {
        try {
            $input = $this->hydrate($definition, $rawInput);

            return $this->allows($definition, $input, $context, $this->makeHandler($definition));
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Resolve the D-017 class handler once per invoke (null for fluent definitions).
     */
    private function handler(InvokeState $state): ?object
    {
        return $state->handler ??= $this->makeHandler($state->definition);
    }

    private function makeHandler(CapabilityDefinition $definition): ?object
    {
        if ($definition->handlerClass === null) {
            return null;
        }

        return ($this->handlerFactory)($definition->handlerClass);
    }

    /**
     * @param  array<string, mixed>  $rawInput
     */
    private function hydrate(CapabilityDefinition $definition, array $rawInput): mixed
    {
        $inputClass = $definition->input;
        if ($inputClass === null) {
            return $rawInput;
        }

        if (is_a($inputClass, CapabilityData::class, true)) {
            /** @var class-string<CapabilityData> $inputClass */
            return $inputClass::fromArray($rawInput);
        }

        /** @var class-string<SchemaProvider> $inputClass */
        return $inputClass::validate($rawInput);
    }

    /**
     * The host-bound Authorizer, if any: the explicit instance, else the late container
     * binding, resolved fresh on every call (never cached). A binding that throws propagates.
     */
    public function hostAuthorizer(): ?Authorizer
    {
        if ($this->authorizer !== null) {
            return $this->authorizer;
        }

        return $this->authorizerResolver !== null ? ($this->authorizerResolver)() : null;
    }

    /**
     * Authorize decision (D-017 / L-003). A host-bound Authorizer is a gate every invoke
     * must pass, asked first. The capability's own rule (fluent authorize callable, else
     * the class authorize()) must also pass. With no own rule the host Authorizer alone
     * decides; with neither, the invoke is denied (fail closed). A null `$authorizer`
     * means no host Authorizer was supplied, so it is not a gate.
     */
    private function allows(CapabilityDefinition $definition, mixed $input, mixed $context, ?object $handler): bool
    {
        $host = $this->hostAuthorizer();
        if ($host !== null && ! $host->authorize($definition->name, $input, $context)) {
            return false;
        }

        $definitionAuth = $definition->authorize;
        if (is_callable($definitionAuth)) {
            return (bool) $definitionAuth($input, $context);
        }

        if ($handler !== null && method_exists($handler, 'authorize')) {
            return (bool) self::callWithArity([$handler, 'authorize'], $input, $context);
        }

        return $host !== null;
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

        if (! $this->allows($state->definition, $state->input, $state->context, $this->handler($state))) {
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

        // Executing an already-approved request (D-006 accept / resume): the decision was
        // made; asking again would loop the row back to pending.
        if (isset($state->options['executing_approval_id'])) {
            return null;
        }

        $needs = (bool) ($state->options['needs_approval'] ?? false);
        if (! $needs && is_callable($state->options['needs_approval_callback'] ?? null)) {
            $needs = (bool) $state->options['needs_approval_callback']($state->input, $state->context);
        }

        // The capability's own rule — governance is part of the definition (D-006 / D-017):
        // fluent needsApproval(callable) or the class handler's needsApproval().
        if (! $needs && is_callable($state->definition->needsApproval)) {
            $needs = (bool) self::callWithArity($state->definition->needsApproval, $state->input, $state->context);
        }
        $handler = $this->handler($state);
        if (! $needs && $handler !== null && method_exists($handler, 'needsApproval')) {
            $needs = (bool) self::callWithArity([$handler, 'needsApproval'], $state->input, $state->context);
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
            // The whole resolved scope travels with the row so the accept re-check and the
            // approved run see exactly what was requested (D-006 step 5, L-501).
            'scope' => $ctx->scope()?->toRow(),
            'requester_actor_type' => ResolveActor::actorType($ctx->actor()),
            'requester_actor_id' => ResolveActor::actorId($ctx->actor()),
            'original_caller' => $state->caller,
            'original_surface' => $state->surface,
            'input_json' => $state->sentInput(),
            'input_hash' => $state->requestHash,
            'idempotency_key' => $state->idempotencyKey,
            // The capability's own governance travels with the row (D-006): who may decide, how long.
            'approval_policy' => $state->definition->approvalPolicy,
            'approval_ttl_hours' => $state->definition->approvalTtlHours,
            // Where the request came from, so a chat notifier can put the buttons in that
            // conversation (M-101 / D-006 step 4); null for HTTP / CLI / job requests.
            'messaging' => $ctx->messaging(),
        ]);

        $state->approvalId = (string) $record['id'];
        $requested = new CapabilityApprovalRequested(
            capability: $state->definition->name,
            approvalId: $state->approvalId,
            caller: $state->caller,
        );
        $this->observation->recordApproval($requested);
        if ($this->eventsEnabled) {
            // The approval row is saved; a listener cannot turn it into `internal` (L-201).
            $this->results()->dispatch($requested);
        }

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
        $this->observation->lastRateLimitKey = null;

        if ($this->shouldForceFail(PipelineStages::RATE_LIMIT, $forced)) {
            return $this->rateLimitedResult('Forced failure at rate_limit.');
        }

        // Executing an already-approved request (D-006 accept / resume): the request was
        // counted when it was made and the approver is not the requester, so it must not
        // spend or trip the requester's buckets — a max=1 capability would otherwise burn
        // its approval as a terminal rate_limited row (L-105 / D-013).
        if (isset($state->options['executing_approval_id'])) {
            return null;
        }

        // Agent turn budget (D-013) — checked whenever an in-process adapter supplies the turn's
        // tool-call count (agent tools, AI turns as caller=job). Only ever narrows.
        if (array_key_exists('agent_turn_tool_calls', $state->options)) {
            $calls = (int) $state->options['agent_turn_tool_calls'];
            $perTurn = $state->definition->rateLimit['max_tool_calls_per_turn'] ?? null;
            $budget = $this->agentTurnBudget()->narrowedTo(is_numeric($perTurn) ? (int) $perTurn : null);
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
        $this->observation->lastRateLimitKey = $capKey;

        // Zero limits are edge: always rate_limited when the dimension is active.
        if ($perMinute <= 0 || $perCap <= 0) {
            return $this->rateLimitedResult('Rate limit exceeded.');
        }

        if ($this->rateLimiter->tooManyAttempts($actorKey, $perMinute)) {
            return $this->rateLimitedResult('Rate limit exceeded (per_minute).', $this->rateLimiter->availableIn($actorKey));
        }

        if ($this->rateLimiter->tooManyAttempts($capKey, $perCap)) {
            return $this->rateLimitedResult('Rate limit exceeded (per_capability_per_minute).', $this->rateLimiter->availableIn($capKey));
        }

        $decay = (int) ($override['decay'] ?? 60);
        $this->rateLimiter->hit($actorKey, $decay);
        $this->rateLimiter->hit($capKey, $decay);

        return null;
    }

    /**
     * @param  int  $retryAfter  seconds until the tripped window frees (C-007); omitted when unknown
     */
    private function rateLimitedResult(string $message, int $retryAfter = 0): CapabilityResult
    {
        $extra = array_merge(ErrorCodeMap::wireFields('rate_limited'), ['retryable' => true]);
        if ($retryAfter > 0) {
            $extra['retry_after'] = $retryAfter;
        }

        return CapabilityResult::failure(code: 'rate_limited', message: $message, extra: $extra);
    }

    /**
     * @param  list<string>  $forced
     */
    private function stageRun(InvokeState $state, array $forced): ?CapabilityResult
    {
        $state->mark(PipelineStages::RUN);
        $this->observation->lastRunWasWrapped = false;

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

        // Domain owns its transaction by default; wrap_run is opt-in (D-010) and needs a connection.
        if ($this->wrapRun && $this->transactionConnection === null) {
            return CapabilityResult::failure(
                code: 'not_configured',
                message: 'transactions.wrap_run is enabled but no database connection is wired for the outer transaction (D-010).',
            );
        }

        try {
            $state->runCalled = true;
            $state->runCount++;
            $state->domainSideEffect = true;
            $this->observation->invokeStartedAt ??= microtime(true);
            $handler = $this->handler($state);
            if ($this->wrapRun && $this->transactionConnection !== null) {
                $this->observation->lastRunWasWrapped = true;
                if ($this->holdWrapForAudit($state)) {
                    // Keep the transaction open until strict audit succeeds or rolls it back.
                    $this->transactionConnection->beginTransaction();
                    $state->wrapHeld = true;
                    try {
                        $state->output = $this->executeRun($state->definition, $state->input, $state->context, $handler);
                    } catch (Throwable $e) {
                        $this->settleOpenWrap($state, commit: false);

                        return $this->runFailure($e);
                    }
                } else {
                    $state->output = $this->transactionConnection->transaction(
                        fn (): mixed => $this->executeRun($state->definition, $state->input, $state->context, $handler),
                    );
                }
            } else {
                $state->output = $this->executeRun($state->definition, $state->input, $state->context, $handler);
            }
        } catch (Throwable $e) {
            return $this->runFailure($e);
        }

        return null;
    }

    /**
     * Bug-class errors are reported and hidden; missing models are not_found;
     * anything else is a deliberate domain throw and keeps its message (L-004).
     */
    private function runFailure(Throwable $e): CapabilityResult
    {
        if ($e instanceof ModelNotFoundException) {
            return CapabilityResult::failure(code: 'not_found', message: 'Not found.');
        }

        // QueryException is a PDOException.
        if ($e instanceof Error || $e instanceof PDOException) {
            $this->reportThrowable($e);

            return CapabilityResult::failure(code: 'internal', message: 'Internal error.');
        }

        return CapabilityResult::failure(code: 'domain_error', message: $e->getMessage());
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

        if (! $state->definition->shouldValidateOutput($this->validateOutputEnabled)) {
            return null;
        }

        $check = $this->outputValidator->validate($state->definition, $state->output, $state->context);
        if ($check !== null) {
            return $check;
        }

        return null;
    }

    /**
     * Strict mode records audit before a completed idempotency row. A failure
     * with no open wrap stores audit_failed so a retry replays the error.
     * An open wrap rolls the domain back and releases the processing claim (D-010).
     */
    private function finishStrict(InvokeState $state): CapabilityResult
    {
        $auditFailure = $this->stageRecordAudit($state, success: true);
        if ($auditFailure !== null) {
            if ($state->wrapHeld) {
                $this->settleOpenWrap($state, commit: false);
                $this->releaseIdempotencyClaim($state);
                $auditFailure = $this->uncommittedAuditFailure($state, $auditFailure);
            } else {
                $this->stageStoreIdempotency($state, $auditFailure);
            }

            $this->results()->emitEvents($state, success: false, failure: $auditFailure);

            return $this->results()->wireResponse($state, $auditFailure);
        }

        $this->settleOpenWrap($state, commit: true);
        $this->stageStoreIdempotency($state);
        $this->results()->emitEvents($state, success: true);

        return $this->results()->wireResponse($state, CapabilityResult::success(
            $state->output,
            $this->successMeta($state),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function successMeta(InvokeState $state): array
    {
        $successMeta = [
            'request_id' => $state->requestId,
            'capability' => $state->definition->name,
            'idempotent_replay' => false,
            'stages' => $state->stages,
        ];
        if ($state->definition->deprecated) {
            $successMeta['deprecated'] = true;
            $successMeta['deprecation_warning'] = sprintf(
                'Capability "%s" is deprecated%s.',
                $state->definition->name,
                $state->definition->successor ? '; use '.$state->definition->successor : '',
            );
            if ($state->definition->successor !== null) {
                $successMeta['successor'] = $state->definition->successor;
            }
        }

        return $successMeta;
    }

    private function holdWrapForAudit(InvokeState $state): bool
    {
        return $state->definition->auditMode($this->auditStage->auditMode) === 'strict'
            && $this->auditStage->auditEnabled
            && $this->auditStage->auditWriter !== null
            && $state->definition->shouldAudit();
    }

    private function settleOpenWrap(InvokeState $state, bool $commit): void
    {
        if (! $state->wrapHeld || $this->transactionConnection === null) {
            return;
        }

        if ($commit) {
            $this->transactionConnection->commit();
        } else {
            $this->transactionConnection->rollBack();
            $state->domainSideEffect = false;
        }

        $state->wrapHeld = false;
    }

    private function releaseIdempotencyClaim(InvokeState $state): void
    {
        if ($state->idempotencyKey === null || $state->idempotencyKey === '' || $state->context === null) {
            return;
        }

        $this->idempotencyGuard->releaseClaim($state->definition, $state->context, $state->idempotencyKey);
    }

    private function uncommittedAuditFailure(InvokeState $state, CapabilityResult $failure): CapabilityResult
    {
        $extra = array_diff_key($failure->error ?? [], array_flip(['code', 'message']));
        $extra['domain_committed'] = false;

        return CapabilityResult::failure(
            code: 'audit_failed',
            message: (string) ($failure->error['message'] ?? 'Audit failed.'),
            extra: $extra,
            meta: array_merge($failure->meta, [
                'domain_side_effect' => false,
                'request_id' => $state->requestId,
                'stages' => $state->stages,
            ]),
        );
    }

    private function stageStoreIdempotency(InvokeState $state, ?CapabilityResult $result = null): void
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
        $result ??= CapabilityResult::success($state->output);
        $this->idempotencyGuard->storeResult(
            $state->definition,
            $ctx,
            $state->idempotencyKey,
            (string) $state->requestHash,
            $result,
            $state->approvalId,
        );
    }

    private function stageRecordAudit(InvokeState $state, bool $success, ?CapabilityResult $failure = null): ?CapabilityResult
    {
        return $this->auditStage->record($state, $success, $failure);
    }

    private function executeRun(CapabilityDefinition $definition, mixed $input, mixed $context, ?object $handler): mixed
    {
        if (is_callable($definition->run)) {
            return self::callWithArity($definition->run, $input, $context);
        }

        if ($handler !== null) {
            if (! method_exists($handler, 'run')) {
                throw new InvalidArgumentException(sprintf(
                    'Handler %s has no run() method.',
                    $handler::class,
                ));
            }

            return self::callWithArity([$handler, 'run'], $input, $context);
        }

        throw new InvalidArgumentException('No run handler.');
    }

    /**
     * D-003: pass context only when the signature takes a second argument. Decided
     * up front from the signature so a callable is never invoked twice for one invoke.
     */
    private static function callWithArity(callable $callable, mixed $input, mixed $context): mixed
    {
        $reflection = is_array($callable)
            ? new ReflectionMethod($callable[0], $callable[1])
            : new ReflectionFunction(Closure::fromCallable($callable));

        return self::acceptsContext($reflection)
            ? $callable($input, $context)
            : $callable($input);
    }

    private static function acceptsContext(ReflectionFunctionAbstract $callable): bool
    {
        return $callable->isVariadic() || $callable->getNumberOfParameters() >= 2;
    }

    /**
     * @param  list<string>  $forced
     */
    private function shouldForceFail(string $stage, array $forced): bool
    {
        return in_array($stage, $forced, true);
    }
}
