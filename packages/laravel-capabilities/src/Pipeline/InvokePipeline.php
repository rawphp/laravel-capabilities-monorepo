<?php

namespace Rawphp\Capabilities\Pipeline;

use InvalidArgumentException;
use Rawphp\Capabilities\Approval\ApprovalManager;
use Rawphp\Capabilities\Contracts\Authorizer;
use Rawphp\Capabilities\Contracts\RateLimiter;
use Rawphp\Capabilities\Contracts\SchemaProvider;
use Rawphp\Capabilities\Events\CapabilityApprovalRequested;
use Rawphp\Capabilities\Events\CapabilityFailed;
use Rawphp\Capabilities\Events\CapabilityInvoked;
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
use Rawphp\Capabilities\Support\SystemActor;
use Throwable;

/**
 * Ordered capability invoke pipeline (PIPE-001).
 *
 * Extracted from {@see CapabilityRegistry} so the
 * registry remains a thin bus facade over definition catalog + pipeline.
 */
final class InvokePipeline
{
    /**
     * @param  array{
     *     enabled?: bool,
     *     defaults?: array{per_minute?: int, per_capability_per_minute?: int},
     *     agent_turn?: array{max_tool_calls?: int}
     * }  $rateLimitConfig
     */
    public function __construct(
        public JsonSchemaValidator $jsonSchema,
        public ServerRuleChecker $serverRuleChecker,
        public ResolveActor $resolveActor,
        public ResolveTenantFromCaller $resolveTenant,
        public IdempotencyGuard $idempotencyGuard,
        public Authorizer $authorizer,
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
    ) {}

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
                return $this->stageWireResponse($state, $auditFailure);
            }

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

            return $this->stageWireResponse($state, CapabilityResult::success(
                $state->output,
                $successMeta,
            ));
        } catch (Throwable $e) {
            $state->mark(PipelineStages::WIRE_RESPONSE);
            $this->observation->lastState = $state;
            $this->observation->lastStages = $state->stages;
            $this->recordFailure($state->definition, $e->getMessage(), $state->caller, 'internal');

            return CapabilityResult::failure(
                code: 'internal',
                message: $e->getMessage(),
                meta: ['request_id' => $state->requestId, 'stages' => $state->stages],
            );
        }
    }

    public function finishEarly(CapabilityResult $result, ?InvokeState $state): CapabilityResult
    {
        $this->observation->lastStages = $state?->stages ?? [];
        $this->observation->lastState = $state;

        return $result;
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
        $this->observation->approvalEvents[] = new CapabilityApprovalRequested(
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
        $this->observation->lastRateLimitKey = null;

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
        $this->observation->lastRateLimitKey = $capKey;

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

        try {
            $state->runCalled = true;
            $state->runCount++;
            $state->domainSideEffect = true;
            // Domain owns its transaction by default; wrap_run is opt-in (D-010).
            if ($this->wrapRun) {
                $this->observation->lastRunWasWrapped = true;
            }
            $this->observation->invokeStartedAt ??= microtime(true);
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

        if (! $state->definition->shouldValidateOutput($this->validateOutputEnabled)) {
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

    private function stageRecordAudit(InvokeState $state, bool $success, ?CapabilityResult $failure = null): ?CapabilityResult
    {
        return $this->auditStage->record($state, $success, $failure);
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
            $this->observation->invokedEvents[] = $event;
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
        $this->observation->lastState = $state;
        $this->observation->lastStages = $state->stages;

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
            if ($this->observation->failedEvents === [] || ! $state->hasStage(PipelineStages::EMIT_EVENTS)) {
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
        $this->observation->failedEvents[] = $event;
        $this->observation->logs[] = [
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
}
