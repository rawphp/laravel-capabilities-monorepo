<?php

declare(strict_types=1);

namespace Rawphp\Capabilities\Support;

use Rawphp\Capabilities\Contracts\Authorizer;
use Throwable;

/**
 * Pure host product-readiness checks for `capabilities:integration-health`.
 *
 * No HTTP. AI-chat (health only) = capabilities-ai.routes.enabled OR non-empty
 * capabilities-ai.queue.name. Core does not require the AI package — AI contracts
 * are probed by class-string via the injected $bound callback.
 */
final class IntegrationHealthChecker
{
    /** Surfaces that perform authorized invokes (authorizer required when any enabled). */
    private const INVOKE_SURFACES = ['http', 'mcp', 'agent', 'job', 'cli'];

    /** Class-string probes for AI package (string-only; no hard dependency). */
    public const AI_CONTEXT = 'Rawphp\\CapabilitiesAi\\Contracts\\ConversationContextProvider';

    public const AI_TOOL_CATALOG = 'Rawphp\\CapabilitiesAi\\Contracts\\ToolCatalog';

    /**
     * @param  array<string, mixed>  $capabilitiesConfig
     * @param  array<string, mixed>|null  $aiConfig  null when AI package config absent
     * @param  callable(class-string): bool  $bound
     * @param  (callable(): int)|null  $mcpToolCount  null to skip live tool count
     * @param  (callable(): string|null)|null  $idempotencyReadinessClass  resolved class or null
     */
    public function check(
        array $capabilitiesConfig,
        ?array $aiConfig,
        callable $bound,
        ?callable $mcpToolCount = null,
        ?callable $idempotencyReadinessClass = null,
    ): IntegrationHealthReport {
        $checks = [];
        $aiChat = $this->isAiChat($aiConfig);
        $mode = $aiChat ? 'ai-chat' : 'bus-only';

        $checks[] = $this->checkAuthorizer($capabilitiesConfig, $bound);

        if ($aiChat) {
            array_push($checks, ...$this->checkAiChat($aiConfig ?? [], $bound, $idempotencyReadinessClass));
        }

        $checks[] = $this->checkMcp($capabilitiesConfig, $mcpToolCount);

        return new IntegrationHealthReport($mode, $checks);
    }

    /**
     * @param  array<string, mixed>|null  $ai
     */
    private function isAiChat(?array $ai): bool
    {
        if ($ai === null) {
            return false;
        }

        $routesOn = (bool) ($ai['routes']['enabled'] ?? false);
        $queueName = $ai['queue']['name'] ?? null;
        $queueOn = is_string($queueName) && $queueName !== '';

        return $routesOn || $queueOn;
    }

    /**
     * @param  array<string, mixed>  $capabilitiesConfig
     * @param  callable(class-string): bool  $bound
     * @return array{level: 'fail'|'warn'|'ok'|'skip', code: string, message: string}
     */
    private function checkAuthorizer(array $capabilitiesConfig, callable $bound): array
    {
        $surfaces = is_array($capabilitiesConfig['surfaces'] ?? null)
            ? $capabilitiesConfig['surfaces']
            : [];

        $anyInvoke = false;
        foreach (self::INVOKE_SURFACES as $name) {
            $cfg = is_array($surfaces[$name] ?? null) ? $surfaces[$name] : [];
            if ((bool) ($cfg['enabled'] ?? true)) {
                $anyInvoke = true;
                break;
            }
        }

        if (! $anyInvoke) {
            return [
                'level' => 'skip',
                'code' => 'authorizer_bound',
                'message' => 'No authorized-invoke surfaces enabled (http/mcp/agent/job/cli); authorizer not required.',
            ];
        }

        if ($bound(Authorizer::class)) {
            return [
                'level' => 'ok',
                'code' => 'authorizer_bound',
                'message' => 'Authorizer is bound.',
            ];
        }

        return [
            'level' => 'fail',
            'code' => 'authorizer_bound',
            'message' => 'Authorizer is not bound; authorized invoke surfaces are enabled.',
        ];
    }

