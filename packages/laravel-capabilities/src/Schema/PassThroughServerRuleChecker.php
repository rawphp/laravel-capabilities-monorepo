<?php

namespace Rawphp\Capabilities\Schema;

/**
 * Default server rule checker that does not hit a database — always passes.
 * Unit tests inject failing checkers for server-only failure scenarios.
 */
final class PassThroughServerRuleChecker implements ServerRuleChecker
{
    public function check(array $rules, array $data): array
    {
        return [];
    }
}
