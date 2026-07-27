<?php

// REQ-015: Architecture contract guards. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Tests\Fixtures\ArchitectureHelpers as A;
use Rawphp\Capabilities\Tests\Fixtures\PipelineHelpers;
use Rawphp\Capabilities\Pipeline\PipelineStages;
use Rawphp\Capabilities\Boot\CapabilitiesConfig;
use Rawphp\Capabilities\Support\ErrorCodeMap;

it("happy: rule one_run holds for caller agent [DESIGN]", function () {
    A::assertDesignRule('one_run');
    // caller agent shares pipeline governance
    A::assertConcernApplies('authorize', 'agent');
});

it("fail: rule one_run violation via caller agent refused [DESIGN]", function () {
    A::assertDesignRuleViolationRefused('one_run');
    A::assertConcernCannotSkip('authorize', 'agent');
});

it("happy: rule one_run holds for caller mcp [DESIGN]", function () {
    A::assertDesignRule('one_run');
    // caller mcp shares pipeline governance
    A::assertConcernApplies('authorize', 'mcp');
});

it("fail: rule one_run violation via caller mcp refused [DESIGN]", function () {
    A::assertDesignRuleViolationRefused('one_run');
    A::assertConcernCannotSkip('authorize', 'mcp');
});

it("happy: rule one_run holds for caller http [DESIGN]", function () {
    A::assertDesignRule('one_run');
    // caller http shares pipeline governance
    A::assertConcernApplies('authorize', 'http');
});

it("fail: rule one_run violation via caller http refused [DESIGN]", function () {
    A::assertDesignRuleViolationRefused('one_run');
    A::assertConcernCannotSkip('authorize', 'http');
});

it("happy: rule one_run holds for caller cli [DESIGN]", function () {
    A::assertDesignRule('one_run');
    // caller cli shares pipeline governance
    A::assertConcernApplies('authorize', 'cli');
});

it("fail: rule one_run violation via caller cli refused [DESIGN]", function () {
    A::assertDesignRuleViolationRefused('one_run');
    A::assertConcernCannotSkip('authorize', 'cli');
});

it("happy: rule one_run holds for caller job [DESIGN]", function () {
    A::assertDesignRule('one_run');
    // caller job shares pipeline governance
    A::assertConcernApplies('authorize', 'job');
});

it("fail: rule one_run violation via caller job refused [DESIGN]", function () {
    A::assertDesignRuleViolationRefused('one_run');
    A::assertConcernCannotSkip('authorize', 'job');
});

it("happy: rule adapters_dumb holds for caller agent [DESIGN]", function () {
    A::assertDesignRule('adapters_dumb');
    // caller agent shares pipeline governance
    A::assertConcernApplies('authorize', 'agent');
});

it("fail: rule adapters_dumb violation via caller agent refused [DESIGN]", function () {
    A::assertDesignRuleViolationRefused('adapters_dumb');
    A::assertConcernCannotSkip('authorize', 'agent');
});

it("happy: rule adapters_dumb holds for caller mcp [DESIGN]", function () {
    A::assertDesignRule('adapters_dumb');
    // caller mcp shares pipeline governance
    A::assertConcernApplies('authorize', 'mcp');
});

it("fail: rule adapters_dumb violation via caller mcp refused [DESIGN]", function () {
    A::assertDesignRuleViolationRefused('adapters_dumb');
    A::assertConcernCannotSkip('authorize', 'mcp');
});

it("happy: rule adapters_dumb holds for caller http [DESIGN]", function () {
    A::assertDesignRule('adapters_dumb');
    // caller http shares pipeline governance
    A::assertConcernApplies('authorize', 'http');
});

it("fail: rule adapters_dumb violation via caller http refused [DESIGN]", function () {
    A::assertDesignRuleViolationRefused('adapters_dumb');
    A::assertConcernCannotSkip('authorize', 'http');
});

