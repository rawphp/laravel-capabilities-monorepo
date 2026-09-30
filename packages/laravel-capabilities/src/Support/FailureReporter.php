<?php

namespace Rawphp\Capabilities\Support;

use Illuminate\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Rawphp\Capabilities\Contracts\Metrics;
use Throwable;

/**
 * Hand a swallowed throwable to the host's ExceptionHandler (and a metric to the bound
 * {@see Metrics}) without letting it change the invoke outcome (L-009 / L-103 / L-104 / L-201).
 *
 * Resolves both lazily from the global container so pure registry construction in unit
 * tests needs neither; when nothing is bound the failure is dropped here, but every call
 * site also keeps its own observation-log line.
 */
final class FailureReporter
{
    public const AUDIT_WRITE_FAILED = 'audit_write_failed_total';

    public const LISTENER_FAILED = 'bus_listener_failed_total';

    public const APPROVAL_NOTIFY_FAILED = 'approval_notify_failed_total';

    /** A host ScopeResolver threw while placing an approver / resuming user (L-402). */
    public const APPROVER_SCOPE_FAILED = 'approver_scope_failed_total';

    public static function report(Throwable $e): void
    {
        $container = Container::getInstance();
        if ($container->bound(ExceptionHandler::class)) {
            $container->make(ExceptionHandler::class)->report($e);
        }
    }

    /**
     * @param  array<string, string>  $labels
     */
    public static function count(string $metric, array $labels = []): void
    {
        $container = Container::getInstance();
        if ($container->bound(Metrics::class)) {
            $metrics = $container->make(Metrics::class);
            if ($metrics instanceof Metrics) {
                $metrics->increment($metric, 1, $labels);
            }
        }
    }

    /**
     * @param  array<string, string>  $labels
     */
    public static function reportAndCount(Throwable $e, string $metric, array $labels = []): void
    {
        self::report($e);
        self::count($metric, $labels);
    }
}
