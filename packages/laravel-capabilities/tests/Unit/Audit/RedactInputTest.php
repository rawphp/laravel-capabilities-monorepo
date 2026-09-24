<?php

// Audit input redaction (D-010). Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Audit\AuditLogger;
use Rawphp\Capabilities\Pipeline\InvokeState;
use Rawphp\Capabilities\Registry\CapabilityDefinition;
use Rawphp\Capabilities\Support\CapabilityData;

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

function resultSummaryFor(mixed $output): mixed
{
    $def = new CapabilityDefinition(name: 'redact', description: 'd', readOnly: true);
    $state = new InvokeState($def, [], 'http');
    $state->output = $output;

    return AuditLogger::entry($state, true)['result']['summary'];
}

it('fail: sensitive fields in a run() output never reach the audit result summary verbatim [D-010]', function () {
    $output = new class extends CapabilityData
    {
        public function __construct(
            public string $id = 'key-1',
            public string $apiToken = 'tok-live-123',
            public array $meta = ['client_secret' => 's', 'label' => 'ci'],
        ) {}
    };

    expect(resultSummaryFor($output))->toBe([
        'id' => 'key-1',
        'apiToken' => '[REDACTED]',
        'meta' => ['client_secret' => '[REDACTED]', 'label' => 'ci'],
    ]);
});

it('edge: array output is redacted and scalar output passes through [D-010]', function () {
    expect(resultSummaryFor(['password' => 'p', 'ok' => true]))->toBe(['password' => '[REDACTED]', 'ok' => true])
        ->and(resultSummaryFor('done'))->toBe('done');
});