it("happy: rule adapters_dumb holds for caller cli [DESIGN]", function () {
    A::assertDesignRule('adapters_dumb');
    // caller cli shares pipeline governance
    A::assertConcernApplies('authorize', 'cli');
});

it("fail: rule adapters_dumb violation via caller cli refused [DESIGN]", function () {
    A::assertDesignRuleViolationRefused('adapters_dumb');
    A::assertConcernCannotSkip('authorize', 'cli');
});

it("happy: rule adapters_dumb holds for caller job [DESIGN]", function () {
    A::assertDesignRule('adapters_dumb');
    // caller job shares pipeline governance
    A::assertConcernApplies('authorize', 'job');
});

it("fail: rule adapters_dumb violation via caller job refused [DESIGN]", function () {
    A::assertDesignRuleViolationRefused('adapters_dumb');
    A::assertConcernCannotSkip('authorize', 'job');
});

it("happy: rule domain_yours holds for caller agent [DESIGN]", function () {
    A::assertDesignRule('domain_yours');
    // caller agent shares pipeline governance
    A::assertConcernApplies('authorize', 'agent');
});

it("fail: rule domain_yours violation via caller agent refused [DESIGN]", function () {
    A::assertDesignRuleViolationRefused('domain_yours');
    A::assertConcernCannotSkip('authorize', 'agent');
});

it("happy: rule domain_yours holds for caller mcp [DESIGN]", function () {
    A::assertDesignRule('domain_yours');
    // caller mcp shares pipeline governance
    A::assertConcernApplies('authorize', 'mcp');
});

it("fail: rule domain_yours violation via caller mcp refused [DESIGN]", function () {
    A::assertDesignRuleViolationRefused('domain_yours');
    A::assertConcernCannotSkip('authorize', 'mcp');
});

it("happy: rule domain_yours holds for caller http [DESIGN]", function () {
    A::assertDesignRule('domain_yours');
    // caller http shares pipeline governance
    A::assertConcernApplies('authorize', 'http');
});

it("fail: rule domain_yours violation via caller http refused [DESIGN]", function () {
    A::assertDesignRuleViolationRefused('domain_yours');
    A::assertConcernCannotSkip('authorize', 'http');
});

it("happy: rule domain_yours holds for caller cli [DESIGN]", function () {
    A::assertDesignRule('domain_yours');
    // caller cli shares pipeline governance
    A::assertConcernApplies('authorize', 'cli');
});

it("fail: rule domain_yours violation via caller cli refused [DESIGN]", function () {
    A::assertDesignRuleViolationRefused('domain_yours');
    A::assertConcernCannotSkip('authorize', 'cli');
});

it("happy: rule domain_yours holds for caller job [DESIGN]", function () {
    A::assertDesignRule('domain_yours');
    // caller job shares pipeline governance
    A::assertConcernApplies('authorize', 'job');
});

it("fail: rule domain_yours violation via caller job refused [DESIGN]", function () {
    A::assertDesignRuleViolationRefused('domain_yours');
    A::assertConcernCannotSkip('authorize', 'job');
});

it("happy: rule fail_closed holds for caller agent [DESIGN]", function () {
    A::assertDesignRule('fail_closed');
    // caller agent shares pipeline governance
    A::assertConcernApplies('authorize', 'agent');
});

it("fail: rule fail_closed violation via caller agent refused [DESIGN]", function () {
    A::assertDesignRuleViolationRefused('fail_closed');
    A::assertConcernCannotSkip('authorize', 'agent');
});

it("happy: rule fail_closed holds for caller mcp [DESIGN]", function () {
    A::assertDesignRule('fail_closed');
    // caller mcp shares pipeline governance
    A::assertConcernApplies('authorize', 'mcp');
});

it("fail: rule fail_closed violation via caller mcp refused [DESIGN]", function () {
    A::assertDesignRuleViolationRefused('fail_closed');
    A::assertConcernCannotSkip('authorize', 'mcp');
});

