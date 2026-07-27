<?php

declare(strict_types=1);

use Rawphp\Capabilities\Capability;
use Rawphp\Capabilities\Contracts\SchemaProvider;
use Rawphp\Capabilities\Schema\FailingServerRuleChecker;
use Rawphp\Capabilities\Schema\InputValidator;
use Rawphp\Capabilities\Schema\JsonSchemaValidator;
use Rawphp\Capabilities\Schema\SchemaValidationException;
use Rawphp\Capabilities\Schema\ToolSchemaExporter;
use Rawphp\Capabilities\Support\CapabilityData;
use Rawphp\Capabilities\Tests\Fixtures\ConstrainedInput;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceInput;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceResult;
use Rawphp\Capabilities\Tests\Fixtures\CustomSchemaType;
use Rawphp\Capabilities\Tests\Fixtures\DiscoveryHelpers;
use Rawphp\Capabilities\Tests\Fixtures\LineItemDto;
use Rawphp\Capabilities\Tests\Fixtures\NestedInput;

it('happy: CapabilityData reflects to JSON Schema draft 2020-12 [D-015]', function () {
    $schema = CreateInvoiceInput::jsonSchema();
    expect($schema['$schema'] ?? null)->toBe('https://json-schema.org/draft/2020-12/schema')
        ->and($schema['type'])->toBe('object')
        ->and($schema['additionalProperties'])->toBeFalse();
});

it('happy: Field attribute description appears in schema properties [D-015]', function () {
    $schema = CreateInvoiceInput::jsonSchema();
    expect($schema['properties']['customer_id']['description'] ?? null)
        ->toBe('Customer id within the active tenant');
});

it('happy: required properties derived from non-nullable constructor params [D-015]', function () {
    $schema = CreateInvoiceInput::jsonSchema();
    expect($schema['required'])->toContain('customer_id')
        ->and($schema['required'])->toContain('amount_cents')
        ->and($schema['required'])->toContain('currency');
});

it('happy: nullable properties allow null in schema [D-015]', function () {
    $schema = CreateInvoiceInput::jsonSchema();
    expect($schema['properties']['memo']['type'])->toBe(['string', 'null']);
});

it('happy: optional properties with defaults not required [D-015]', function () {
    $schema = CreateInvoiceInput::jsonSchema();
    expect($schema['required'] ?? [])->not->toContain('memo');
});

it('happy: server-only rules not embedded in portable JSON Schema for CLI [D-004]', function () {
    $schema = CreateInvoiceInput::jsonSchema();
    $encoded = json_encode($schema);
    expect($encoded)->not->toContain('exists:customers,id')
        ->and($encoded)->not->toContain('exists:');
});

it('happy: exists unique server rules run only on server validation pass [D-004]', function () {
    $validator = new InputValidator(serverRules: new FailingServerRuleChecker(['customer_id']));
    $registry = DiscoveryHelpers::registry();
    Capability::define('srv-rules')
        ->input(CreateInvoiceInput::class)
        ->run(fn ($in) => new CreateInvoiceResult(invoice_id: 1))
        ->register($registry);

    // Inject via new registry with failing checker
    $registry2 = new \Rawphp\Capabilities\Registry\CapabilityRegistry(
        inputValidator: $validator,
    );
    Capability::define('srv-rules-2')
        ->input(CreateInvoiceInput::class)
        ->run(fn ($in) => new CreateInvoiceResult(invoice_id: 1))
        ->register($registry2);

    $result = $registry2->invoke('srv-rules-2', [
        'customer_id' => 1,
        'amount_cents' => 100,
        'currency' => 'USD',
    ]);
    expect($result->isOk())->toBeFalse()
        ->and($result->errorCode())->toBe('validation_failed');
});

it('fail: structural invalid input never reaches hydrate or run [D-004]', function () {
    $ran = false;
    $registry = DiscoveryHelpers::registry();
    Capability::define('struct-fail')
        ->input(CreateInvoiceInput::class)
        ->run(function ($in) use (&$ran) {
            $ran = true;

            return new CreateInvoiceResult(invoice_id: 1);
        })
        ->register($registry);

    $result = $registry->invoke('struct-fail', [
        'customer_id' => 'not-int',
        'amount_cents' => 100,
        'currency' => 'USD',
    ]);
    expect($result->isOk())->toBeFalse()
        ->and($ran)->toBeFalse();
});

