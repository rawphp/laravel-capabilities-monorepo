<?php

namespace Rawphp\Capabilities\Schema;

/**
 * Server-only Laravel rule evaluation (exists, unique, …). Injectable for unit tests.
 */
interface ServerRuleChecker
{
    /**
     * @param  array<string, mixed>  $rules
     * @param  array<string, mixed>  $data
     * @return list<array{field: string, message: string}>
     */
    public function check(array $rules, array $data): array;
}