it("happy: rule fail_closed holds for caller http [DESIGN]", function () {
    A::assertDesignRule('fail_closed');
    // caller http shares pipeline governance
    A::assertConcernApplies('authorize', 'http');
});

it("fail: rule fail_closed violation via caller http refused [DESIGN]", function () {
    A::assertDesignRuleViolationRefused('fail_closed');
    A::assertConcernCannotSkip('authorize', 'http');
});

it("happy: rule fail_closed holds for caller cli [DESIGN]", function () {
    A::assertDesignRule('fail_closed');
    // caller cli shares pipeline governance
    A::assertConcernApplies('authorize', 'cli');
});

it("fail: rule fail_closed violation via caller cli refused [DESIGN]", function () {
    A::assertDesignRuleViolationRefused('fail_closed');
    A::assertConcernCannotSkip('authorize', 'cli');
});

it("happy: rule fail_closed holds for caller job [DESIGN]", function () {
    A::assertDesignRule('fail_closed');
    // caller job shares pipeline governance
    A::assertConcernApplies('authorize', 'job');
});

it("fail: rule fail_closed violation via caller job refused [DESIGN]", function () {
    A::assertDesignRuleViolationRefused('fail_closed');
    A::assertConcernCannotSkip('authorize', 'job');
});

it("happy: rule no_silent_actors holds for caller agent [DESIGN]", function () {
    A::assertDesignRule('no_silent_actors');
    // caller agent shares pipeline governance
    A::assertConcernApplies('authorize', 'agent');
});

it("fail: rule no_silent_actors violation via caller agent refused [DESIGN]", function () {
    A::assertDesignRuleViolationRefused('no_silent_actors');
    A::assertConcernCannotSkip('authorize', 'agent');
});

it("happy: rule no_silent_actors holds for caller mcp [DESIGN]", function () {
    A::assertDesignRule('no_silent_actors');
    // caller mcp shares pipeline governance
    A::assertConcernApplies('authorize', 'mcp');
});

it("fail: rule no_silent_actors violation via caller mcp refused [DESIGN]", function () {
    A::assertDesignRuleViolationRefused('no_silent_actors');
    A::assertConcernCannotSkip('authorize', 'mcp');
});

it("happy: rule no_silent_actors holds for caller http [DESIGN]", function () {
    A::assertDesignRule('no_silent_actors');
    // caller http shares pipeline governance
    A::assertConcernApplies('authorize', 'http');
});

it("fail: rule no_silent_actors violation via caller http refused [DESIGN]", function () {
    A::assertDesignRuleViolationRefused('no_silent_actors');
    A::assertConcernCannotSkip('authorize', 'http');
});

it("happy: rule no_silent_actors holds for caller cli [DESIGN]", function () {
    A::assertDesignRule('no_silent_actors');
    // caller cli shares pipeline governance
    A::assertConcernApplies('authorize', 'cli');
});

it("fail: rule no_silent_actors violation via caller cli refused [DESIGN]", function () {
    A::assertDesignRuleViolationRefused('no_silent_actors');
    A::assertConcernCannotSkip('authorize', 'cli');
});

it("happy: rule no_silent_actors holds for caller job [DESIGN]", function () {
    A::assertDesignRule('no_silent_actors');
    // caller job shares pipeline governance
    A::assertConcernApplies('authorize', 'job');
});

it("fail: rule no_silent_actors violation via caller job refused [DESIGN]", function () {
    A::assertDesignRuleViolationRefused('no_silent_actors');
    A::assertConcernCannotSkip('authorize', 'job');
});

it("happy: rule no_ambient_tenancy holds for caller agent [DESIGN]", function () {
    A::assertDesignRule('no_ambient_tenancy');
    // caller agent shares pipeline governance
    A::assertConcernApplies('authorize', 'agent');
});

it("fail: rule no_ambient_tenancy violation via caller agent refused [DESIGN]", function () {
    A::assertDesignRuleViolationRefused('no_ambient_tenancy');
    A::assertConcernCannotSkip('authorize', 'agent');
});

