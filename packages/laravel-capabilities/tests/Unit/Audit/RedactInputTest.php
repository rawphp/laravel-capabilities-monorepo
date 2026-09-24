<?php

// Audit input redaction (D-010). Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Audit\AuditLogger;
use Rawphp\Capabilities\Pipeline\InvokeState;
use Rawphp\Capabilities\Registry\CapabilityDefinition;

function redactedInputFor(array $input): mixed
{
    $def = new CapabilityDefinition(name: 'redact', description: 'd', readOnly: true);

    return AuditLogger::entry(new InvokeState($def, $input, 'http'), true)['redacted_input'];
}

it('fail: nested and mis-cased sensitive fields never reach the audit entry verbatim [D-010]', function () {
    $redacted = redactedInputFor([
        'email' => 'a@example.com',
        'Authorization' => 'Bearer abc',
        'user_password' => 'hunter2',
        'apiKey' => 'k-1',
        'payload' => [
            'credentials' => ['PASSWORD' => 'p', 'username' => 'bob'],
            'items' => [['name' => 'x', 'Access-Token' => 't']],
        ],
    ]);

    expect($redacted)->toBe([
        'email' => 'a@example.com',
        'Authorization' => '[REDACTED]',
        'user_password' => '[REDACTED]',
        'apiKey' => '[REDACTED]',
        'payload' => [
            'credentials' => ['PASSWORD' => '[REDACTED]', 'username' => 'bob'],
            'items' => [['name' => 'x', 'Access-Token' => '[REDACTED]']],
        ],
    ]);
});

it('edge: a sensitive key holding a structure is redacted whole [D-010]', function () {
    expect(redactedInputFor(['secrets' => ['a' => 1], 'ok' => true]))
        ->toBe(['secrets' => '[REDACTED]', 'ok' => true]);
});
