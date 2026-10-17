<?php

// A run() may refuse with conflict (409) or rate_limited (429) by throwing CapabilityFailure.
// Everything else matches a thrown domain exception: rollback, audit, idempotency.

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\ConnectionInterface;
use Rawphp\Capabilities\Http\CliJsonEnvelope;
use Rawphp\Capabilities\Support\CapabilityFailure;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Tests\Fixtures\AuditHelpers;
use Rawphp\Capabilities\Tests\Fixtures\HttpHelpers;
use Rawphp\Capabilities\Tests\Fixtures\PipelineHelpers;

function capabilityFailureInvoke(Throwable $e): CapabilityResult
{
    $h = PipelineHelpers::harness(['run' => function () use ($e) {
        throw $e;
    }]);

    return $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http'));
}

function capabilityFailureConnection(): ConnectionInterface
{
    $capsule = new Capsule;
    $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    $connection = $capsule->getConnection();
    $connection->statement('create table wrap_probe (id integer primary key, note text)');

    return $connection;
}

it('happy: CapabilityFailure is a final RuntimeException built only through its named constructors', function () {
    $class = new ReflectionClass(CapabilityFailure::class);

    expect($class->isFinal())->toBeTrue()
        ->and($class->isSubclassOf(RuntimeException::class))->toBeTrue()
        ->and($class->getConstructor()?->isPrivate())->toBeTrue()
        ->and(CapabilityFailure::conflict('x'))->toBeInstanceOf(RuntimeException::class);
});

it('happy: a thrown conflict maps to 409 conflict with its message, not retryable', function () {
    $result = capabilityFailureInvoke(CapabilityFailure::conflict('Walkthrough already published.'));

    expect($result->errorCode())->toBe('conflict')
        ->and($result->error['message'])->toBe('Walkthrough already published.')
        ->and($result->error['http_status'])->toBe(409)
        ->and($result->error['retryable'])->toBeFalse()
        ->and($result->error['cli_exit'])->toBe(5)
        ->and($result->error)->not->toHaveKey('retry_after');
});

it('happy: a thrown rateLimited without retryAfter maps to 429 rate_limited, retryable, no retry_after', function () {
    $result = capabilityFailureInvoke(CapabilityFailure::rateLimited('Too many walkthroughs today.'));

    expect($result->errorCode())->toBe('rate_limited')
        ->and($result->error['message'])->toBe('Too many walkthroughs today.')
        ->and($result->error['http_status'])->toBe(429)
        ->and($result->error['retryable'])->toBeTrue()
        ->and($result->error['cli_exit'])->toBe(6)
        ->and($result->error)->not->toHaveKey('retry_after');
});

it('happy: a thrown rateLimited with retryAfter carries retry_after seconds', function () {
    $result = capabilityFailureInvoke(CapabilityFailure::rateLimited('Slow down.', 30));

    expect($result->errorCode())->toBe('rate_limited')
        ->and($result->error['http_status'])->toBe(429)
        ->and($result->error['retryable'])->toBeTrue()
        ->and($result->error['retry_after'])->toBe(30);
});

it('edge: a thrown rateLimited with a retryAfter below 1 sends no retry_after, like a pipeline rate_limited', function () {
    $zero = capabilityFailureInvoke(CapabilityFailure::rateLimited('Slow down.', 0));
    $negative = capabilityFailureInvoke(CapabilityFailure::rateLimited('Slow down.', -5));

    expect($zero->errorCode())->toBe('rate_limited')
        ->and($zero->error)->not->toHaveKey('retry_after')
        ->and($negative->error)->not->toHaveKey('retry_after');
});

it('fail: a conflict thrown under wrap_run rolls the domain write back', function () {
    $connection = capabilityFailureConnection();
    $h = AuditHelpers::harness([
        'transactions' => ['wrap_run' => true],
        'transaction_connection' => $connection,
        'run' => function () use ($connection) {
            $connection->table('wrap_probe')->insert(['note' => 'partial write']);
            throw CapabilityFailure::conflict('Already published.');
        },
    ]);

    $result = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options());

    expect($result->errorCode())->toBe('conflict')
        ->and($h['registry']->lastRunWasWrapped())->toBeTrue()
        ->and($connection->table('wrap_probe')->count())->toBe(0)
        ->and($connection->transactionLevel())->toBe(0);
});