it("happy: rule no_ambient_tenancy holds for caller mcp [DESIGN]", function () {
    A::assertDesignRule('no_ambient_tenancy');
    // caller mcp shares pipeline governance
    A::assertConcernApplies('authorize', 'mcp');
});

it("fail: rule no_ambient_tenancy violation via caller mcp refused [DESIGN]", function () {
    A::assertDesignRuleViolationRefused('no_ambient_tenancy');
    A::assertConcernCannotSkip('authorize', 'mcp');
});

it("happy: rule no_ambient_tenancy holds for caller http [DESIGN]", function () {
    A::assertDesignRule('no_ambient_tenancy');
    // caller http shares pipeline governance
    A::assertConcernApplies('authorize', 'http');
});

it("fail: rule no_ambient_tenancy violation via caller http refused [DESIGN]", function () {
    A::assertDesignRuleViolationRefused('no_ambient_tenancy');
    A::assertConcernCannotSkip('authorize', 'http');
});

it("happy: rule no_ambient_tenancy holds for caller cli [DESIGN]", function () {
    A::assertDesignRule('no_ambient_tenancy');
    // caller cli shares pipeline governance
    A::assertConcernApplies('authorize', 'cli');
});

it("fail: rule no_ambient_tenancy violation via caller cli refused [DESIGN]", function () {
    A::assertDesignRuleViolationRefused('no_ambient_tenancy');
    A::assertConcernCannotSkip('authorize', 'cli');
});

it("happy: rule no_ambient_tenancy holds for caller job [DESIGN]", function () {
    A::assertDesignRule('no_ambient_tenancy');
    // caller job shares pipeline governance
    A::assertConcernApplies('authorize', 'job');
});

it("fail: rule no_ambient_tenancy violation via caller job refused [DESIGN]", function () {
    A::assertDesignRuleViolationRefused('no_ambient_tenancy');
    A::assertConcernCannotSkip('authorize', 'job');
});

it("happy: rule idempotent_retries holds for caller agent [DESIGN]", function () {
    A::assertDesignRule('idempotent_retries');
    // caller agent shares pipeline governance
    A::assertConcernApplies('authorize', 'agent');
});

it("fail: rule idempotent_retries violation via caller agent refused [DESIGN]", function () {
    A::assertDesignRuleViolationRefused('idempotent_retries');
    A::assertConcernCannotSkip('authorize', 'agent');
});

it("happy: rule idempotent_retries holds for caller mcp [DESIGN]", function () {
    A::assertDesignRule('idempotent_retries');
    // caller mcp shares pipeline governance
    A::assertConcernApplies('authorize', 'mcp');
});

it("fail: rule idempotent_retries violation via caller mcp refused [DESIGN]", function () {
    A::assertDesignRuleViolationRefused('idempotent_retries');
    A::assertConcernCannotSkip('authorize', 'mcp');
});

it("happy: rule idempotent_retries holds for caller http [DESIGN]", function () {
    A::assertDesignRule('idempotent_retries');
    // caller http shares pipeline governance
    A::assertConcernApplies('authorize', 'http');
});

it("fail: rule idempotent_retries violation via caller http refused [DESIGN]", function () {
    A::assertDesignRuleViolationRefused('idempotent_retries');
    A::assertConcernCannotSkip('authorize', 'http');
});

it("happy: rule idempotent_retries holds for caller cli [DESIGN]", function () {
    A::assertDesignRule('idempotent_retries');
    // caller cli shares pipeline governance
    A::assertConcernApplies('authorize', 'cli');
});

it("fail: rule idempotent_retries violation via caller cli refused [DESIGN]", function () {
    A::assertDesignRuleViolationRefused('idempotent_retries');
    A::assertConcernCannotSkip('authorize', 'cli');
});

it("happy: rule idempotent_retries holds for caller job [DESIGN]", function () {
    A::assertDesignRule('idempotent_retries');
    // caller job shares pipeline governance
    A::assertConcernApplies('authorize', 'job');
});

