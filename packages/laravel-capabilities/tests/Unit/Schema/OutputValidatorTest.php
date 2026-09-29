<?php

declare(strict_types=1);

// OutputValidator behaviour and envelopes.

use Rawphp\Capabilities\Registry\CapabilityDefinition;
use Rawphp\Capabilities\Schema\OutputValidator;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceResult;

it('OutputValidator skips missing output, flags bad providers, and maps results to HTTP and tool envelopes', function () {
    $ov = new OutputValidator;

    $noOut = new CapabilityDefinition(name: 'n', description: 'd', output: null, readOnly: true);
    expect($ov->validate($noOut, ['x' => 1]))->toBeNull();

    $emptyOut = new CapabilityDefinition(name: 'e', description: 'd', output: '', readOnly: true);
    expect($ov->validate($emptyOut, ['x' => 1]))->toBeNull();

    $bad = new CapabilityDefinition(name: 'b', description: 'd', output: stdClass::class, readOnly: true);
    $fail = $ov->validate($bad, []);
    expect($fail)->not->toBeNull()->and($fail->errorCode())->toBe('output_invalid');

    $okDef = new CapabilityDefinition(
        name: 'o',
        description: 'd',
        output: CreateInvoiceResult::class,
        readOnly: true,
    );
    $okDto = CreateInvoiceResult::fromArray(['invoice_id' => 9]);
    expect($ov->validate($okDef, $okDto))->toBeNull();
    expect($ov->validate($okDef, ['invoice_id' => 9]))->toBeNull();

    $objectWithToArray = new class
    {
        public function toArray(): array
        {
            return ['invoice_id' => 1];
        }
    };
    expect($ov->validate($okDef, $objectWithToArray))->toBeNull();
    // non-array non-DTO falls back to []
    expect($ov->validate($okDef, 42))->not->toBeNull();

    $okResult = CapabilityResult::ok(['z' => 1]);
    $httpOk = $ov->toHttpEnvelope($okResult);
    expect($httpOk['status'])->toBe(200);

    $httpOut = $ov->toHttpEnvelope(CapabilityResult::failure(code: 'output_invalid', message: 'bad'));
    expect($httpOut['status'])->toBe(500);
    $httpVal = $ov->toHttpEnvelope(CapabilityResult::failure(code: 'validation_failed', message: 'v'));
    expect($httpVal['status'])->toBe(422);
    $httpForb = $ov->toHttpEnvelope(CapabilityResult::failure(code: 'forbidden', message: 'f'));
    expect($httpForb['status'])->toBe(403);
    $httpDef = $ov->toHttpEnvelope(CapabilityResult::failure(code: 'conflict', message: 'c'));
    expect($httpDef['status'])->toBe(400);

    $toolOk = $ov->toToolResult($okResult);
    expect($toolOk['ok'])->toBeTrue()->and($toolOk['is_error'])->toBeFalse();
    $toolFail = $ov->toToolResult(CapabilityResult::failure(code: 'output_invalid', message: 'x'));
    expect($toolFail['ok'])->toBeFalse()->and($toolFail['is_error'])->toBeTrue();
});
