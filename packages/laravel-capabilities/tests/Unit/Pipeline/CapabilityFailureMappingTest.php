<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager;
use Rawphp\Capabilities\Http\CliJsonEnvelope;
use Rawphp\Capabilities\Http\HttpResponse;
use Rawphp\Capabilities\Support\CapabilityFailure;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Tests\Fixtures\AuditHelpers;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceResult;
use Rawphp\Capabilities\Tests\Fixtures\PipelineHelpers;
use Rawphp\Capabilities\Tests\Fixtures\RecordingExceptionHandler;

/**
 * A run() that throws CapabilityFailure refuses with that mapped code on every
 * surface, instead of the generic domain_error.
 */
function invokeFailing(CapabilityFailure $e, string $caller = 'http', array $extra = []): CapabilityResult
{
    $h = PipelineHelpers::harness(['run' => function () use ($e) {
        throw $e;
    }]);

    return $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, $extra));
}

afterEach(function () {
    Container::setInstance(null);
});

it('builds a conflict failure with its code and message', function () {
    $e = CapabilityFailure::conflict('Session is already closed.');

    expect($e)->toBeInstanceOf(RuntimeException::class)
        ->and($e->code())->toBe('conflict')
        ->and($e->getMessage())->toBe('Session is already closed.')
        ->and($e->extra())->toBe([]);
});

it('builds a rate_limited failure that carries retry_after in extra', function () {
    $e = CapabilityFailure::rateLimited('Too many wrong emails.', 600);

    expect($e->code())->toBe('rate_limited')
        ->and($e->getMessage())->toBe('Too many wrong emails.')
        ->and($e->extra())->toBe(['retry_after' => 600]);
});

it('accepts any mapped code through the constructor', function () {
    $e = new CapabilityFailure('gone', 'Link expired.', ['hint' => 'ask again']);

    expect($e->code())->toBe('gone')
        ->and($e->extra())->toBe(['hint' => 'ask again']);
});

it('takes code, message and extra as named arguments', function () {
    $e = new CapabilityFailure(code: 'conflict', message: 'Already closed.', extra: ['state' => 'closed']);

    expect($e->code())->toBe('conflict')
        ->and($e->getMessage())->toBe('Already closed.')
        ->and($e->extra())->toBe(['state' => 'closed']);
});

it('refuses a code that ErrorCodeMap does not know at construction', function () {
    new CapabilityFailure('teapot', 'I am a teapot.');
})->throws(InvalidArgumentException::class, 'teapot');

it('refuses approval_required, which only the approval gate may produce', function () {
    new CapabilityFailure('approval_required', 'Needs sign-off.');
})->throws(InvalidArgumentException::class, 'approval_required');

it('refuses extra keys that would override the code\'s wire fields', function (string $key) {
    new CapabilityFailure('conflict', 'Closed.', [$key => 200]);
})->throws(InvalidArgumentException::class)->with(['http_status', 'cli_exit', 'retryable']);

it('maps a thrown conflict to 409 conflict with the message kept, unreported', function () {
    $reporter = RecordingExceptionHandler::bind();

    $result = invokeFailing(CapabilityFailure::conflict('Session is already closed.'));

    expect($result->errorCode())->toBe('conflict')
        ->and($result->error['http_status'])->toBe(409)
        ->and($result->error['cli_exit'])->toBe(5)
        ->and($result->error['retryable'])->toBeFalse()
        ->and($result->error['message'])->toBe('Session is already closed.')
        ->and($reporter->reported)->toBe([]);
});

it('maps a thrown rateLimited to 429 rate_limited with retry_after and retryable', function () {
    $result = invokeFailing(CapabilityFailure::rateLimited('Too many wrong emails.', 60));

    expect($result->errorCode())->toBe('rate_limited')
        ->and($result->error['http_status'])->toBe(429)
        ->and($result->error['cli_exit'])->toBe(6)
        ->and($result->error['retryable'])->toBeTrue()
        ->and($result->error['retry_after'])->toBe(60)
        ->and($result->error['message'])->toBe('Too many wrong emails.');
});