it("fail: rule idempotent_retries violation via caller job refused [DESIGN]", function () {
    A::assertDesignRuleViolationRefused('idempotent_retries');
    A::assertConcernCannotSkip('authorize', 'job');
});

it("happy: rule approvals_state_machine holds for caller agent [DESIGN]", function () {
    A::assertDesignRule('approvals_state_machine');
    // caller agent shares pipeline governance
    A::assertConcernApplies('authorize', 'agent');
});

it("fail: rule approvals_state_machine violation via caller agent refused [DESIGN]", function () {
    A::assertDesignRuleViolationRefused('approvals_state_machine');
    A::assertConcernCannotSkip('authorize', 'agent');
});

it("happy: rule approvals_state_machine holds for caller mcp [DESIGN]", function () {
    A::assertDesignRule('approvals_state_machine');
    // caller mcp shares pipeline governance
    A::assertConcernApplies('authorize', 'mcp');
});

it("fail: rule approvals_state_machine violation via caller mcp refused [DESIGN]", function () {
    A::assertDesignRuleViolationRefused('approvals_state_machine');
    A::assertConcernCannotSkip('authorize', 'mcp');
});

it("happy: rule approvals_state_machine holds for caller http [DESIGN]", function () {
    A::assertDesignRule('approvals_state_machine');
    // caller http shares pipeline governance
    A::assertConcernApplies('authorize', 'http');
});

it("fail: rule approvals_state_machine violation via caller http refused [DESIGN]", function () {
    A::assertDesignRuleViolationRefused('approvals_state_machine');
    A::assertConcernCannotSkip('authorize', 'http');
});

it("happy: rule approvals_state_machine holds for caller cli [DESIGN]", function () {
    A::assertDesignRule('approvals_state_machine');
    // caller cli shares pipeline governance
    A::assertConcernApplies('authorize', 'cli');
});

it("fail: rule approvals_state_machine violation via caller cli refused [DESIGN]", function () {
    A::assertDesignRuleViolationRefused('approvals_state_machine');
    A::assertConcernCannotSkip('authorize', 'cli');
});

it("happy: rule approvals_state_machine holds for caller job [DESIGN]", function () {
    A::assertDesignRule('approvals_state_machine');
    // caller job shares pipeline governance
    A::assertConcernApplies('authorize', 'job');
});

it("fail: rule approvals_state_machine violation via caller job refused [DESIGN]", function () {
    A::assertDesignRuleViolationRefused('approvals_state_machine');
    A::assertConcernCannotSkip('authorize', 'job');
});

it("happy: rule profiles_not_dump holds for caller agent [DESIGN]", function () {
    A::assertDesignRule('profiles_not_dump');
    // caller agent shares pipeline governance
    A::assertConcernApplies('authorize', 'agent');
});

it("fail: rule profiles_not_dump violation via caller agent refused [DESIGN]", function () {
    A::assertDesignRuleViolationRefused('profiles_not_dump');
    A::assertConcernCannotSkip('authorize', 'agent');
});

it("happy: rule profiles_not_dump holds for caller mcp [DESIGN]", function () {
    A::assertDesignRule('profiles_not_dump');
    // caller mcp shares pipeline governance
    A::assertConcernApplies('authorize', 'mcp');
});

it("fail: rule profiles_not_dump violation via caller mcp refused [DESIGN]", function () {
    A::assertDesignRuleViolationRefused('profiles_not_dump');
    A::assertConcernCannotSkip('authorize', 'mcp');
});

it("happy: rule profiles_not_dump holds for caller http [DESIGN]", function () {
    A::assertDesignRule('profiles_not_dump');
    // caller http shares pipeline governance
    A::assertConcernApplies('authorize', 'http');
});

it("fail: rule profiles_not_dump violation via caller http refused [DESIGN]", function () {
    A::assertDesignRuleViolationRefused('profiles_not_dump');
    A::assertConcernCannotSkip('authorize', 'http');
});

