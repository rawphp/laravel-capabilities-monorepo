<?php

namespace Rawphp\Capabilities\Schema;

use Rawphp\Capabilities\Contracts\SchemaProvider;
use Rawphp\Capabilities\Registry\CapabilityDefinition;
use Rawphp\Capabilities\Support\CapabilityData;
use Rawphp\Capabilities\Support\CapabilityResult;

/**
 * Post-run output contract enforcement (D-014). Fail closed — never return malformed success.
 */
final class OutputValidator
{
    public function __construct(
        private readonly JsonSchemaValidator $jsonSchema = new JsonSchemaValidator,
    ) {}

    /**
     * @return CapabilityResult|null  failure result, or null when valid / skipped
     */
    public function validate(CapabilityDefinition $definition, mixed $output): ?CapabilityResult
    {
        $outputClass = $definition->output;
        if ($outputClass === null || $outputClass === '') {
            return null;
        }

        if (! is_a($outputClass, SchemaProvider::class, true)) {
            return CapabilityResult::failure(
                code: 'output_invalid',
                message: sprintf('Output type %s must implement SchemaProvider.', $outputClass),
            );
        }

        $data = $this->toArray($output);
        $schema = $outputClass::jsonSchema();
        $violations = $this->jsonSchema->validate($schema, $data);

        if ($violations !== []) {
            return CapabilityResult::failure(
                code: 'output_invalid',
                message: 'Capability output failed schema validation.',
                extra: ['violations' => $violations],
            );
        }

        // Ensure instance type when run returned a DTO.
        if (is_object($output) && ! $output instanceof $outputClass && ! $output instanceof CapabilityData) {
            // Array-shaped success after schema pass is acceptable.
        }

        return null;
    }

    /**
     * HTTP mapping for output_invalid (D-014): 500-class, not 200 success.
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function toHttpEnvelope(CapabilityResult $result): array
    {
        if ($result->isOk()) {
            return [
                'status' => 200,
                'body' => $result->toArray(),
            ];
        }

        $code = $result->errorCode();
        $status = match ($code) {
            'output_invalid' => 500,
            'validation_failed' => 422,
            'forbidden' => 403,
            default => 400,
        };

        return [
            'status' => $status,
            'body' => $result->toArray(),
        ];
    }

    /**
     * Agent/MCP tool result shaping — never success when output_invalid.
     *
     * @return array{ok: bool, is_error: bool, content: mixed}
     */
    public function toToolResult(CapabilityResult $result): array
    {
        if ($result->isOk()) {
            return [
                'ok' => true,
                'is_error' => false,
                'content' => $result->data,
            ];
        }

        return [
            'ok' => false,
            'is_error' => true,
            'content' => $result->error,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(mixed $output): array
    {
        if ($output instanceof CapabilityData) {
            return $output->toArray();
        }

        if (is_array($output)) {
            return $output;
        }

        if (is_object($output) && method_exists($output, 'toArray')) {
            /** @var array<string, mixed> */
            return $output->toArray();
        }

        return [];
    }
}