it('returns HTTP 409 for conflict and 429 with Retry-After for rateLimited', function () {
    $conflict = HttpResponse::fromResult(invokeFailing(CapabilityFailure::conflict('Already closed.')));
    $limited = HttpResponse::fromResult(invokeFailing(CapabilityFailure::rateLimited('Slow down.', 60)));

    expect($conflict->status)->toBe(409)
        ->and($conflict->body['error']['code'])->toBe('conflict')
        ->and($conflict->headers)->not->toHaveKey('Retry-After')
        ->and($limited->status)->toBe(429)
        ->and($limited->body['error']['code'])->toBe('rate_limited')
        ->and($limited->headers['Retry-After'])->toBe('60');
});

it('gives every caller the same code and CLI exit', function (string $caller) {
    $conflict = invokeFailing(CapabilityFailure::conflict('Already closed.'), $caller);
    $limited = invokeFailing(CapabilityFailure::rateLimited('Slow down.', 60), $caller);

    expect($conflict->errorCode())->toBe('conflict')
        ->and($conflict->error['http_status'])->toBe(409)
        ->and($conflict->error['cli_exit'])->toBe(5)
        ->and($limited->errorCode())->toBe('rate_limited')
        ->and($limited->error['http_status'])->toBe(429)
        ->and($limited->error['cli_exit'])->toBe(6)
        ->and($limited->error['retry_after'])->toBe(60);
})->with(['http', 'mcp', 'cli', 'agent', 'job']);

it('keeps the code and exit in the product CLI envelope', function () {
    $envelope = CliJsonEnvelope::fromResult(invokeFailing(CapabilityFailure::rateLimited('Slow down.', 60), 'cli'));

    expect($envelope['error']['code'])->toBe('rate_limited')
        ->and($envelope['error']['cli_exit'])->toBe(6)
        ->and($envelope['error']['retry_after'])->toBe(60);
});

it('rolls back an open wrap_run transaction and audits the failure with the new code', function () {
    $capsule = new Manager;
    $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    $db = $capsule->getConnection();
    $db->statement('create table sessions (id integer)');

    $h = AuditHelpers::harness([
        'transactions' => ['wrap_run' => true],
        'transaction_connection' => $db,
        'run' => function () use ($db) {
            $db->insert('insert into sessions (id) values (1)');

            throw CapabilityFailure::conflict('Session is already closed.');
        },
    ]);

    $result = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options());

    $audit = $h['fakes']->audit->all();
    expect($result->errorCode())->toBe('conflict')
        ->and($h['registry']->lastRunWasWrapped())->toBeTrue()
        ->and($db->select('select * from sessions'))->toBe([])
        ->and($audit)->not->toBeEmpty()
        ->and(json_encode(end($audit)))->toContain('"conflict"')
        ->and($h['registry']->failedEvents()[0]->code)->toBe('conflict');
});

it('rolls back a strict-mode held wrap on a thrown failure', function () {
    $capsule = new Manager;
    $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    $db = $capsule->getConnection();
    $db->statement('create table sessions (id integer)');

    $h = AuditHelpers::harness([
        'mode' => 'strict',
        'transactions' => ['wrap_run' => true],
        'transaction_connection' => $db,
        'run' => function () use ($db) {
            $db->insert('insert into sessions (id) values (1)');

            throw CapabilityFailure::rateLimited('Slow down.', 600);
        },
    ]);

    $result = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options());

    expect($result->errorCode())->toBe('rate_limited')
        ->and($result->error['retry_after'])->toBe(600)
        ->and($db->select('select * from sessions'))->toBe([])
        ->and($db->transactionLevel())->toBe(0);
});

it('stores the new code for idempotent replay', function () {
    $runs = 0;
    $h = PipelineHelpers::harness(['run' => function () use (&$runs) {
        $runs++;

        throw CapabilityFailure::conflict('Already closed.');
    }]);
    $opts = PipelineHelpers::options('http', ['idempotency_key' => 'close-session-1']);

    $first = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), $opts);
    $second = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), $opts);

    expect($first->errorCode())->toBe('conflict')
        ->and($second->errorCode())->toBe('conflict')
        ->and($second->error['http_status'])->toBe(409)
        ->and($runs)->toBe(1);
});

