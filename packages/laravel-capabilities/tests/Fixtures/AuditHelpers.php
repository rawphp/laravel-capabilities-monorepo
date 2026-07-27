<?php

declare(strict_types=1);

namespace Rawphp\Capabilities\Tests\Fixtures;

use Rawphp\Capabilities\Audit\AuditOutbox;
use Rawphp\Capabilities\Capability;
use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Rawphp\Capabilities\Support\FailingAuditWriter;
use Rawphp\Capabilities\Support\FixedClock;
use Rawphp\Capabilities\Support\InMemoryAuditWriter;
use Rawphp\Capabilities\Tests\Support\SharedFakes;
use stdClass;

/**
 * Shared builders for Audit/* unit tests (D-010).
 */
final class AuditHelpers
{
    /**
     * @param  array<string, mixed>  $opts
     * @return array{
     *     registry: CapabilityRegistry,
     *     fakes: SharedFakes,
     *     runCount: stdClass,
     *     name: string,
     *     outbox: AuditOutbox,
     *     clock: FixedClock
     * }
     */
    public static function harness(array $opts = []): array
    {
        $fakes = SharedFakes::create(authorize: (bool) ($opts['authorize'] ?? true));
        $outbox = $opts['outbox'] ?? new AuditOutbox($fakes->clock);
        $auditWriter = $opts['audit_writer'] ?? $fakes->audit;
        if (($opts['fail_audit'] ?? false) === true) {
            $auditWriter = new FailingAuditWriter((string) ($opts['fail_message'] ?? 'disk full'));
        }

        $registry = new CapabilityRegistry(
            globallyEnabledSurfaces: [
                'agent' => true,
                'mcp' => true,
                'http' => true,
                'cli' => true,
                'job' => true,
                'artisan' => true,
                'messaging' => false,
            ],
            validationConfig: ['validate_output' => true],
            authorizer: $fakes->authorizer,
            approvalStore: $fakes->approvals,
            idempotencyStore: $fakes->idempotency,
            auditWriter: $auditWriter,
            rateLimiter: $fakes->rateLimiter,
            auditMode: $opts['mode'] ?? 'best_effort',
            auditConfig: array_merge([
                'enabled' => $opts['audit_enabled'] ?? true,
                'mode' => $opts['mode'] ?? 'best_effort',
                'required' => $opts['required'] ?? false,
                'driver' => $opts['driver'] ?? 'database',
            ], $opts['audit_config'] ?? []),
            rateLimitConfig: $opts['rate_limit_config'] ?? ['enabled' => false],
            transactionsConfig: $opts['transactions'] ?? ['wrap_run' => false],
            eventsConfig: $opts['events'] ?? ['enabled' => true],
            auditOutbox: $outbox,
        );

        if (isset($opts['throw_on_audit'])) {
            $registry->throwOnAuditFailure((bool) $opts['throw_on_audit']);
        }

        $runCount = new stdClass;
        $runCount->value = 0;
        $runCount->sideEffect = false;
        $runCount->domainEvents = [];

        $name = $opts['name'] ?? 'audit-cap';
        $builder = Capability::define($name)
            ->description('audit test capability')
            ->surfaces(['agent', 'mcp', 'http', 'cli', 'job', 'artisan'])
            ->input($opts['input'] ?? CreateInvoiceInput::class)
            ->output($opts['output'] ?? CreateInvoiceResult::class)
            ->idempotent($opts['idempotent'] ?? 'optional')
            ->audit($opts['audit'] ?? true)
            ->allowSystemCallers(true);

        if (($opts['readOnly'] ?? false) === true) {
            $builder->readOnly(true);
        }

        if (isset($opts['approvalPolicy'])) {
            $builder->approvalPolicy($opts['approvalPolicy']);
        }

        $run = $opts['run'] ?? function ($in) use ($runCount, $opts) {
            $runCount->value++;
            $runCount->sideEffect = true;
            if (isset($opts['domain_event'])) {
                $runCount->domainEvents[] = $opts['domain_event'];
            }
            if (isset($opts['run_throws'])) {
                throw new \RuntimeException((string) $opts['run_throws']);
            }

            return new CreateInvoiceResult(invoice_id: 42);
        };
        $builder->run($run)->register($registry);

        return [
            'registry' => $registry,
            'fakes' => $fakes,
            'runCount' => $runCount,
            'name' => $name,
            'outbox' => $outbox,
            'clock' => $fakes->clock,
            'audit' => $auditWriter instanceof InMemoryAuditWriter ? $auditWriter : $fakes->audit,
        ];
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function options(string $caller = 'http', array $extra = []): array
    {
        return PipelineHelpers::options($caller, $extra);
    }

    /**
     * @return array<string, mixed>
     */
    public static function input(): array
    {
        return PipelineHelpers::validInput();
    }
}
