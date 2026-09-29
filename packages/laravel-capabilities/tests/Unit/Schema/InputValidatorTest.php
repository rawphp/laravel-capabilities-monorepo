<?php

declare(strict_types=1);

// InputValidator behaviour.

use Rawphp\Capabilities\Registry\CapabilityDefinition;
use Rawphp\Capabilities\Schema\FailingServerRuleChecker;
use Rawphp\Capabilities\Schema\InputValidator;
use Rawphp\Capabilities\Schema\SchemaValidationException;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceInput;

it('InputValidator passes raw input through, hydrates DTOs, rejects bad providers and input, and honours server rules', function () {
    $v = new InputValidator;

    $noInput = new CapabilityDefinition(name: 'n', description: 'd', input: null, readOnly: true);
    expect($v->validate($noInput, ['raw' => true]))->toBe(['raw' => true]);

    $bad = new CapabilityDefinition(name: 'b', description: 'd', input: stdClass::class, readOnly: true);
    expect(fn () => $v->validate($bad, []))->toThrow(SchemaValidationException::class);

    $ok = new CapabilityDefinition(
        name: 'inv',
        description: 'd',
        input: CreateInvoiceInput::class,
        readOnly: false,
    );
    $dto = $v->validate($ok, [
        'customer_id' => 1,
        'amount_cents' => 100,
        'currency' => 'USD',
    ]);
    expect($dto)->toBeInstanceOf(CreateInvoiceInput::class);

    expect(fn () => $v->validate($ok, ['customer_id' => 'x']))->toThrow(SchemaValidationException::class);

    $failing = new InputValidator(serverRules: new FailingServerRuleChecker);
    // CreateInvoiceInput has rules(); FailingServerRuleChecker should reject when rules present
    expect(fn () => $failing->validate($ok, [
        'customer_id' => 1,
        'amount_cents' => 100,
        'currency' => 'USD',
    ]))->toThrow(SchemaValidationException::class);

    // skip server rules
    $dto2 = $failing->validate($ok, [
        'customer_id' => 1,
        'amount_cents' => 100,
        'currency' => 'USD',
    ], serverRules: false);
    expect($dto2)->toBeInstanceOf(CreateInvoiceInput::class);

    expect($v->serverRuleChecker())->not->toBeNull()
        ->and($v->jsonSchemaValidator())->not->toBeNull();

    $v->validatePortable(CreateInvoiceInput::jsonSchema(), [
        'customer_id' => 1,
        'amount_cents' => 50,
        'currency' => 'EUR',
    ]);
});
