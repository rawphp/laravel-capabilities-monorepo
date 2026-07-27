<?php

namespace Rawphp\Capabilities\Schema;

use Rawphp\Capabilities\Contracts\SchemaProvider;
use Rawphp\Capabilities\Registry\CapabilityDefinition;
use Rawphp\Capabilities\Support\CapabilityData;

/**
 * Server-side input validation: portable JSON Schema then optional server-only rules (D-004).
 *
 * Structural invalid input never reaches hydrate/run.
 */
final class InputValidator
{
    public function __construct(
        private readonly JsonSchemaValidator $jsonSchema = new JsonSchemaValidator,
        private readonly ?ServerRuleChecker $serverRules = null,
    ) {}

    public function serverRuleChecker(): ServerRuleChecker
    {
        return $this->serverRules ?? new PassThroughServerRuleChecker;
    }

    public function jsonSchemaValidator(): JsonSchemaValidator
    {
        return $this->jsonSchema;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return object|array  Hydrated DTO or raw array when no input class
     *
     * @throws SchemaValidationException
     */
    public function validate(CapabilityDefinition $definition, array $data, bool $serverRules = true): object|array
    {
        $inputClass = $definition->input;

        if ($inputClass === null) {
            return $data;
        }

        if (! is_a($inputClass, SchemaProvider::class, true)) {
            throw new SchemaValidationException(sprintf(
                'Input type %s must implement SchemaProvider.',
                $inputClass,
            ));
        }

        $schema = $inputClass::jsonSchema();
        $violations = $this->jsonSchema->validate($schema, $data);
        if ($violations !== []) {
            throw SchemaValidationException::withViolations($violations);
        }

        if ($serverRules && is_a($inputClass, CapabilityData::class, true)) {
            /** @var class-string<CapabilityData> $inputClass */
            $rules = $inputClass::rules();
            if ($rules !== []) {
                $checker = $this->serverRules ?? new PassThroughServerRuleChecker;
                $serverViolations = $checker->check($rules, $data);
                if ($serverViolations !== []) {
                    throw SchemaValidationException::withViolations($serverViolations);
                }
            }
        }

        return $inputClass::validate($data);
    }

    /**
     * Portable-only validation (CLI local path).
     *
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $data
     *
     * @throws SchemaValidationException
     */
    public function validatePortable(array $schema, array $data): void
    {
        $this->jsonSchema->assertValid($schema, $data);
    }
}
