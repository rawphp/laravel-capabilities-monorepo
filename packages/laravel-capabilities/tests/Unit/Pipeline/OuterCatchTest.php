<?php

// L-009: a throwable escaping any pipeline stage (authorize, stores, rate limiter, output
// validation, strict audit) is reported and hidden like a run-stage bug, and the invoke still
// goes through the failure finish so a claimed Idempotency-Key is released (D-005).

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Rawphp\Capabilities\Contracts\IdempotencyStore;
use Rawphp\Capabilities\Contracts\RateLimiter;
use Rawphp\Capabilities\Support\InMemoryIdempotencyStore;
use Rawphp\Capabilities\Tests\Fixtures\PipelineHelpers;

function outerCatchReporter(): object
{
    $reporter = new class implements ExceptionHandler
    {
        /** @var list<Throwable> */
        public array $reported = [];

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
    };

    $container = new Container;
    $container->instance(ExceptionHandler::class, $reporter);
    Container::setInstance($container);

    return $reporter;
}

afterEach(function () {
    Container::setInstance(null);
});

it('fail: an exception thrown by authorize() is reported and returned as a generic internal error [L-009]', function () {
    $reporter = outerCatchReporter();
    $e = new RuntimeException('SQLSTATE[42S22]: select * from secrets where token = ?');
    $h = PipelineHelpers::harness(['authorize_cb' => function () use ($e) {
        throw $e;
    }]);

    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options());

    expect($result->errorCode())->toBe('internal')
        ->and($result->error['message'])->toBe('Internal error.')
        ->and($result->error['http_status'])->toBe(500)
        ->and(json_encode($result->toArray()))->not->toContain('SQLSTATE')
        ->and($reporter->reported)->toBe([$e])
        ->and($h['runCount']->value)->toBe(0)
        ->and($h['registry']->failedEvents())->toHaveCount(1)
        ->and($h['registry']->failedEvents()[0]->code)->toBe('internal')
        ->and($h['registry']->failedEvents()[0]->message)->toBe('Internal error.');
});

it('happy: a claimed idempotency key is released as a stored failure, so a retry replays instead of busy [L-009 / D-005]', function () {
    outerCatchReporter();
    $throw = true;
    $h = PipelineHelpers::harness(['authorize_cb' => function () use (&$throw) {
        if ($throw) {
            throw new LogicException('transient');
        }

        return true;
    }]);
    $options = PipelineHelpers::options('http', ['idempotency_key' => 'idem-outer-1']);

    $first = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), $options);
    $throw = false;
    $second = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), $options);

    expect($first->errorCode())->toBe('internal')
        ->and($second->errorCode())->toBe('internal')
        ->and($second->meta['idempotent_replay'] ?? null)->toBeTrue()
        ->and($second->error['message'])->not->toContain('processing')
        ->and($h['runCount']->value)->toBe(0);
});

it('fail: a rate limiter that throws is sanitised the same way and run() is not called [L-009]', function () {
    $reporter = outerCatchReporter();
    $limiter = new class implements RateLimiter
    {
        public function tooManyAttempts(string $key, int $maxAttempts): bool
        {
            throw new RuntimeException('redis: connection refused at 10.0.0.9:6379');
        }

        public function hit(string $key, int $decaySeconds = 60): int
        {
            return 1;
        }

        public function remaining(string $key, int $maxAttempts): int
        {
            return $maxAttempts;
        }

        public function clear(string $key): void {}
    };
    $h = PipelineHelpers::harness(['rate_limiter' => $limiter]);

    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options());

    expect($result->errorCode())->toBe('internal')
        ->and($result->error['message'])->toBe('Internal error.')
        ->and($reporter->reported)->toHaveCount(1)
        ->and($h['runCount']->value)->toBe(0);
});

it('edge: when the failure finish itself throws, the caller still gets the internal envelope [L-009]', function () {
    $reporter = outerCatchReporter();
    $inner = new InMemoryIdempotencyStore(PipelineHelpers::harness()['fakes']->clock);
    $store = new class($inner) implements IdempotencyStore
    {
        public function __construct(private IdempotencyStore $inner) {}

        public function find(?string $tenantId, string $actorType, string $actorId, string $capabilityName, string $key): ?array
        {
            return $this->inner->find($tenantId, $actorType, $actorId, $capabilityName, $key);
        }

        public function put(array $record): array
        {
            throw new RuntimeException('store down');
        }

        public function claim(array $record): bool
        {
            return $this->inner->claim($record);
        }

        public function update(?string $tenantId, string $actorType, string $actorId, string $capabilityName, string $key, array $attributes): ?array
        {
            return $this->inner->update($tenantId, $actorType, $actorId, $capabilityName, $key, $attributes);
        }
    };
    $h = PipelineHelpers::harness(['authorize_cb' => function () {
        throw new RuntimeException('boom');
    }]);
    $h['registry']->withIdempotencyStore($store);

    $result = $h['registry']->invoke(
        $h['name'],
        PipelineHelpers::validInput(),
        PipelineHelpers::options('http', ['idempotency_key' => 'idem-outer-2']),
    );

    expect($result->errorCode())->toBe('internal')
        ->and($result->error['message'])->toBe('Internal error.')
        ->and($result->meta['stages'] ?? [])->toContain('wire_response')
        ->and(count($reporter->reported))->toBe(2)
        ->and($reporter->reported[1]->getMessage())->toBe('store down');
});
