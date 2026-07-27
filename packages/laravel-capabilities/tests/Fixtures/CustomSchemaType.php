<?php

declare(strict_types=1);

namespace Rawphp\Capabilities\Tests\Fixtures;

use InvalidArgumentException;
use Rawphp\Capabilities\Contracts\SchemaProvider;

final class CustomSchemaType implements SchemaProvider
{
    public function __construct(public string $label) {}

    public static function jsonSchema(): array
    {
        return [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['label'],
            'properties' => [
                'label' => ['type' => 'string'],
            ],
        ];
    }

    public static function validate(array $data): object
    {
        if (! isset($data['label']) || ! is_string($data['label'])) {
            throw new InvalidArgumentException('label required string');
        }

        return new self($data['label']);
    }
}
