<?php

// Audit input redaction (D-010). Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Attributes\Field;
use Rawphp\Capabilities\Audit\AuditLogger;
use Rawphp\Capabilities\Pipeline\InvokeState;
use Rawphp\Capabilities\Registry\CapabilityDefinition;
use Rawphp\Capabilities\Support\CapabilityContext;
use Rawphp\Capabilities\Support\CapabilityData;
use Rawphp\Capabilities\Tests\Fixtures\ScopeCallerJobHelpers as H;

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

it('fail: host-supplied MCP session secrets never reach the audit entry verbatim [D-010][D-023]', function () {
    $state = new InvokeState(new CapabilityDefinition(name: 'mcp-cap', description: 'd', readOnly: true), [], 'mcp');
    $state->context = CapabilityContext::make(['caller' => 'mcp', 'actor' => H::user(), 'mcp' => [
        'auth_profile' => 'user_delegated',
        'client_id' => 'claude-desktop',
        'session' => ['tenant_id' => 't-1', 'access_token' => 'at', 'oauth' => ['refresh_token' => 'rt', 'scope' => 'read']],
    ]]);

    expect(AuditLogger::entry($state, true)['mcp'])->toBe([
        'auth_profile' => 'user_delegated',
        'client_id' => 'claude-desktop',
        'session' => ['tenant_id' => 't-1', 'access_token' => '[REDACTED]', 'oauth' => ['refresh_token' => '[REDACTED]', 'scope' => 'read']],
    ]);
});

it('fail: sensitive messaging metadata never reaches the audit entry verbatim [D-010]', function () {
    $state = new InvokeState(new CapabilityDefinition(name: 'msg-cap', description: 'd', readOnly: true), [], 'agent');
    $state->context = CapabilityContext::make(['caller' => 'agent', 'actor' => H::user(), 'messaging' => [
        'channel' => 'telegram', 'chat_id' => '4242', 'bot_token' => 'b-1',
    ]]);

    expect(AuditLogger::entry($state, true)['messaging'])
        ->toBe(['channel' => 'telegram', 'chat_id' => '4242', 'bot_token' => '[REDACTED]']);
});

final class RedactCardStub extends CapabilityData
{
    public function __construct(
        #[Field(sensitive: true)]
        public string $number,
        public string $brand,
    ) {}
}

final class RedactApplicantStub extends CapabilityData
{
    /** @param  list<RedactCardStub>  $cards */
    public function __construct(
        #[Field(sensitive: true)]
        public string $ssn,
        public string $email,
        public ?RedactCardStub $primary = null,
        #[Field(items: RedactCardStub::class)]
        public array $cards = [],
    ) {}
}

function applicantInput(): array
{
    return [
        'ssn' => '123-45-6789',
        'email' => 'a@example.com',
        'primary' => ['number' => '4111', 'brand' => 'visa'],
        'cards' => [['number' => '5500', 'brand' => 'mc']],
    ];
}

function applicantRedacted(): array
{
    return [
        'ssn' => '[REDACTED]',
        'email' => 'a@example.com',
        'primary' => ['number' => '[REDACTED]', 'brand' => 'visa'],
        'cards' => [['number' => '[REDACTED]', 'brand' => 'mc']],
    ];
}

function applicantDefinition(): CapabilityDefinition
{
    return new CapabilityDefinition(name: 'apply', description: 'd', input: RedactApplicantStub::class);
}

it('fail: fields the input DTO marks sensitive never reach the audit entry verbatim [D-010]', function () {
    $state = new InvokeState(applicantDefinition(), applicantInput(), 'http');
    $state->input = RedactApplicantStub::fromArray(applicantInput());

    expect(AuditLogger::entry($state, true)['redacted_input'])->toBe(applicantRedacted());
});

it('edge: sensitive fields stay redacted when the input never hydrated [D-010]', function () {
    $raw = applicantInput() + ['unexpected' => 'x'];

    expect(AuditLogger::entry(new InvokeState(applicantDefinition(), $raw, 'http'), false)['redacted_input'])
        ->toBe(applicantRedacted() + ['unexpected' => 'x']);
});
