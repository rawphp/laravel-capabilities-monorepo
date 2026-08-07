<?php

declare(strict_types=1);

namespace Rawphp\Capabilities\Adapters\Artisan;

use Illuminate\Console\Command;
use Rawphp\Capabilities\Adapters\Mcp\McpServerRegistrar;
use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Rawphp\Capabilities\Support\IntegrationHealthChecker;
use Rawphp\Capabilities\Support\IntegrationHealthReport;
use Throwable;

/**
 * Product integration readiness (bindings, AI-chat mode, MCP tools).
 *
 * Distinct from HTTP GET …/capabilities/health — do not merge.
 */
class IntegrationHealthCommand extends Command
{
    protected $signature = 'capabilities:integration-health';

    protected $description = 'Diagnose host product readiness (bindings, AI-chat, MCP tools).';

    public function __construct(
        private readonly ?IntegrationHealthChecker $checker = null,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $checker = $this->checker ?? new IntegrationHealthChecker;
        $report = $checker->check(
            $this->capabilitiesConfig(),
            $this->aiConfig(),
            $this->boundCallback(),
            $this->mcpToolCountCallback(),
            $this->idempotencyReadinessClassCallback(),
        );

        $this->render($report);

        return $report->exitCode();
    }

    /**
     * @return array<string, mixed>
     */
    private function capabilitiesConfig(): array
    {
        try {
            $cfg = $this->laravel->make('config')->get('capabilities', []);
        } catch (Throwable) {
            return [];
        }

        return is_array($cfg) ? $cfg : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function aiConfig(): ?array
    {
        try {
            $config = $this->laravel->make('config');
            if (! method_exists($config, 'has') || ! $config->has('capabilities-ai')) {
                // Fall back: get may still return a published array or empty default.
                $cfg = $config->get('capabilities-ai', null);
            } else {
                $cfg = $config->get('capabilities-ai');
            }
        } catch (Throwable) {
            return null;
        }

        return is_array($cfg) ? $cfg : null;
    }

    /**
     * @return callable(class-string): bool
     */
    private function boundCallback(): callable
    {
        return function (string $abstract): bool {
            try {
                return $this->laravel->bound($abstract);
            } catch (Throwable) {
                return false;
            }
        };
    }

    /**
     * Best-effort MCP tool count via plan + registry (read-only; never mounts tools).
     *
     * Uses {@see McpServerRegistrar::plan} and {@see CapabilityRegistry::mcpTools} so
     * diagnostics never call {@see McpServerRegistrar::register} / adapter register on
     * the live singleton (ORI-846 / UR-063). Unbound registry or empty plan → 0.
     *
     * @return (callable(): int)|null
     */
    private function mcpToolCountCallback(): ?callable
    {
        return function (): int {
            $app = $this->laravel;
            if (! $app->bound(CapabilityRegistry::class)) {
                return 0;
            }

            $mcp = [];
            try {
                $cfg = $app->make('config')->get('capabilities.surfaces.mcp', []);
                $mcp = is_array($cfg) ? $cfg : [];
            } catch (Throwable) {
                $mcp = [];
            }

            $rows = McpServerRegistrar::plan($mcp);
            if ($rows === []) {
                return 0;
            }

            /** @var CapabilityRegistry $registry */
            $registry = $app->make(CapabilityRegistry::class);
            $n = 0;
            foreach ($rows as $row) {
                $profile = $row['profile'] ?? null;
                if (! is_string($profile) || $profile === '') {
                    continue;
                }
                $n += count($registry->mcpTools($profile));
            }

            return $n;
        };
    }

    /**
     * @return (callable(): string|null)|null
     */
    private function idempotencyReadinessClassCallback(): ?callable
    {
        return function (): ?string {
            $abstract = 'Rawphp\\CapabilitiesAi\\Contracts\\IdempotencyReadiness';
            try {
                if (! $this->laravel->bound($abstract)) {
                    return null;
                }
                $resolved = $this->laravel->make($abstract);

                return is_object($resolved) ? $resolved::class : null;
            } catch (Throwable) {
                return null;
            }
        };
    }

    private function render(IntegrationHealthReport $report): void
    {
        $this->info("Mode: {$report->mode}");
        $this->newLine();

        foreach ($report->checks as $check) {
            $level = strtoupper((string) ($check['level'] ?? '?'));
            $code = (string) ($check['code'] ?? '');
            $message = (string) ($check['message'] ?? '');
            $line = "[{$level}] {$code}: {$message}";

            match ($check['level'] ?? '') {
                'fail' => $this->error($line),
                'warn' => $this->warn($line),
                default => $this->line($line),
            };
        }

        $this->newLine();
        if ($report->failed()) {
            $this->error('Integration health: FAILED');
        } else {
            $this->info('Integration health: OK');
        }
    }
}