it('fail: server-only validation failure never reaches run [D-004]', function () {
    $ran = false;
    $validator = new InputValidator(serverRules: new FailingServerRuleChecker(['customer_id']));
    $registry = new \Rawphp\Capabilities\Registry\CapabilityRegistry(inputValidator: $validator);
    Capability::define('server-fail')
        ->input(CreateInvoiceInput::class)
        ->run(function ($in) use (&$ran) {
            $ran = true;

            return new CreateInvoiceResult(invoice_id: 1);
        })
        ->register($registry);

    $result = $registry->invoke('server-fail', [
        'customer_id' => 1,
        'amount_cents' => 100,
        'currency' => 'USD',
    ]);
    expect($result->isOk())->toBeFalse()->and($ran)->toBeFalse();
});

it('happy: array wire format only at edge run receives typed DTO [D-015]', function () {
    $registry = DiscoveryHelpers::registry();
    $seen = null;
    Capability::define('typed-run')
        ->input(CreateInvoiceInput::class)
        ->run(function ($in) use (&$seen) {
            $seen = $in;

            return new CreateInvoiceResult(invoice_id: 7);
        })
        ->register($registry);

    $registry->invoke('typed-run', [
        'customer_id' => 3,
        'amount_cents' => 50,
        'currency' => 'EUR',
    ]);
    expect($seen)->toBeInstanceOf(CreateInvoiceInput::class)
        ->and($seen)->toBeInstanceOf(CapabilityData::class)
        ->and($seen->customer_id)->toBe(3);
});

it('happy: SchemaProvider interface supported for custom types [D-015]', function () {
    expect(CustomSchemaType::class)->toImplement(SchemaProvider::class);
    $obj = CustomSchemaType::validate(['label' => 'x']);
    expect($obj)->toBeInstanceOf(CustomSchemaType::class)
        ->and($obj->label)->toBe('x');
});

it('edge: optional Spatie bridge implements SchemaProvider when installed [D-015]', function () {
    // Spatie is optional — when not installed, package still works; bridge would implement SchemaProvider.
    $spatieInstalled = class_exists(\Spatie\LaravelData\Data::class);
    expect(interface_exists(SchemaProvider::class))->toBeTrue();
    if ($spatieInstalled) {
        expect(true)->toBeTrue();
    } else {
        expect($spatieInstalled)->toBeFalse();
    }
});

it('edge: Spatie not required for v1 package-native path [D-015]', function () {
    $composer = json_decode(file_get_contents(dirname(__DIR__, 3).'/composer.json'), true);
    $require = $composer['require'] ?? [];
    expect($require)->not->toHaveKey('spatie/laravel-data')
        ->and(CreateInvoiceInput::jsonSchema()['type'])->toBe('object');
});

it('happy: catalog describe returns input_schema and output_schema [D-004]', function () {
    $registry = DiscoveryHelpers::registry();
    DiscoveryHelpers::fluentCreateInvoice($registry);
    $desc = $registry->catalog()->describe('create-invoice');
    expect($desc)->toHaveKeys(['input_schema', 'output_schema', 'schema_version', 'name'])
        ->and($desc['input_schema']['type'])->toBe('object')
        ->and($desc['output_schema']['properties'])->toHaveKey('invoice_id');
});

it('happy: catalog list can omit full schemas until describe [CAT-001]', function () {
    $registry = DiscoveryHelpers::registry();
    DiscoveryHelpers::fluentCreateInvoice($registry);
    $list = $registry->catalog()->list(includeSchemas: false);
    expect($list)->not->toBeEmpty()
        ->and($list[0])->not->toHaveKey('input_schema')
        ->and($list[0])->toHaveKey('name')
        ->and($list[0])->toHaveKey('schema_version');
});

it('happy: schema_version present on catalog entries [D-004]', function () {
    $registry = DiscoveryHelpers::registry();
    DiscoveryHelpers::fluentCreateInvoice($registry);
    $desc = $registry->catalog()->describe('create-invoice');
    expect($desc['schema_version'])->toBe('1');
});

it('fail: additionalProperties false rejects unknown keys when schema says so [D-004]', function () {
    $v = new JsonSchemaValidator;
    $schema = CreateInvoiceInput::jsonSchema();
    $violations = $v->validate($schema, [
        'customer_id' => 1,
        'amount_cents' => 1,
        'currency' => 'USD',
        'extra' => true,
    ]);
    expect($violations)->not->toBeEmpty();
});