it("happy: rule profiles_not_dump holds for caller cli [DESIGN]", function () {
    A::assertDesignRule('profiles_not_dump');
    // caller cli shares pipeline governance
    A::assertConcernApplies('authorize', 'cli');
});

it("fail: rule profiles_not_dump violation via caller cli refused [DESIGN]", function () {
    A::assertDesignRuleViolationRefused('profiles_not_dump');
    A::assertConcernCannotSkip('authorize', 'cli');
});

it("happy: rule profiles_not_dump holds for caller job [DESIGN]", function () {
    A::assertDesignRule('profiles_not_dump');
    // caller job shares pipeline governance
    A::assertConcernApplies('authorize', 'job');
});

it("fail: rule profiles_not_dump violation via caller job refused [DESIGN]", function () {
    A::assertDesignRuleViolationRefused('profiles_not_dump');
    A::assertConcernCannotSkip('authorize', 'job');
});

it("happy: rule one_http_api holds for caller agent [DESIGN]", function () {
    A::assertDesignRule('one_http_api');
    // caller agent shares pipeline governance
    A::assertConcernApplies('authorize', 'agent');
});

it("fail: rule one_http_api violation via caller agent refused [DESIGN]", function () {
    A::assertDesignRuleViolationRefused('one_http_api');
    A::assertConcernCannotSkip('authorize', 'agent');
});

it("happy: rule one_http_api holds for caller mcp [DESIGN]", function () {
    A::assertDesignRule('one_http_api');
    // caller mcp shares pipeline governance
    A::assertConcernApplies('authorize', 'mcp');
});

it("fail: rule one_http_api violation via caller mcp refused [DESIGN]", function () {
    A::assertDesignRuleViolationRefused('one_http_api');
    A::assertConcernCannotSkip('authorize', 'mcp');
});

it("happy: rule one_http_api holds for caller http [DESIGN]", function () {
    A::assertDesignRule('one_http_api');
    // caller http shares pipeline governance
    A::assertConcernApplies('authorize', 'http');
});

it("fail: rule one_http_api violation via caller http refused [DESIGN]", function () {
    A::assertDesignRuleViolationRefused('one_http_api');
    A::assertConcernCannotSkip('authorize', 'http');
});

it("happy: rule one_http_api holds for caller cli [DESIGN]", function () {
    A::assertDesignRule('one_http_api');
    // caller cli shares pipeline governance
    A::assertConcernApplies('authorize', 'cli');
});

it("fail: rule one_http_api violation via caller cli refused [DESIGN]", function () {
    A::assertDesignRuleViolationRefused('one_http_api');
    A::assertConcernCannotSkip('authorize', 'cli');
});

it("happy: rule one_http_api holds for caller job [DESIGN]", function () {
    A::assertDesignRule('one_http_api');
    // caller job shares pipeline governance
    A::assertConcernApplies('authorize', 'job');
});

it("fail: rule one_http_api violation via caller job refused [DESIGN]", function () {
    A::assertDesignRuleViolationRefused('one_http_api');
    A::assertConcernCannotSkip('authorize', 'job');
});

it("happy: rule server_derived_caller holds for caller agent [DESIGN]", function () {
    A::assertDesignRule('server_derived_caller');
    // caller agent shares pipeline governance
    A::assertConcernApplies('authorize', 'agent');
});

it("fail: rule server_derived_caller violation via caller agent refused [DESIGN]", function () {
    A::assertDesignRuleViolationRefused('server_derived_caller');
    A::assertConcernCannotSkip('authorize', 'agent');
});

it("happy: rule server_derived_caller holds for caller mcp [DESIGN]", function () {
    A::assertDesignRule('server_derived_caller');
    // caller mcp shares pipeline governance
    A::assertConcernApplies('authorize', 'mcp');
});

