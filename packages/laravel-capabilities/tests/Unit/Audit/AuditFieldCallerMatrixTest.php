<?php

// Spec-derived unit tests for D-010 audit field x caller matrix. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Tests\Fixtures\AuditHelpers;

it("happy: audit field name set for caller agent on success [D-010]", function () {
    $h = AuditHelpers::harness(['name' => 'audit-field-agent-name']);
    $r = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options('agent'));
    expect($r->isOk())->toBeTrue();
    $entry = $h['audit']->all()[0] ?? [];
    expect($entry)->toHaveKey('name');
});

it("happy: audit field caller set for caller agent on success [D-010]", function () {
    $h = AuditHelpers::harness(['name' => 'audit-field-agent-caller']);
    $r = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options('agent'));
    expect($r->isOk())->toBeTrue();
    $entry = $h['audit']->all()[0] ?? [];
    expect($entry)->toHaveKey('caller');
});

it("happy: audit field actor set for caller agent on success [D-010]", function () {
    $h = AuditHelpers::harness(['name' => 'audit-field-agent-actor']);
    $r = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options('agent'));
    expect($r->isOk())->toBeTrue();
    $entry = $h['audit']->all()[0] ?? [];
    expect($entry)->toHaveKey('actor');
});

it("happy: audit field scope set for caller agent on success [D-010]", function () {
    $h = AuditHelpers::harness(['name' => 'audit-field-agent-scope']);
    $r = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options('agent'));
    expect($r->isOk())->toBeTrue();
    $entry = $h['audit']->all()[0] ?? [];
    expect($entry)->toHaveKey('scope');
});

it("happy: audit field duration set for caller agent on success [D-010]", function () {
    $h = AuditHelpers::harness(['name' => 'audit-field-agent-duration']);
    $r = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options('agent'));
    expect($r->isOk())->toBeTrue();
    $entry = $h['audit']->all()[0] ?? [];
    expect($entry)->toHaveKey('duration');
});

it("happy: audit field result set for caller agent on success [D-010]", function () {
    $h = AuditHelpers::harness(['name' => 'audit-field-agent-result']);
    $r = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options('agent'));
    expect($r->isOk())->toBeTrue();
    $entry = $h['audit']->all()[0] ?? [];
    expect($entry)->toHaveKey('result');
});

it("happy: audit field name set for caller mcp on success [D-010]", function () {
    $h = AuditHelpers::harness(['name' => 'audit-field-mcp-name']);
    $r = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options('mcp'));
    expect($r->isOk())->toBeTrue();
    $entry = $h['audit']->all()[0] ?? [];
    expect($entry)->toHaveKey('name');
});

it("happy: audit field caller set for caller mcp on success [D-010]", function () {
    $h = AuditHelpers::harness(['name' => 'audit-field-mcp-caller']);
    $r = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options('mcp'));
    expect($r->isOk())->toBeTrue();
    $entry = $h['audit']->all()[0] ?? [];
    expect($entry)->toHaveKey('caller');
});

it("happy: audit field actor set for caller mcp on success [D-010]", function () {
    $h = AuditHelpers::harness(['name' => 'audit-field-mcp-actor']);
    $r = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options('mcp'));
    expect($r->isOk())->toBeTrue();
    $entry = $h['audit']->all()[0] ?? [];
    expect($entry)->toHaveKey('actor');
});

it("happy: audit field scope set for caller mcp on success [D-010]", function () {
    $h = AuditHelpers::harness(['name' => 'audit-field-mcp-scope']);
    $r = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options('mcp'));
    expect($r->isOk())->toBeTrue();
    $entry = $h['audit']->all()[0] ?? [];
    expect($entry)->toHaveKey('scope');
});

it("happy: audit field duration set for caller mcp on success [D-010]", function () {
    $h = AuditHelpers::harness(['name' => 'audit-field-mcp-duration']);
    $r = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options('mcp'));
    expect($r->isOk())->toBeTrue();
    $entry = $h['audit']->all()[0] ?? [];
    expect($entry)->toHaveKey('duration');
});

it("happy: audit field result set for caller mcp on success [D-010]", function () {
    $h = AuditHelpers::harness(['name' => 'audit-field-mcp-result']);
    $r = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options('mcp'));
    expect($r->isOk())->toBeTrue();
    $entry = $h['audit']->all()[0] ?? [];
    expect($entry)->toHaveKey('result');
});

it("happy: audit field name set for caller http on success [D-010]", function () {
    $h = AuditHelpers::harness(['name' => 'audit-field-http-name']);
    $r = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options('http'));
    expect($r->isOk())->toBeTrue();
    $entry = $h['audit']->all()[0] ?? [];
    expect($entry)->toHaveKey('name');
});

it("happy: audit field caller set for caller http on success [D-010]", function () {
    $h = AuditHelpers::harness(['name' => 'audit-field-http-caller']);
    $r = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options('http'));
    expect($r->isOk())->toBeTrue();
    $entry = $h['audit']->all()[0] ?? [];
    expect($entry)->toHaveKey('caller');
});

it("happy: audit field actor set for caller http on success [D-010]", function () {
    $h = AuditHelpers::harness(['name' => 'audit-field-http-actor']);
    $r = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options('http'));
    expect($r->isOk())->toBeTrue();
    $entry = $h['audit']->all()[0] ?? [];
    expect($entry)->toHaveKey('actor');
});

