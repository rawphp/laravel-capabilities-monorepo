<?php

namespace Rawphp\Capabilities\Support;

/**
 * Pure in-memory capability definition builder for unit tests.
 *
 * Does not register with the registry or touch the container — only returns a
 * definition array later pipeline/registry tests can inject.
 */
final class CapabilityFixture
{
    /**
     * @param  list<string>  $surfaces
     * @param  array<string, mixed>  $inputSchema
     * @param  array<string, mixed>  $outputSchema
     * @return array{
     *     name: string,
     *     description: string,
     *     mutating: bool,
     *     surfaces: list<string>,
     *     input_schema: array<string, mixed>,
     *     output_schema: array<string, mixed>
     * }
     */
    public static function definition(
        string $name = 'test.capability',
        string $description = 'Test capability fixture',
        bool $mutating = true,
        array $surfaces = ['agent', 'mcp', 'http', 'cli', 'job'],
        array $inputSchema = ['type' => 'object', 'properties' => []],
        array $outputSchema = ['type' => 'object', 'properties' => []],
    ): array {
        return [
            'name' => $name,
            'description' => $description,
            'mutating' => $mutating,
            'surfaces' => array_values($surfaces),
            'input_schema' => $inputSchema,
            'output_schema' => $outputSchema,
        ];
    }
}
