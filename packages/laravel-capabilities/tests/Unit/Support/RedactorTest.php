<?php

// Shared sensitive-key redaction (D-010). Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Support\Redactor;

it('happy: non-sensitive keys pass through unchanged', function () {
    expect(Redactor::redact(['email' => 'a@example.com', 'items' => [1, 2]]))
        ->toBe(['email' => 'a@example.com', 'items' => [1, 2]]);
});

it('fail: nested and mis-cased sensitive keys are replaced with the placeholder [D-010]', function () {
    expect(Redactor::redact([
        'Client-Secret' => 's',
        'nested' => ['api_key' => 'k', 'list' => [['TOKEN' => 't', 'id' => 7]]],
    ]))->toBe([
        'Client-Secret' => Redactor::PLACEHOLDER,
        'nested' => ['api_key' => Redactor::PLACEHOLDER, 'list' => [['TOKEN' => Redactor::PLACEHOLDER, 'id' => 7]]],
    ]);
});