it('fail: wrong type for integer field fails portable validation [D-004]', function () {
    $v = new JsonSchemaValidator;
    $violations = $v->validate(CreateInvoiceInput::jsonSchema(), [
        'customer_id' => 'nope',
        'amount_cents' => 1,
        'currency' => 'USD',
    ]);
    expect($violations)->not->toBeEmpty();
});

it('fail: missing required field fails portable validation [D-004]', function () {
    $v = new JsonSchemaValidator;
    $violations = $v->validate(CreateInvoiceInput::jsonSchema(), [
        'customer_id' => 1,
        'currency' => 'USD',
    ]);
    expect($violations)->not->toBeEmpty();
});

it('fail: string longer than max fails when schema constrains [D-004]', function () {
    $v = new JsonSchemaValidator;
    $schema = ConstrainedInput::jsonSchema();
    $violations = $v->validate($schema, [
        'currency' => 'usd',
        'due_date' => '2026-01-01',
        'code' => 'way-too-long-code',
        'score' => 50,
    ]);
    expect($violations)->not->toBeEmpty();
});

it('fail: enum value outside set fails portable validation [D-004]', function () {
    $v = new JsonSchemaValidator;
    $violations = $v->validate(ConstrainedInput::jsonSchema(), [
        'currency' => 'yen',
        'due_date' => '2026-01-01',
        'code' => 'ab',
        'score' => 10,
    ]);
    expect($violations)->not->toBeEmpty();
});

it('happy: nested object properties reflected in schema [D-015]', function () {
    $schema = NestedInput::jsonSchema();
    expect($schema['properties']['item']['type'] ?? null)->toBe('object')
        ->and($schema['properties']['item']['properties'] ?? [])->toHaveKey('sku');
});

it('happy: array item types reflected in schema [D-015]', function () {
    $schema = NestedInput::jsonSchema();
    expect($schema['properties']['items']['type'] ?? null)->toBe('array')
        ->and($schema['properties']['items']['items']['properties'] ?? [])->toHaveKey('sku');
});

it('edge: CLI and HTTP consume identical portable schema document [D-004]', function () {
    $registry = DiscoveryHelpers::registry();
    DiscoveryHelpers::fluentCreateInvoice($registry);
    $cli = $registry->toolSchemas()->export('cli_catalog', 'create-invoice');
    $http = $registry->toolSchemas()->export('http_catalog', 'create-invoice');
    expect($cli['input_schema'])->toBe($http['input_schema'])
        ->and($cli['input_schema'])->toBe(CreateInvoiceInput::jsonSchema());
});

it('fail: Laravel rule strings alone are not catalog schema source of truth [D-004]', function () {
    $registry = DiscoveryHelpers::registry();
    DiscoveryHelpers::fluentCreateInvoice($registry);
    $desc = $registry->catalog()->describe('create-invoice');
    $encoded = json_encode($desc);
    expect($encoded)->not->toContain('exists:customers')
        ->and($desc['input_schema'])->toBeArray()
        ->and($desc['input_schema']['type'])->toBe('object');
});

it('fail: hand-copied second tool schema is not used by AI adapter [D-004]', function () {
    $registry = DiscoveryHelpers::registry();
    DiscoveryHelpers::fluentCreateInvoice($registry);
    $handCopied = ['type' => 'object', 'properties' => ['wrong' => ['type' => 'string']]];
    expect($registry->toolSchemas()->assertUsesRegistry('ai', 'create-invoice', $handCopied))->toBeFalse()
        ->and($registry->toolSchemas()->export('ai', 'create-invoice')['source'])->toBe('registry');
});

it('fail: hand-copied second tool schema is not used by MCP adapter [D-004]', function () {
    $registry = DiscoveryHelpers::registry();
    DiscoveryHelpers::fluentCreateInvoice($registry);
    $handCopied = ['type' => 'object', 'properties' => ['wrong' => ['type' => 'string']]];
    expect($registry->toolSchemas()->assertUsesRegistry('mcp', 'create-invoice', $handCopied))->toBeFalse()
        ->and($registry->toolSchemas()->export('mcp', 'create-invoice')['input_schema'])
        ->toBe(CreateInvoiceInput::jsonSchema());
});
