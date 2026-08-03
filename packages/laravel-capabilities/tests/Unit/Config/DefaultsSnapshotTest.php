<?php

// REQ-014: Defaults snapshot (CFG-001). Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Boot\CapabilitiesConfig;

it('happy: default surfaces.agent.enabled is True [CFG-001]', function () {
    expect(CapabilitiesConfig::get('surfaces.agent.enabled'))->toBe(true);
});

it('happy: default surfaces.mcp.enabled is True [CFG-001]', function () {
    expect(CapabilitiesConfig::get('surfaces.mcp.enabled'))->toBe(true);
});

it('happy: default surfaces.http.enabled is True [CFG-001]', function () {
    expect(CapabilitiesConfig::get('surfaces.http.enabled'))->toBe(true);
});

it('happy: default surfaces.cli.enabled is True [CFG-001]', function () {
    expect(CapabilitiesConfig::get('surfaces.cli.enabled'))->toBe(true);
});

it('happy: default surfaces.job.enabled is True [CFG-001]', function () {
    expect(CapabilitiesConfig::get('surfaces.job.enabled'))->toBe(true);
});

it('happy: default surfaces.artisan.enabled is True [CFG-001]', function () {
    expect(CapabilitiesConfig::get('surfaces.artisan.enabled'))->toBe(true);
});

it('happy: default surfaces.messaging.enabled is False [CFG-001]', function () {
    expect(CapabilitiesConfig::get('surfaces.messaging.enabled'))->toBe(false);
});

it("happy: default audit.mode is 'best_effort' [CFG-001]", function () {
    expect(CapabilitiesConfig::get('audit.mode'))->toBe('best_effort');
});

it('happy: default audit.required is False [CFG-001]', function () {
    expect(CapabilitiesConfig::get('audit.required'))->toBe(false);
});

it('happy: default transactions.wrap_run is False [CFG-001]', function () {
    expect(CapabilitiesConfig::get('transactions.wrap_run'))->toBe(false);
});

it('happy: default events.enabled is True [CFG-001]', function () {
    expect(CapabilitiesConfig::get('events.enabled'))->toBe(true);
});

it('happy: default approval.ttl_hours is 24 [CFG-001]', function () {
    expect(CapabilitiesConfig::get('approval.ttl_hours'))->toBe(24);
});

it("happy: default approval.execution is 'deferred' [CFG-001]", function () {
    expect(CapabilitiesConfig::get('approval.execution'))->toBe('deferred');
});

it('happy: default approval.resume.enabled is True [CFG-001]', function () {
    expect(CapabilitiesConfig::get('approval.resume.enabled'))->toBe(true);
});

it('happy: default approval.resume.every_seconds is 60 [CFG-001]', function () {
    expect(CapabilitiesConfig::get('approval.resume.every_seconds'))->toBe(60);
});

it('happy: default approval.resume.grace_seconds is 30 [CFG-001]', function () {
    expect(CapabilitiesConfig::get('approval.resume.grace_seconds'))->toBe(30);
});

it('happy: default approval.resume.stuck_after_seconds is 300 [CFG-001]', function () {
    expect(CapabilitiesConfig::get('approval.resume.stuck_after_seconds'))->toBe(300);
});

it('happy: default approval.resume.lease_seconds is 120 [CFG-001]', function () {
    expect(CapabilitiesConfig::get('approval.resume.lease_seconds'))->toBe(120);
});

it('happy: default idempotency.enabled is True [CFG-001]', function () {
    expect(CapabilitiesConfig::get('idempotency.enabled'))->toBe(true);
});

it('happy: default idempotency.ttl_hours is 24 [CFG-001]', function () {
    expect(CapabilitiesConfig::get('idempotency.ttl_hours'))->toBe(24);
});

it('happy: default validation.validate_output is True [CFG-001]', function () {
    expect(CapabilitiesConfig::get('validation.validate_output'))->toBe(true);
});

it('happy: default rate_limits.enabled is True [CFG-001]', function () {
    expect(CapabilitiesConfig::get('rate_limits.enabled'))->toBe(true);
});

it('happy: default observability.metrics is True [CFG-001]', function () {
    expect(CapabilitiesConfig::get('observability.metrics'))->toBe(true);
});

it('happy: default observability.tracing is True [CFG-001]', function () {
    expect(CapabilitiesConfig::get('observability.tracing'))->toBe(true);
});

it('happy: default surfaces.mcp.auth.allow_integration_credentials is False [CFG-001]', function () {
    expect(CapabilitiesConfig::get('surfaces.mcp.auth.allow_integration_credentials'))->toBe(false);
});

it("happy: default surfaces.mcp.auth.default_profile is 'user_pat' [CFG-001]", function () {
    expect(CapabilitiesConfig::get('surfaces.mcp.auth.default_profile'))->toBe('user_pat');
});

it('happy: default surfaces.agent.require_profile is True [CFG-001]', function () {
    expect(CapabilitiesConfig::get('surfaces.agent.require_profile'))->toBe(true);
});

it('happy: default surfaces.mcp.require_profile is True [CFG-001]', function () {
    expect(CapabilitiesConfig::get('surfaces.mcp.require_profile'))->toBe(true);
});

it('happy: default clients.reject_upgrade_attempts is False [CFG-001]', function () {
    expect(CapabilitiesConfig::get('clients.reject_upgrade_attempts'))->toBe(false);
});
