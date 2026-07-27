<?php

namespace Rawphp\Capabilities\Schema;

/**
 * Lightweight portable JSON Schema (draft 2020-12 subset) validator for wire payloads.
 *
 * Supports: type, required, properties, additionalProperties, enum, format (date),
 * minLength/maxLength, minimum/maximum, minItems/maxItems, items.
 */
final class JsonSchemaValidator
{
    /**
     * @param  array<string, mixed>  $schema
     * @param  mixed  $data
     * @return list<array{field: string, message: string}>
     */
    public function validate(array $schema, mixed $data, string $path = ''): array
    {
        $violations = [];

        if (isset($schema['type'])) {
            $typeViolations = $this->validateType($schema['type'], $data, $path);
            if ($typeViolations !== []) {
                return $typeViolations;
            }
        }

        if (array_key_exists('enum', $schema) && is_array($schema['enum'])) {
            if (! in_array($data, $schema['enum'], true)) {
                $violations[] = [
                    'field' => $path === '' ? '(root)' : $path,
                    'message' => 'value not in enum',
                ];
            }
        }

        if (isset($schema['format']) && is_string($data)) {
            if ($schema['format'] === 'date' && ! $this->isDate($data)) {
                $violations[] = [
                    'field' => $path === '' ? '(root)' : $path,
                    'message' => 'invalid date format',
                ];
            }
        }

        if (is_string($data)) {
            if (isset($schema['minLength']) && mb_strlen($data) < (int) $schema['minLength']) {
                $violations[] = [
                    'field' => $path === '' ? '(root)' : $path,
                    'message' => 'shorter than minLength',
                ];
            }
            if (isset($schema['maxLength']) && mb_strlen($data) > (int) $schema['maxLength']) {
                $violations[] = [
                    'field' => $path === '' ? '(root)' : $path,
                    'message' => 'longer than maxLength',
                ];
            }
        }

        if (is_int($data) || is_float($data)) {
            if (isset($schema['minimum']) && $data < $schema['minimum']) {
                $violations[] = [
                    'field' => $path === '' ? '(root)' : $path,
                    'message' => 'below minimum',
                ];
            }
            if (isset($schema['maximum']) && $data > $schema['maximum']) {
                $violations[] = [
                    'field' => $path === '' ? '(root)' : $path,
                    'message' => 'above maximum',
                ];
            }
        }

        if (is_array($data) && $this->isList($data)) {
            if (isset($schema['minItems']) && count($data) < (int) $schema['minItems']) {
                $violations[] = [
                    'field' => $path === '' ? '(root)' : $path,
                    'message' => 'fewer items than minItems',
                ];
            }
            if (isset($schema['maxItems']) && count($data) > (int) $schema['maxItems']) {
                $violations[] = [
                    'field' => $path === '' ? '(root)' : $path,
                    'message' => 'more items than maxItems',
                ];
            }
            if (isset($schema['items']) && is_array($schema['items'])) {
                foreach ($data as $i => $item) {
                    $childPath = $path === '' ? (string) $i : $path.'.'.$i;
                    $violations = array_merge($violations, $this->validate($schema['items'], $item, $childPath));
                }
            }
        }

        if (is_array($data) && ! $this->isList($data) && (($schema['type'] ?? null) === 'object' || isset($schema['properties']))) {
            $properties = $schema['properties'] ?? [];
            $required = $schema['required'] ?? [];
            $additional = $schema['additionalProperties'] ?? true;

            foreach ($required as $req) {
                if (! array_key_exists($req, $data)) {
                    $violations[] = [
                        'field' => $path === '' ? $req : $path.'.'.$req,
                        'message' => 'required property missing',
                    ];
                }
            }

            if ($additional === false) {
                $unknown = array_diff(array_keys($data), array_keys($properties));
                foreach ($unknown as $key) {
                    $violations[] = [
                        'field' => $path === '' ? (string) $key : $path.'.'.$key,
                        'message' => 'additional property not allowed',
                    ];
                }
            }

            foreach ($properties as $name => $propSchema) {
                if (! array_key_exists($name, $data)) {
                    continue;
                }
                $childPath = $path === '' ? (string) $name : $path.'.'.$name;
                if (is_array($propSchema)) {
                    $violations = array_merge($violations, $this->validate($propSchema, $data[$name], $childPath));
                }
            }
        }

        return $violations;
    }

    /**
     * @throws SchemaValidationException
     */
    public function assertValid(array $schema, mixed $data): void
    {
        $violations = $this->validate($schema, $data);
        if ($violations !== []) {
            throw SchemaValidationException::withViolations($violations);
        }
    }

    /**
     * @param  string|list<string>  $type
     * @return list<array{field: string, message: string}>
     */
    private function validateType(string|array $type, mixed $data, string $path): array
    {
        $types = is_array($type) ? $type : [$type];
        foreach ($types as $t) {
            if ($this->matchesType($t, $data)) {
                return [];
            }
        }

        return [[
            'field' => $path === '' ? '(root)' : $path,
            'message' => sprintf('expected type %s', implode('|', $types)),
        ]];
    }

    private function matchesType(string $type, mixed $data): bool
    {
        return match ($type) {
            'null' => $data === null,
            'integer' => is_int($data),
            'number' => is_int($data) || is_float($data),
            'string' => is_string($data),
            'boolean' => is_bool($data),
            'array' => is_array($data) && $this->isList($data),
            'object' => is_array($data) && ! $this->isList($data),
            default => true,
        };
    }

    private function isList(array $value): bool
    {
        if ($value === []) {
            return true;
        }

        return array_keys($value) === range(0, count($value) - 1);
    }

    private function isDate(string $value): bool
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }
        $parts = explode('-', $value);
        return checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0]);
    }
}