it('fail: a conflict thrown under wrap_run with strict audit rolls the held wrap back', function () {
    $connection = capabilityFailureConnection();
    $h = AuditHelpers::harness([
        'mode' => 'strict',
        'transactions' => ['wrap_run' => true],
        'transaction_connection' => $connection,
        'run' => function () use ($connection) {
            $connection->table('wrap_probe')->insert(['note' => 'partial write']);
            throw CapabilityFailure::conflict('Already published.');
        },
    ]);

    $result = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options());

    expect($result->errorCode())->toBe('conflict')
        ->and($h['registry']->lastRunWasWrapped())->toBeTrue()
        ->and($connection->table('wrap_probe')->count())->toBe(0)
        ->and($connection->transactionLevel())->toBe(0);
});

it('happy: audit records a thrown conflict as a failure with its code, like a domain_error', function () {
    $h = AuditHelpers::harness(['run' => fn () => throw CapabilityFailure::conflict('Already published.')]);

    $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options());

    expect($h['audit']->all())->toHaveCount(1)
        ->and($h['audit']->all()[0]['result'])->toBe([
            'ok' => false,
            'code' => 'conflict',
            'message' => 'Already published.',
        ]);
});

it('happy: idempotency stores a thrown rate_limited and replays it without running again', function () {
    $runs = 0;
    $h = AuditHelpers::harness(['run' => function () use (&$runs) {
        $runs++;
        throw CapabilityFailure::rateLimited('Slow down.', 30);
    }]);
    $options = AuditHelpers::options('http', ['idempotency_key' => 'capability-failure-replay-1']);

    $first = $h['registry']->invoke($h['name'], AuditHelpers::input(), $options);
    $second = $h['registry']->invoke($h['name'], AuditHelpers::input(), $options);

    expect($first->errorCode())->toBe('rate_limited')
        ->and($second->errorCode())->toBe('rate_limited')
        ->and($second->error['retry_after'])->toBe(30)
        ->and($second->meta['idempotent_replay'] ?? null)->toBeTrue()
        ->and($runs)->toBe(1);
});

it('happy: HTTP invoke returns 409 with the conflict envelope for a thrown conflict', function () {
    $h = HttpHelpers::harness(['run' => fn () => throw CapabilityFailure::conflict('Already published.')]);

    $res = $h['controller']->invoke(HttpHelpers::authedRequest([
        'method' => 'POST',
        'jsonBody' => PipelineHelpers::validInput(),
    ]), $h['name']);

    expect($res->status)->toBe(409)
        ->and($res->body['ok'])->toBeFalse()
        ->and($res->body['error']['code'])->toBe('conflict')
        ->and($res->body['error']['message'])->toBe('Already published.')
        ->and($res->body['error']['retryable'])->toBeFalse()
        ->and($res->headers)->not->toHaveKey('Retry-After');
});

it('happy: HTTP invoke returns 429 with Retry-After for a thrown rateLimited with retryAfter', function () {
    $h = HttpHelpers::harness(['run' => fn () => throw CapabilityFailure::rateLimited('Slow down.', 45)]);

    $res = $h['controller']->invoke(HttpHelpers::authedRequest([
        'method' => 'POST',
        'jsonBody' => PipelineHelpers::validInput(),
    ]), $h['name']);

    expect($res->status)->toBe(429)
        ->and($res->body['error']['code'])->toBe('rate_limited')
        ->and($res->body['error']['retry_after'])->toBe(45)
        ->and($res->headers['Retry-After'])->toBe('45');
});

it('happy: the product CLI exits with the ErrorCodeMap code for both thrown failures', function () {
    $conflict = capabilityFailureInvoke(CapabilityFailure::conflict('Already published.'));
    $limited = capabilityFailureInvoke(CapabilityFailure::rateLimited('Slow down.'));

    expect(CliJsonEnvelope::exitCode($conflict))->toBe(5)
        ->and(CliJsonEnvelope::exitCode($limited))->toBe(6);
});
