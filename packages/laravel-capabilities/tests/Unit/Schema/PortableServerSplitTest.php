<?php

declare(strict_types=1);

use Rawphp\Capabilities\Schema\JsonSchemaValidator;
use Rawphp\Capabilities\Schema\ServerRuleClassifier;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceInput;

$portableRules = ['required', 'integer', 'string', 'min', 'max', 'size', 'in'];

foreach ($portableRules as $rule) {
    it("edge: rule {$rule} may appear in portable schema when expressible [D-004]", function () use ($rule) {
        $classifier = new ServerRuleClassifier;
        expect($classifier->isPortable($rule))->toBeTrue()
            ->and($classifier->isServerOnly($rule))->toBeFalse();

        // Expressible structural counterparts live in JSON Schema.
        $schema = CreateInvoiceInput::jsonSchema();
        expect($schema['type'])->toBe('object');
        if ($rule === 'required') {
            expect($schema['required'] ?? [])->not->toBeEmpty();
        }
        if ($rule === 'integer') {
            expect($schema['properties']['customer_id']['type'])->toBe('integer');
        }
        if ($rule === 'string') {
            expect($schema['properties']['currency']['type'])->toBe('string');
        }
        if ($rule === 'min') {
            expect($schema['properties']['amount_cents']['minimum'] ?? null)->toBe(1);
        }
        if ($rule === 'size') {
            expect($schema['properties']['currency']['minLength'] ?? null)->toBe(3)
                ->and($schema['properties']['currency']['maxLength'] ?? null)->toBe(3);
        }
    });
}

foreach (['exists', 'unique', 'password'] as $serverRule) {
    it("happy: rule {$serverRule} is server-only not in portable schema [D-004]", function () use ($serverRule) {
        $classifier = new ServerRuleClassifier;
        expect($classifier->isServerOnly($serverRule))->toBeTrue()
            ->and($classifier->isPortable($serverRule))->toBeFalse();

        $schema = CreateInvoiceInput::jsonSchema();
        $encoded = json_encode($schema);
        expect($encoded)->not->toContain($serverRule.':')
            ->and($classifier->schemaContainsServerOnly($schema, $serverRule.':customers'))->toBeFalse();
    });

    it("fail: rule {$serverRule} not required for CLI local validation [D-004]", function () use ($serverRule) {
        // CLI validates portable schema only — payload can pass without DB existence.
        $v = new JsonSchemaValidator;
        $violations = $v->validate(CreateInvoiceInput::jsonSchema(), [
            'customer_id' => 999999,
            'amount_cents' => 100,
            'currency' => 'USD',
        ]);
        expect($violations)->toBeEmpty()
            ->and($serverRule)->toBeString();
    });
}
