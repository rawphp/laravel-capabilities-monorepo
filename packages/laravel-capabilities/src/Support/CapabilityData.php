<?php

namespace Rawphp\Capabilities\Support;

use InvalidArgumentException;
use Rawphp\Capabilities\Attributes\Field;
use Rawphp\Capabilities\Contracts\SchemaProvider;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionUnionType;

/**
 * Package-native DTO base for capability input/output (D-015).
 *
 * Wire arrays hydrate here; authorize/run receive typed instances.
 * Optional Spatie bridge is not required for v1.
 */
abstract class CapabilityData implements SchemaProvider
{
    /**
     * Hydrate a typed DTO from a wire array.
     *
     * Unknown keys are rejected by default (JSON Schema additionalProperties: false).
     * Missing required constructor parameters fail closed.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, bool $allowAdditionalProperties = false): static
    {
        $reflection = new ReflectionClass(static::class);
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            if ($data !== [] && ! $allowAdditionalProperties) {
                throw new InvalidArgumentException(sprintf(
                    '%s does not accept properties, got keys: %s',
                    static::class,
                    implode(', ', array_keys($data)),
                ));
            }

            /** @var static */
            return $reflection->newInstance();
        }

        $parameters = $constructor->getParameters();
        $known = [];
        foreach ($parameters as $parameter) {
            $known[$parameter->getName()] = true;
        }

        if (! $allowAdditionalProperties) {
            $unknown = array_diff(array_keys($data), array_keys($known));
            if ($unknown !== []) {
                throw new InvalidArgumentException(sprintf(
                    'Unknown %s propert%s: %s (additionalProperties=false)',
                    static::class,
                    count($unknown) === 1 ? 'y' : 'ies',
                    implode(', ', $unknown),
                ));
            }
        }

        $args = [];
        foreach ($parameters as $parameter) {
            $name = $parameter->getName();

            if (array_key_exists($name, $data)) {
                $args[] = self::coerceValue($data[$name], $parameter);

                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $args[] = $parameter->getDefaultValue();

                continue;
            }

            if ($parameter->allowsNull()) {
                $args[] = null;

                continue;
            }

            throw new InvalidArgumentException(sprintf(
                'Missing required property "%s" for %s',
                $name,
                static::class,
            ));
        }

        /** @var static */
        return $reflection->newInstanceArgs($args);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $out = [];
        $reflection = new ReflectionClass($this);

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $value = $property->getValue($this);
            $out[$property->getName()] = self::exportValue($value);
        }

        return $out;
    }

    /**
     * Portable JSON Schema (draft 2020-12) derived from constructor types + #[Field].
     * Server-only Laravel rules live in {@see rules()} and are never embedded here.
     *
     * @return array<string, mixed>
     */
    public static function jsonSchema(): array
    {
        $reflection = new ReflectionClass(static::class);
        $constructor = $reflection->getConstructor();
        $properties = [];
        $required = [];

        if ($constructor !== null) {
            foreach ($constructor->getParameters() as $parameter) {
                $name = $parameter->getName();
                $schema = self::parameterSchema($parameter);

                $field = self::fieldAttribute($parameter);
                if ($field !== null) {
                    $schema = self::applyFieldConstraints($schema, $field);
                }

                $properties[$name] = $schema;

                if (! $parameter->isDefaultValueAvailable() && ! $parameter->allowsNull()) {
                    $required[] = $name;
                }
            }
        }

        $schema = [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => $properties,
        ];

        if ($required !== []) {
            $schema['required'] = $required;
        }

        return $schema;
    }

    /**
     * Server-only Laravel validation rules (exists, unique, …). Not portable to CLI.
     *
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function validate(array $data): object
    {
        return static::fromArray($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function from(array $data): static
    {
        return static::fromArray($data);
    }

    private static function coerceValue(mixed $value, ReflectionParameter $parameter): mixed
    {
        $type = $parameter->getType();
        $field = self::fieldAttribute($parameter);

        if ($type === null) {
            return $value;
        }

        if ($value === null) {
            if ($parameter->allowsNull()) {
                return null;
            }

            throw new InvalidArgumentException(sprintf(
                'Property "%s" of %s does not allow null',
                $parameter->getName(),
                static::class,
            ));
        }

        if ($type instanceof ReflectionNamedType) {
            return self::coerceNamed($value, $type->getName(), $parameter->getName(), $field);
        }

        if ($type instanceof ReflectionUnionType) {
            foreach ($type->getTypes() as $inner) {
                if (! $inner instanceof ReflectionNamedType || $inner->getName() === 'null') {
                    continue;
                }

                try {
                    return self::coerceNamed($value, $inner->getName(), $parameter->getName(), $field);
                } catch (InvalidArgumentException) {
                    // try next union member
                }
            }

            throw new InvalidArgumentException(sprintf(
                'Property "%s" of %s has incompatible type',
                $parameter->getName(),
                static::class,
            ));
        }

        return $value;
    }

    private static function coerceNamed(mixed $value, string $typeName, string $property, ?Field $field = null): mixed
    {
        if (is_a($typeName, SchemaProvider::class, true) || is_a($typeName, self::class, true)) {
            if (! is_array($value)) {
                throw new InvalidArgumentException("Property \"{$property}\" must be object/array for nested type");
            }

            /** @var class-string<SchemaProvider> $typeName */
            return $typeName::validate($value);
        }

        return match ($typeName) {
            'int' => is_int($value) || (is_string($value) && is_numeric($value) && ! str_contains((string) $value, '.'))
                ? (int) $value
                : throw new InvalidArgumentException("Property \"{$property}\" must be int"),
            'float' => is_float($value) || is_int($value) || (is_string($value) && is_numeric($value))
                ? (float) $value
                : throw new InvalidArgumentException("Property \"{$property}\" must be float"),
            'string' => is_string($value) || is_numeric($value)
                ? (string) $value
                : throw new InvalidArgumentException("Property \"{$property}\" must be string"),
            'bool' => is_bool($value)
                ? $value
                : throw new InvalidArgumentException("Property \"{$property}\" must be bool"),
            'array' => self::coerceArray($value, $property, $field),
            default => $value,
        };
    }

    private static function coerceArray(mixed $value, string $property, ?Field $field): array
    {
        if (! is_array($value)) {
            throw new InvalidArgumentException("Property \"{$property}\" must be array");
        }

        if ($field?->items !== null && is_a($field->items, SchemaProvider::class, true)) {
            $items = [];
            foreach ($value as $i => $item) {
                if (! is_array($item)) {
                    throw new InvalidArgumentException("Property \"{$property}[{$i}]\" must be object");
                }
                /** @var class-string<SchemaProvider> $itemClass */
                $itemClass = $field->items;
                $items[] = $itemClass::validate($item);
            }

            return $items;
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    private static function parameterSchema(ReflectionParameter $parameter): array
    {
        $type = $parameter->getType();
        $field = self::fieldAttribute($parameter);

        if ($type instanceof ReflectionNamedType) {
            $schema = self::namedTypeSchema($type->getName(), $type->allowsNull(), $field);

            return $schema;
        }

        if ($type instanceof ReflectionUnionType) {
            $names = [];
            $allowsNull = false;
            $objectSchema = null;
            foreach ($type->getTypes() as $inner) {
                if (! $inner instanceof ReflectionNamedType) {
                    continue;
                }
                if ($inner->getName() === 'null') {
                    $allowsNull = true;

                    continue;
                }
                if (is_a($inner->getName(), SchemaProvider::class, true)) {
                    $objectSchema = $inner->getName()::jsonSchema();
                    unset($objectSchema['$schema']);
                    $names[] = 'object';
                } else {
                    $names[] = self::jsonTypeName($inner->getName());
                }
            }

            if ($allowsNull) {
                $names[] = 'null';
            }

            $names = array_values(array_unique($names));

            if ($objectSchema !== null && count($names) === 1) {
                return $objectSchema;
            }

            if ($objectSchema !== null && $allowsNull && count($names) === 2) {
                return array_merge($objectSchema, ['type' => ['object', 'null']]);
            }

            if (count($names) === 1) {
                return ['type' => $names[0]];
            }

            return ['type' => $names];
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private static function namedTypeSchema(string $typeName, bool $allowsNull, ?Field $field = null): array
    {
        if (is_a($typeName, SchemaProvider::class, true) || ($field?->of !== null && is_a($field->of, SchemaProvider::class, true))) {
            $class = is_a($typeName, SchemaProvider::class, true) ? $typeName : $field->of;
            /** @var class-string<SchemaProvider> $class */
            $nested = $class::jsonSchema();
            unset($nested['$schema']);
            if ($allowsNull) {
                $nested['type'] = ['object', 'null'];
            }

            return $nested;
        }

        $json = self::jsonTypeName($typeName);

        if ($json === 'array' && $field?->items !== null && is_a($field->items, SchemaProvider::class, true)) {
            $itemSchema = $field->items::jsonSchema();
            unset($itemSchema['$schema']);
            $schema = [
                'type' => $allowsNull ? ['array', 'null'] : 'array',
                'items' => $itemSchema,
            ];

            return $schema;
        }

        if ($allowsNull) {
            return ['type' => [$json, 'null']];
        }

        return ['type' => $json];
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private static function applyFieldConstraints(array $schema, Field $field): array
    {
        if ($field->description !== '') {
            $schema['description'] = $field->description;
        }
        if ($field->minimum !== null) {
            $schema['minimum'] = $field->minimum;
        }
        if ($field->maximum !== null) {
            $schema['maximum'] = $field->maximum;
        }
        if ($field->minLength !== null) {
            $schema['minLength'] = $field->minLength;
        }
        if ($field->maxLength !== null) {
            $schema['maxLength'] = $field->maxLength;
        }
        if ($field->minItems !== null) {
            $schema['minItems'] = $field->minItems;
        }
        if ($field->maxItems !== null) {
            $schema['maxItems'] = $field->maxItems;
        }
        if ($field->enum !== null) {
            $schema['enum'] = $field->enum;
        }
        if ($field->format !== null) {
            $schema['format'] = $field->format;
        }

        return $schema;
    }

    private static function jsonTypeName(string $phpType): string
    {
        return match ($phpType) {
            'int' => 'integer',
            'float' => 'number',
            'bool' => 'boolean',
            'string' => 'string',
            'array' => 'array',
            default => 'object',
        };
    }

    private static function fieldAttribute(ReflectionParameter $parameter): ?Field
    {
        $attributes = $parameter->getAttributes(Field::class);
        if ($attributes === []) {
            // Also check promoted property attributes.
            $class = $parameter->getDeclaringClass();
            if ($class !== null && $class->hasProperty($parameter->getName())) {
                $attributes = $class->getProperty($parameter->getName())->getAttributes(Field::class);
            }
        }

        if ($attributes === []) {
            return null;
        }

        return $attributes[0]->newInstance();
    }

    private static function exportValue(mixed $value): mixed
    {
        if ($value instanceof self) {
            return $value->toArray();
        }

        if (is_array($value)) {
            return array_map(static fn ($v) => self::exportValue($v), $value);
        }

        return $value;
    }
}