it("happy: audit field scope set for caller http on success [D-010]", function () {
    $h = AuditHelpers::harness(['name' => 'audit-field-http-scope']);
    $r = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options('http'));
    expect($r->isOk())->toBeTrue();
    $entry = $h['audit']->all()[0] ?? [];
    expect($entry)->toHaveKey('scope');
});

it("happy: audit field duration set for caller http on success [D-010]", function () {
    $h = AuditHelpers::harness(['name' => 'audit-field-http-duration']);
    $r = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options('http'));
    expect($r->isOk())->toBeTrue();
    $entry = $h['audit']->all()[0] ?? [];
    expect($entry)->toHaveKey('duration');
});

it("happy: audit field result set for caller http on success [D-010]", function () {
    $h = AuditHelpers::harness(['name' => 'audit-field-http-result']);
    $r = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options('http'));
    expect($r->isOk())->toBeTrue();
    $entry = $h['audit']->all()[0] ?? [];
    expect($entry)->toHaveKey('result');
});

it("happy: audit field name set for caller cli on success [D-010]", function () {
    $h = AuditHelpers::harness(['name' => 'audit-field-cli-name']);
    $r = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options('cli'));
    expect($r->isOk())->toBeTrue();
    $entry = $h['audit']->all()[0] ?? [];
    expect($entry)->toHaveKey('name');
});

it("happy: audit field caller set for caller cli on success [D-010]", function () {
    $h = AuditHelpers::harness(['name' => 'audit-field-cli-caller']);
    $r = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options('cli'));
    expect($r->isOk())->toBeTrue();
    $entry = $h['audit']->all()[0] ?? [];
    expect($entry)->toHaveKey('caller');
});

it("happy: audit field actor set for caller cli on success [D-010]", function () {
    $h = AuditHelpers::harness(['name' => 'audit-field-cli-actor']);
    $r = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options('cli'));
    expect($r->isOk())->toBeTrue();
    $entry = $h['audit']->all()[0] ?? [];
    expect($entry)->toHaveKey('actor');
});

it("happy: audit field scope set for caller cli on success [D-010]", function () {
    $h = AuditHelpers::harness(['name' => 'audit-field-cli-scope']);
    $r = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options('cli'));
    expect($r->isOk())->toBeTrue();
    $entry = $h['audit']->all()[0] ?? [];
    expect($entry)->toHaveKey('scope');
});

it("happy: audit field duration set for caller cli on success [D-010]", function () {
    $h = AuditHelpers::harness(['name' => 'audit-field-cli-duration']);
    $r = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options('cli'));
    expect($r->isOk())->toBeTrue();
    $entry = $h['audit']->all()[0] ?? [];
    expect($entry)->toHaveKey('duration');
});

it("happy: audit field result set for caller cli on success [D-010]", function () {
    $h = AuditHelpers::harness(['name' => 'audit-field-cli-result']);
    $r = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options('cli'));
    expect($r->isOk())->toBeTrue();
    $entry = $h['audit']->all()[0] ?? [];
    expect($entry)->toHaveKey('result');
});

it("happy: audit field name set for caller job on success [D-010]", function () {
    $h = AuditHelpers::harness(['name' => 'audit-field-job-name']);
    $r = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options('job'));
    expect($r->isOk())->toBeTrue();
    $entry = $h['audit']->all()[0] ?? [];
    expect($entry)->toHaveKey('name');
});

it("happy: audit field caller set for caller job on success [D-010]", function () {
    $h = AuditHelpers::harness(['name' => 'audit-field-job-caller']);
    $r = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options('job'));
    expect($r->isOk())->toBeTrue();
    $entry = $h['audit']->all()[0] ?? [];
    expect($entry)->toHaveKey('caller');
});

it("happy: audit field actor set for caller job on success [D-010]", function () {
    $h = AuditHelpers::harness(['name' => 'audit-field-job-actor']);
    $r = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options('job'));
    expect($r->isOk())->toBeTrue();
    $entry = $h['audit']->all()[0] ?? [];
    expect($entry)->toHaveKey('actor');
});

it("happy: audit field scope set for caller job on success [D-010]", function () {
    $h = AuditHelpers::harness(['name' => 'audit-field-job-scope']);
    $r = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options('job'));
    expect($r->isOk())->toBeTrue();
    $entry = $h['audit']->all()[0] ?? [];
    expect($entry)->toHaveKey('scope');
});

it("happy: audit field duration set for caller job on success [D-010]", function () {
    $h = AuditHelpers::harness(['name' => 'audit-field-job-duration']);
    $r = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options('job'));
    expect($r->isOk())->toBeTrue();
    $entry = $h['audit']->all()[0] ?? [];
    expect($entry)->toHaveKey('duration');
});

it("happy: audit field result set for caller job on success [D-010]", function () {
    $h = AuditHelpers::harness(['name' => 'audit-field-job-result']);
    $r = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options('job'));
    expect($r->isOk())->toBeTrue();
    $entry = $h['audit']->all()[0] ?? [];
    expect($entry)->toHaveKey('result');
});

