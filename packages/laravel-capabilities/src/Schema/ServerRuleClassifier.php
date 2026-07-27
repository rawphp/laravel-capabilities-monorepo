<?php

namespace Rawphp\Capabilities\Schema;

/**
 * Splits Laravel-style rules into portable (expressible in JSON Schema) vs server-only (D-004).
 */
final class ServerRuleClassifier
{
    /** @var list<string> */
    public const SERVER_ONLY_PREFIXES = [
        'exists',
        'unique',
        'password',
        'current_password',
        'confirmed',
        'dimensions',
        'file',
        'image',
        'mimes',
        'mimetypes',
        'exclude',
        'exclude_if',
        'exclude_unless',
        'exclude_with',
        'exclude_without',
        'prohibited',
        'prohibited_if',
        'prohibited_unless',
        'prohibits',
        'required_if',
        'required_unless',
        'required_with',
        'required_without',
        'same',
        'different',
        'accepted',
        'declined',
        'present',
        'bail',
        'sometimes',
    ];

    /** @var list<string> */
    public const PORTABLE_RULES = [
        'required',
        'nullable',
        'integer',
        'numeric',
        'string',
        'boolean',
        'array',
        'min',
        'max',
        'size',
        'in',
        'not_in',
        'email',
        'url',
        'uuid',
        'date',
        'date_format',
        'json',
        'regex',
        'between',
        'digits',
        'digits_between',
        'alpha',
        'alpha_num',
        'alpha_dash',
    ];

    public function isServerOnly(string $rule): bool
    {
        $name = $this->ruleName($rule);
        foreach (self::SERVER_ONLY_PREFIXES as $prefix) {
            if ($name === $prefix) {
                return true;
            }
        }

        return false;
    }

    public function isPortable(string $rule): bool
    {
        if ($this->isServerOnly($rule)) {
            return false;
        }

        $name = $this->ruleName($rule);

        return in_array($name, self::PORTABLE_RULES, true);
    }

    /**
     * @param  array<string, mixed>  $rules  field => rule string|list
     * @return array{portable: array<string, list<string>>, server_only: array<string, list<string>>}
     */
    public function classify(array $rules): array
    {
        $portable = [];
        $serverOnly = [];

        foreach ($rules as $field => $fieldRules) {
            $list = $this->normalize($fieldRules);
            foreach ($list as $rule) {
                if ($this->isServerOnly($rule)) {
                    $serverOnly[$field][] = $rule;
                } elseif ($this->isPortable($rule)) {
                    $portable[$field][] = $rule;
                } else {
                    // Unknown rules treated as server-only (fail closed for CLI portability).
                    $serverOnly[$field][] = $rule;
                }
            }
        }

        return ['portable' => $portable, 'server_only' => $serverOnly];
    }

    /**
     * Whether a server-only rule name appears in portable JSON Schema document.
     *
     * @param  array<string, mixed>  $schema
     */
    public function schemaContainsServerOnly(array $schema, string $ruleName = 'exists'): bool
    {
        $encoded = json_encode($schema) ?: '';

        return str_contains($encoded, $ruleName.':')
            || str_contains($encoded, '"'.$ruleName.'"')
            || str_contains($encoded, $ruleName);
    }

    private function ruleName(string $rule): string
    {
        $parts = explode(':', $rule, 2);

        return strtolower($parts[0]);
    }

    /**
     * @return list<string>
     */
    private function normalize(mixed $fieldRules): array
    {
        if (is_string($fieldRules)) {
            return array_values(array_filter(array_map('trim', explode('|', $fieldRules))));
        }

        if (is_array($fieldRules)) {
            $out = [];
            foreach ($fieldRules as $rule) {
                if (is_string($rule)) {
                    $out[] = $rule;
                }
            }

            return $out;
        }

        return [];
    }
}
