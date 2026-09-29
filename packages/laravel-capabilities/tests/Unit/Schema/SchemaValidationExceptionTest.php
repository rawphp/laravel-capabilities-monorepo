<?php

declare(strict_types=1);

// SchemaValidationException construction.

use Rawphp\Capabilities\Schema\SchemaValidationException;

it('SchemaValidationException::withViolations joins field messages, defaults the error code, and the constructor keeps its arguments', function () {
    $empty = SchemaValidationException::withViolations([]);
    expect($empty->getMessage())->toBe('Validation failed')
        ->and($empty->violations)->toBe([])
        ->and($empty->errorCode)->toBe('validation_failed');

    $with = SchemaValidationException::withViolations([
        ['field' => 'amount', 'message' => 'too small'],
        ['field' => 'currency', 'message' => 'invalid'],
    ], 'schema_invalid');
    expect($with->getMessage())->toContain('amount: too small')
        ->and($with->getMessage())->toContain('currency: invalid')
        ->and($with->errorCode)->toBe('schema_invalid');

    $direct = new SchemaValidationException('boom', [['field' => 'x', 'message' => 'y']], 'custom');
    expect($direct->getMessage())->toBe('boom')->and($direct->violations)->toHaveCount(1);
});
