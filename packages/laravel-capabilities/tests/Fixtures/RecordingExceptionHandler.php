<?php

declare(strict_types=1);

namespace Rawphp\Capabilities\Tests\Fixtures;

use Illuminate\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Rawphp\Capabilities\Contracts\Metrics;
use Rawphp\Capabilities\Observability\InMemoryMetrics;
use Throwable;

/**
 * Host exception handler double bound on the global container, the way the
 * pipeline reports bug-class failures (L-009 / L-103 / L-104). Call {@see unbind()}
 * in afterEach.
 */
final class RecordingExceptionHandler implements ExceptionHandler
{
    /** @var list<Throwable> */
    public array $reported = [];

    public InMemoryMetrics $metrics;

    private function __construct()
    {
        $this->metrics = new InMemoryMetrics;
    }

    public static function bind(): self
    {
        $handler = new self;
        $container = new Container;
        $container->instance(ExceptionHandler::class, $handler);
        $container->instance(Metrics::class, $handler->metrics);
        Container::setInstance($container);

        return $handler;
    }

    public static function unbind(): void
    {
        Container::setInstance(null);
    }

    public function report(Throwable $e): void
    {
        $this->reported[] = $e;
    }

    public function shouldReport(Throwable $e): bool
    {
        return true;
    }

    public function render($request, Throwable $e)
    {
        throw $e;
    }

    public function renderForConsole($output, Throwable $e): void {}
}
