<?php

namespace Rawphp\Capabilities\Schema;

use Illuminate\Contracts\Validation\Factory;
use Throwable;

/**
 * Evaluates server-only Laravel rules (exists, unique, closures) through the
 * host validation factory. Portable rules stay on JSON Schema (D-004).
 *
 * A rule that cannot be evaluated is a violation. It never counts as a pass.
 */
final class IlluminateServerRuleChecker implements ServerRuleChecker
{
    private const UNEVALUABLE = 'Server-only rule could not be evaluated.';

    public function __construct(
        private readonly Factory $factory,
    ) {}

    public function check(array $rules, array $data): array
    {
        $extracted = $this->serverOnlyRules($rules);
        if ($extracted['unevaluable'] !== null) {
            return [[
                'field' => $extracted['unevaluable'],
                'message' => self::UNEVALUABLE,
            ]];
        }

        if ($extracted['rules'] === []) {
            return [];
        }

        try {
            $validator = $this->factory->make($data, $extracted['rules']);
            if ($validator->passes()) {
                return [];
            }
        } catch (Throwable) {
            return [[
                'field' => '(root)',
                'message' => self::UNEVALUABLE,
            ]];
        }

        $violations = [];
        foreach ($validator->errors()->messages() as $field => $messages) {
            foreach ($messages as $message) {
                $violations[] = [
                    'field' => (string) $field,
                    'message' => (string) $message,
                ];
            }
        }

        return $violations !== [] ? $violations : [[
            'field' => '(root)',
            'message' => self::UNEVALUABLE,
        ]];
    }

    /**
     * @param  array<string, mixed>  $rules
     * @return array{rules: array<string, list<mixed>>, unevaluable: ?string}
     */
    private function serverOnlyRules(array $rules): array
    {
        $classifier = new ServerRuleClassifier;
        $kept = [];

        foreach ($rules as $field => $fieldRules) {
            $list = is_array($fieldRules) ? $fieldRules : explode('|', (string) $fieldRules);
            $fieldRulesKept = [];
            foreach ($list as $rule) {
                if (is_string($rule)) {
                    $rule = trim($rule);
                    if ($rule === '' || $classifier->isPortable($rule)) {
                        continue;
                    }
                    $fieldRulesKept[] = $rule;

                    continue;
                }

                if (is_object($rule)) {
                    $fieldRulesKept[] = $rule;

                    continue;
                }

                return ['rules' => [], 'unevaluable' => (string) $field];
            }

            if ($fieldRulesKept !== []) {
                $kept[(string) $field] = $fieldRulesKept;
            }
        }

        return ['rules' => $kept, 'unevaluable' => null];
    }
}
