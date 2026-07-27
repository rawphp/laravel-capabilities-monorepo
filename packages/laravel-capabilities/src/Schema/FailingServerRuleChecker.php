<?php

namespace Rawphp\Capabilities\Schema;

/**
 * Test double: fails all server-only rules that match configured prefixes.
 */
final class FailingServerRuleChecker implements ServerRuleChecker
{
    /**
     * @param  list<string>  $failFields
     */
    public function __construct(
        private readonly array $failFields = [],
        private readonly string $message = 'server rule failed',
    ) {}

    public function check(array $rules, array $data): array
    {
        $violations = [];
        $classifier = new ServerRuleClassifier;

        foreach ($rules as $field => $fieldRules) {
            if ($this->failFields !== [] && ! in_array($field, $this->failFields, true)) {
                continue;
            }

            $list = is_array($fieldRules) ? $fieldRules : explode('|', (string) $fieldRules);
            foreach ($list as $rule) {
                if (! is_string($rule)) {
                    continue;
                }
                if ($classifier->isServerOnly($rule)) {
                    $violations[] = [
                        'field' => (string) $field,
                        'message' => $this->message.': '.$rule,
                    ];
                }
            }
        }

        return $violations;
    }
}