    /**
     * @param  array<string, mixed>  $ai
     * @param  callable(class-string): bool  $bound
     * @param  (callable(): string|null)|null  $idempotencyReadinessClass
     * @return list<array{level: 'fail'|'warn'|'ok'|'skip', code: string, message: string}>
     */
    private function checkAiChat(array $ai, callable $bound, ?callable $idempotencyReadinessClass): array
    {
        $out = [];

        $out[] = $bound(self::AI_CONTEXT)
            ? ['level' => 'ok', 'code' => 'ai_context_bound', 'message' => 'ConversationContextProvider is bound.']
            : ['level' => 'fail', 'code' => 'ai_context_bound', 'message' => 'ConversationContextProvider is not bound (AI-chat).'];

        $out[] = $bound(self::AI_TOOL_CATALOG)
            ? ['level' => 'ok', 'code' => 'ai_tool_catalog_bound', 'message' => 'ToolCatalog is bound.']
            : ['level' => 'fail', 'code' => 'ai_tool_catalog_bound', 'message' => 'ToolCatalog is not bound (AI-chat).'];

        $claimTtl = (int) ($ai['claim_ttl'] ?? 0);
        $out[] = $claimTtl > 0
            ? ['level' => 'ok', 'code' => 'ai_claim_ttl', 'message' => "claim_ttl is {$claimTtl}."]
            : ['level' => 'fail', 'code' => 'ai_claim_ttl', 'message' => 'claim_ttl must be > 0 for AI-chat.'];

        $proposalsOn = (bool) ($ai['proposals']['enabled'] ?? true);
        if (! $proposalsOn) {
            $out[] = [
                'level' => 'skip',
                'code' => 'ai_always_ready',
                'message' => 'proposals.enabled is false; AlwaysReady readiness check skipped.',
            ];
        } else {
            $class = null;
            if ($idempotencyReadinessClass !== null) {
                try {
                    $class = $idempotencyReadinessClass();
                } catch (Throwable) {
                    $class = null;
                }
            }
            $class = is_string($class) ? $class : null;
            if ($class !== null && $this->isAlwaysReadyClass($class)) {
                $out[] = [
                    'level' => 'fail',
                    'code' => 'ai_always_ready',
                    'message' => "IdempotencyReadiness is AlwaysReady ({$class}); unsafe when proposals.enabled.",
                ];
            } else {
                $out[] = [
                    'level' => 'ok',
                    'code' => 'ai_always_ready',
                    'message' => $class === null
                        ? 'IdempotencyReadiness is not AlwaysReady (unresolved or live).'
                        : "IdempotencyReadiness is {$class} (not AlwaysReady).",
                ];
            }
        }

        $progressDriver = (string) ($ai['progress']['driver'] ?? 'array');
        if ($progressDriver === 'array') {
            $out[] = [
                'level' => 'fail',
                'code' => 'ai_progress_array',
                'message' => 'progress.driver is array (not allowed for AI-chat production; set redis or CAPABILITIES_AI_ALLOW_UNSAFE for demos).',
            ];
        } else {
            $out[] = [
                'level' => 'ok',
                'code' => 'ai_progress_array',
                'message' => "progress.driver is {$progressDriver}.",
            ];
        }

        $routesOn = (bool) ($ai['routes']['enabled'] ?? false);
        $queueName = $ai['queue']['name'] ?? null;
        $queueEmpty = ! is_string($queueName) || $queueName === '';
        // Fail when AI-chat entered via routes only and queue.name empty (ops label required).
        if ($routesOn && $queueEmpty) {
            $out[] = [
                'level' => 'fail',
                'code' => 'ai_queue_name_empty',
                'message' => 'AI-chat via routes only; queue.name empty (set CAPABILITIES_AI_QUEUE_NAME for workers).',
            ];
        } else {
            $out[] = [
                'level' => 'ok',
                'code' => 'ai_queue_name_empty',
                'message' => $queueEmpty
                    ? 'queue.name empty but AI-chat not routes-only.'
                    : 'queue.name is set.',
            ];
        }

        return $out;
    }

    private function isAlwaysReadyClass(string $class): bool
    {
        $base = strrchr($class, '\\');
        $short = $base === false ? $class : substr($base, 1);

        return str_contains($short, 'AlwaysReady');
    }

    /**
     * @param  array<string, mixed>  $capabilitiesConfig
     * @param  (callable(): int)|null  $mcpToolCount
     * @return array{level: 'fail'|'warn'|'ok'|'skip', code: string, message: string}
     */
    private function checkMcp(array $capabilitiesConfig, ?callable $mcpToolCount): array
    {
        $surfaces = is_array($capabilitiesConfig['surfaces'] ?? null)
            ? $capabilitiesConfig['surfaces']
            : [];
        $mcp = is_array($surfaces['mcp'] ?? null) ? $surfaces['mcp'] : [];
        $mcpEnabled = (bool) ($mcp['enabled'] ?? true);

        if (! $mcpEnabled) {
            return [
                'level' => 'skip',
                'code' => 'mcp_tools',
                'message' => 'MCP surface disabled.',
            ];
        }

        if (! $this->mcpPlanNonEmpty($mcp)) {
            return [
                'level' => 'skip',
                'code' => 'mcp_tools',
                'message' => 'MCP plan empty (no profiles/servers); tool count not required.',
            ];
        }

        if ($mcpToolCount === null) {
            return [
                'level' => 'skip',
                'code' => 'mcp_tools',
                'message' => 'MCP tool count probe not provided.',
            ];
        }

        try {
            $count = (int) $mcpToolCount();
        } catch (Throwable $e) {
            return [
                'level' => 'fail',
                'code' => 'mcp_register',
                'message' => 'MCP register/tool count failed: '.$e->getMessage(),
            ];
        }

        if ($count === 0) {
            return [
                'level' => 'fail',
                'code' => 'mcp_tools',
                'message' => 'MCP plan is non-empty but zero tools registered.',
            ];
        }

        return [
            'level' => 'ok',
            'code' => 'mcp_tools',
            'message' => "MCP tools registered: {$count}.",
        ];
    }

    /**
     * @param  array<string, mixed>  $mcp
     */
    private function mcpPlanNonEmpty(array $mcp): bool
    {
        $profiles = $mcp['profiles'] ?? [];
        if (is_array($profiles) && $profiles !== []) {
            return true;
        }

        $servers = $mcp['servers'] ?? [];

        return is_array($servers) && $servers !== [];
    }
}