it("fail: rule server_derived_caller violation via caller mcp refused [DESIGN]", function () {
    A::assertDesignRuleViolationRefused('server_derived_caller');
    A::assertConcernCannotSkip('authorize', 'mcp');
});

it("happy: rule server_derived_caller holds for caller http [DESIGN]", function () {
    A::assertDesignRule('server_derived_caller');
    // caller http shares pipeline governance
    A::assertConcernApplies('authorize', 'http');
});

it("fail: rule server_derived_caller violation via caller http refused [DESIGN]", function () {
    A::assertDesignRuleViolationRefused('server_derived_caller');
    A::assertConcernCannotSkip('authorize', 'http');
});

it("happy: rule server_derived_caller holds for caller cli [DESIGN]", function () {
    A::assertDesignRule('server_derived_caller');
    // caller cli shares pipeline governance
    A::assertConcernApplies('authorize', 'cli');
});

it("fail: rule server_derived_caller violation via caller cli refused [DESIGN]", function () {
    A::assertDesignRuleViolationRefused('server_derived_caller');
    A::assertConcernCannotSkip('authorize', 'cli');
});

it("happy: rule server_derived_caller holds for caller job [DESIGN]", function () {
    A::assertDesignRule('server_derived_caller');
    // caller job shares pipeline governance
    A::assertConcernApplies('authorize', 'job');
});

it("fail: rule server_derived_caller violation via caller job refused [DESIGN]", function () {
    A::assertDesignRuleViolationRefused('server_derived_caller');
    A::assertConcernCannotSkip('authorize', 'job');
});

it("happy: rule mcp_auth_profiles holds for caller agent [DESIGN]", function () {
    A::assertDesignRule('mcp_auth_profiles');
    // caller agent shares pipeline governance
    A::assertConcernApplies('authorize', 'agent');
});

it("fail: rule mcp_auth_profiles violation via caller agent refused [DESIGN]", function () {
    A::assertDesignRuleViolationRefused('mcp_auth_profiles');
    A::assertConcernCannotSkip('authorize', 'agent');
});

it("happy: rule mcp_auth_profiles holds for caller mcp [DESIGN]", function () {
    A::assertDesignRule('mcp_auth_profiles');
    // caller mcp shares pipeline governance
    A::assertConcernApplies('authorize', 'mcp');
});

it("fail: rule mcp_auth_profiles violation via caller mcp refused [DESIGN]", function () {
    A::assertDesignRuleViolationRefused('mcp_auth_profiles');
    A::assertConcernCannotSkip('authorize', 'mcp');
});

it("happy: rule mcp_auth_profiles holds for caller http [DESIGN]", function () {
    A::assertDesignRule('mcp_auth_profiles');
    // caller http shares pipeline governance
    A::assertConcernApplies('authorize', 'http');
});

it("fail: rule mcp_auth_profiles violation via caller http refused [DESIGN]", function () {
    A::assertDesignRuleViolationRefused('mcp_auth_profiles');
    A::assertConcernCannotSkip('authorize', 'http');
});

it("happy: rule mcp_auth_profiles holds for caller cli [DESIGN]", function () {
    A::assertDesignRule('mcp_auth_profiles');
    // caller cli shares pipeline governance
    A::assertConcernApplies('authorize', 'cli');
});

it("fail: rule mcp_auth_profiles violation via caller cli refused [DESIGN]", function () {
    A::assertDesignRuleViolationRefused('mcp_auth_profiles');
    A::assertConcernCannotSkip('authorize', 'cli');
});

it("happy: rule mcp_auth_profiles holds for caller job [DESIGN]", function () {
    A::assertDesignRule('mcp_auth_profiles');
    // caller job shares pipeline governance
    A::assertConcernApplies('authorize', 'job');
});

it("fail: rule mcp_auth_profiles violation via caller job refused [DESIGN]", function () {
    A::assertDesignRuleViolationRefused('mcp_auth_profiles');
    A::assertConcernCannotSkip('authorize', 'job');
});

