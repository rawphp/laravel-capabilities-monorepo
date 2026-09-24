<?php

// ONE-RUN: run arity is decided from the signature, never by retrying after an ArgumentCountError.

declare(strict_types=1);

use Rawphp\Capabilities\Registry\CapabilityDefinition;
use Rawphp\Capabilities\Support\CapabilityContext;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceInput;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceResult;
use Rawphp\Capabilities\Tests\Fixtures\PipelineHelpers;

final class RunArityThrowingHandler
{
    public static int $calls = 0;

    public function run(CreateInvoiceInput $input, ?CapabilityContext $context = null): CreateInvoiceResult
    {
        self::$calls++;
        throw new ArgumentCountError('nested call missing an argument');

        return new CreateInvoiceResult(invoice_id: 1);
    }
}

final class RunArityInputOnlyHandler
{
    public function run(CreateInvoiceInput $input): CreateInvoiceResult
    {
        return new CreateInvoiceResult(invoice_id: 7);
    }
}

final class RunArityContextHandler
{
    public static mixed $context = null;

    public function run(CreateInvoiceInput $input, CapabilityContext $context): CreateInvoiceResult
    {
        self::$context = $context;

        return new CreateInvoiceResult(invoice_id: 8);
    }
}

function runArityRegisterHandler(string $class): array
{
    $h = PipelineHelpers::harness();
    $h['registry']->register(new CapabilityDefinition(
        name: 'arity-handler',
        surfaces: ['http'],
        input: CreateInvoiceInput::class,
        output: CreateInvoiceResult::class,
        handlerClass: $class,
    ));

    return $h;
}

it('fail: ArgumentCountError thrown inside a run closure does not re-run it [ONE-RUN]', function () {
    $calls = 0;
    $h = PipelineHelpers::harness(['run' => function ($input, $context) use (&$calls) {
        $calls++;
        throw new ArgumentCountError('nested call missing an argument');
    }]);

    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options());

    expect($result->isOk())->toBeFalse()
        ->and($result->error['code'])->toBe('domain_error')
        ->and($calls)->toBe(1);
});

it('fail: ArgumentCountError thrown inside an input-only run closure does not re-run it [ONE-RUN]', function () {
    $calls = 0;
    $h = PipelineHelpers::harness(['run' => function ($input) use (&$calls) {
        $calls++;
        throw new ArgumentCountError('nested call missing an argument');
    }]);

    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options());

    expect($result->error['code'])->toBe('domain_error')->and($calls)->toBe(1);
});

it('fail: ArgumentCountError thrown inside a handler run() does not re-run it [ONE-RUN]', function () {
    RunArityThrowingHandler::$calls = 0;
    $h = runArityRegisterHandler(RunArityThrowingHandler::class);

    $result = $h['registry']->invoke('arity-handler', PipelineHelpers::validInput(), PipelineHelpers::options());

    expect($result->error['code'])->toBe('domain_error')
        ->and(RunArityThrowingHandler::$calls)->toBe(1);
});

it('happy: run closure declaring (input, context) receives the context [D-003]', function () {
    $seen = null;
    $h = PipelineHelpers::harness(['run' => function ($input, $context) use (&$seen) {
        $seen = $context;

        return new CreateInvoiceResult(invoice_id: 5);
    }]);

    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options());

    expect($result->isOk())->toBeTrue()->and($seen)->toBeInstanceOf(CapabilityContext::class);
});

it('happy: variadic run closure receives input and context [D-003]', function () {
    $args = [];
    $h = PipelineHelpers::harness(['run' => function (...$a) use (&$args) {
        $args = $a;

        return new CreateInvoiceResult(invoice_id: 6);
    }]);

    $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options());

    expect($args)->toHaveCount(2)->and($args[1])->toBeInstanceOf(CapabilityContext::class);
});

it('happy: strict single-argument internal callable is called with input only [ONE-RUN]', function () {
    $h = PipelineHelpers::harness([
        'run' => 'get_object_vars',
        'validation' => ['validate_output' => false],
    ]);

    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options());

    expect($result->isOk())->toBeTrue()->and($result->data['customer_id'])->toBe(1);
});

it('happy: input-only handler run() is called with input only [ONE-RUN]', function () {
    $h = runArityRegisterHandler(RunArityInputOnlyHandler::class);

    $result = $h['registry']->invoke('arity-handler', PipelineHelpers::validInput(), PipelineHelpers::options());

    expect($result->isOk())->toBeTrue()->and($result->data->invoice_id)->toBe(7);
});

it('happy: handler run() declaring context receives it [D-003]', function () {
    RunArityContextHandler::$context = null;
    $h = runArityRegisterHandler(RunArityContextHandler::class);

    $result = $h['registry']->invoke('arity-handler', PipelineHelpers::validInput(), PipelineHelpers::options());

    expect($result->isOk())->toBeTrue()
        ->and(RunArityContextHandler::$context)->toBeInstanceOf(CapabilityContext::class);
});
