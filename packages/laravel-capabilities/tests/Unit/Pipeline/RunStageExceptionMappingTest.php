<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Tests\Fixtures\PipelineHelpers;

/**
 * L-004: bug-class errors in the run stage are reported and hidden;
 * deliberate domain throws keep their 422 domain_error message.
 */
function runStageReporter(): object
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

function invokeThrowing(Throwable $e): CapabilityResult
{
    $h = PipelineHelpers::harness(['run' => function () use ($e) {
        throw $e;
    }]);

    return $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http'));
}

afterEach(function () {
    Container::setInstance(null);
});

it('reports a TypeError and returns internal 500 with a generic message', function () {
    $reporter = runStageReporter();
    $e = new TypeError('Argument #1 ($id) must be of type int, string given');

    $result = invokeThrowing($e);

    expect($result->errorCode())->toBe('internal')
        ->and($result->error['http_status'])->toBe(500)
        ->and($result->error['message'])->toBe('Internal error.')
        ->and($reporter->reported)->toBe([$e]);
});

it('reports a QueryException and hides the SQL', function () {
    $reporter = runStageReporter();
    $e = new QueryException('mysql', 'select * from secrets where token = ?', ['abc'], new PDOException('SQLSTATE[42S22]'));

    $result = invokeThrowing($e);

    expect($result->errorCode())->toBe('internal')
        ->and($result->error['http_status'])->toBe(500)
        ->and($result->error['message'])->toBe('Internal error.')
        ->and($reporter->reported)->toBe([$e]);
});

it('maps ModelNotFoundException to not_found 404 without the model class', function () {
    $reporter = runStageReporter();
    $e = (new ModelNotFoundException)->setModel('App\\Models\\Issue', [7]);

    $result = invokeThrowing($e);

    expect($result->errorCode())->toBe('not_found')
        ->and($result->error['http_status'])->toBe(404)
        ->and($result->error['message'])->toBe('Not found.')
        ->and($reporter->reported)->toBe([]);
});

it('keeps a deliberate RuntimeException as 422 domain_error with its message, unreported', function () {
    $reporter = runStageReporter();

    $result = invokeThrowing(new RuntimeException('REQ not found for project.'));

    expect($result->errorCode())->toBe('domain_error')
        ->and($result->error['http_status'])->toBe(422)
        ->and($result->error['message'])->toBe('REQ not found for project.')
        ->and($reporter->reported)->toBe([]);
});

it('still returns internal 500 when no exception handler is bound', function () {
    Container::setInstance(new Container);

    $result = invokeThrowing(new Error('Call to undefined method'));

    expect($result->errorCode())->toBe('internal')
        ->and($result->error['message'])->toBe('Internal error.');
});

it('reports a raw PDOException as internal 500', function () {
    $reporter = runStageReporter();
    $e = new PDOException('SQLSTATE[HY000] [2002] Connection refused');

    $result = invokeThrowing($e);

    expect($result->errorCode())->toBe('internal')
        ->and($result->error['message'])->toBe('Internal error.')
        ->and($reporter->reported)->toBe([$e]);
});