it('carries constructor extra through the pipeline beside the code\'s wire fields', function () {
    $result = invokeFailing(new CapabilityFailure('gone', 'Link expired.', ['hint' => 'Ask for a new one.']));
    $response = HttpResponse::fromResult($result);

    expect($result->errorCode())->toBe('gone')
        ->and($result->error['hint'])->toBe('Ask for a new one.')
        ->and($result->error['http_status'])->toBe(410)
        ->and($result->error['cli_exit'])->toBe(5)
        ->and($response->status)->toBe(410)
        ->and($response->body['error']['hint'])->toBe('Ask for a new one.');
});

/**
 * @param  Closure(): mixed  $run
 * @return array{registry: mixed, name: string, opts: array<string, mixed>}
 */
function wrappedIdempotentHarness(Closure $run, string $key): array
{
    $capsule = new Manager;
    $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);

    $h = AuditHelpers::harness([
        'transactions' => ['wrap_run' => true],
        'transaction_connection' => $capsule->getConnection(),
        'run' => $run,
    ]);
    // The store and the guard must read one clock for a released key to expire.
    $h['registry']->withClock($h['fakes']->clock);

    return [
        'registry' => $h['registry'],
        'name' => $h['name'],
        'opts' => AuditHelpers::options('http', ['idempotency_key' => $key]),
    ];
}

it('replays a rate_limited thrown from an unwrapped run(): its writes may have committed', function () {
    $runs = 0;
    $h = PipelineHelpers::harness(['run' => function () use (&$runs) {
        $runs++;

        throw CapabilityFailure::rateLimited('Provider busy.', 60);
    }]);
    $h['registry']->withClock($h['fakes']->clock);
    $opts = PipelineHelpers::options('http', ['idempotency_key' => 'charge-1']);

    $first = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), $opts);
    $second = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), $opts);

    expect($first->errorCode())->toBe('rate_limited')
        ->and($second->errorCode())->toBe('rate_limited')
        ->and($runs)->toBe(1);
});

it('frees the key after a rate_limited under wrap_run rolled back: a retry runs again', function () {
    $runs = 0;
    $h = wrappedIdempotentHarness(function () use (&$runs) {
        $runs++;

        throw CapabilityFailure::rateLimited('Too many wrong emails.', 600);
    }, 'check-email-1');

    $first = $h['registry']->invoke($h['name'], AuditHelpers::input(), $h['opts']);
    $second = $h['registry']->invoke($h['name'], AuditHelpers::input(), $h['opts']);

    expect($first->errorCode())->toBe('rate_limited')
        ->and($second->errorCode())->toBe('rate_limited')
        ->and($second->error['retry_after'])->toBe(600)
        ->and($runs)->toBe(2);
});

it('lets a wrapped retry under the same key succeed once run() stops refusing', function () {
    $runs = 0;
    $h = wrappedIdempotentHarness(function () use (&$runs) {
        if (++$runs === 1) {
            throw CapabilityFailure::rateLimited('Slow down.', 60);
        }

        return new CreateInvoiceResult(invoice_id: 42);
    }, 'check-email-2');

    $h['registry']->invoke($h['name'], AuditHelpers::input(), $h['opts']);
    $retry = $h['registry']->invoke($h['name'], AuditHelpers::input(), $h['opts']);
    $replay = $h['registry']->invoke($h['name'], AuditHelpers::input(), $h['opts']);

    expect($retry->isOk())->toBeTrue()
        ->and($replay->isOk())->toBeTrue()
        ->and($runs)->toBe(2);
});

it('still maps a plain RuntimeException to domain_error', function () {
    $h = PipelineHelpers::harness(['run_throws' => 'Nope.']);

    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options());

    expect($result->errorCode())->toBe('domain_error')
        ->and($result->error['http_status'])->toBe(422);
});
