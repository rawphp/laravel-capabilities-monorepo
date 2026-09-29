<?php

declare(strict_types=1);

// ServerRuleClassifier portable vs server-only rules.

use Rawphp\Capabilities\Schema\ServerRuleClassifier;

it('ServerRuleClassifier splits rules into portable and server-only and detects server-only schema content', function () {
    $c = new ServerRuleClassifier;
    $split = $c->classify([
        'a' => 'required|integer|exists:users,id',
        'b' => ['nullable', 'string', 'custom_rule'],
        'c' => 'min:1|max:10',
        'd' => 123, // ignored non-string rules
        'e' => ['required', 99, 'email'],
    ]);
    expect($split['portable']['a'] ?? [])->toContain('required')
        ->and($split['portable']['a'] ?? [])->toContain('integer')
        ->and($split['server_only']['a'] ?? [])->toContain('exists:users,id')
        ->and($split['server_only']['b'] ?? [])->toContain('custom_rule')
        ->and($split['portable']['c'] ?? [])->toContain('min:1')
        ->and($split['portable']['e'] ?? [])->toContain('email');

    expect($c->isPortable('required'))->toBeTrue()
        ->and($c->isPortable('exists:x,y'))->toBeFalse()
        ->and($c->isServerOnly('unique:users,email'))->toBeTrue()
        ->and($c->schemaContainsServerOnly(['properties' => ['id' => ['type' => 'integer']]], 'exists'))->toBeFalse()
        ->and($c->schemaContainsServerOnly(['x' => 'exists:users,id'], 'exists'))->toBeTrue();
});
