<?php

declare(strict_types=1);

use Rawphp\Capabilities\Schema\IlluminateServerRuleChecker;
use Rawphp\Capabilities\Tests\Fixtures\CountingPresenceVerifier;

it('returns no violations when there are no server-only rules [D-004]', function () {
    $checker = new IlluminateServerRuleChecker(CountingPresenceVerifier::factory());

    expect($checker->check([], ['customer_id' => 1]))->toBe([])
        ->and($checker->check([
            'customer_id' => ['required', 'integer'],
            'currency' => 'required|string|size:3',
        ], ['customer_id' => 1, 'currency' => 'USD']))->toBe([]);
});

it('fail: exists fails when the row is missing and passes when it is present [D-004]', function () {
    $verifier = new CountingPresenceVerifier(['customers' => 0]);
    $checker = new IlluminateServerRuleChecker(CountingPresenceVerifier::factory($verifier));

    $missing = $checker->check(
        ['customer_id' => ['required', 'integer', 'exists:customers,id']],
        ['customer_id' => 9],
    );

    expect($missing)->toBe([
        ['field' => 'customer_id', 'message' => 'The selected customer id is invalid.'],
    ])->and($verifier->calls[0]['collection'] ?? null)->toBe('customers')
        ->and($verifier->calls[0]['column'] ?? null)->toBe('id')
        ->and($verifier->calls[0]['value'] ?? null)->toBe(9);

    $verifier->counts['customers'] = 1;
    $verifier->calls = [];

    expect($checker->check(
        ['customer_id' => 'exists:customers,id'],
        ['customer_id' => 9],
    ))->toBe([])
        ->and($verifier->calls)->toHaveCount(1);
});

it('fail: unique fails when the value is already taken [D-004]', function () {
    $verifier = new CountingPresenceVerifier(['users' => 1]);
    $checker = new IlluminateServerRuleChecker(CountingPresenceVerifier::factory($verifier));

    expect($checker->check(
        ['email' => ['unique:users,email']],
        ['email' => 'a@example.com'],
    ))->toBe([
        ['field' => 'email', 'message' => 'The email has already been taken.'],
    ]);

    $verifier->counts['users'] = 0;

    expect($checker->check(
        ['email' => ['unique:users,email']],
        ['email' => 'a@example.com'],
    ))->toBe([]);
});

it('fail: a server-only rule that cannot be evaluated does not pass [D-004]', function () {
    $checker = new IlluminateServerRuleChecker(CountingPresenceVerifier::factory());

    expect($checker->check(
        ['customer_id' => ['exists:customers,id']],
        ['customer_id' => 1],
    ))->toBe([
        ['field' => '(root)', 'message' => 'Server-only rule could not be evaluated.'],
    ])->and($checker->check(
        ['customer_id' => [1]],
        ['customer_id' => 1],
    ))->toBe([
        ['field' => 'customer_id', 'message' => 'Server-only rule could not be evaluated.'],
    ]);
});

it('evaluates closure rules that JSON Schema cannot carry [D-004]', function () {
    $checker = new IlluminateServerRuleChecker(CountingPresenceVerifier::factory());

    $violations = $checker->check([
        'memo' => [function (string $attribute, mixed $value, Closure $fail): void {
            $fail('Memo is refused.');
        }],
    ], ['memo' => 'nope']);

    expect($violations)->toBe([
        ['field' => 'memo', 'message' => 'Memo is refused.'],
    ]);
});
