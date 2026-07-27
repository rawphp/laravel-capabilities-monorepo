<?php

// REQ-015: Parity cross-caller governance contract guards. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Tests\Fixtures\ParityHelpers as P;
use Rawphp\Capabilities\Tests\Fixtures\PipelineHelpers;
use Rawphp\Capabilities\Tests\Fixtures\ArchitectureHelpers as A;
use Rawphp\Capabilities\Pipeline\PipelineStages;
use Rawphp\Capabilities\Support\ErrorCodeMap;

it("fail: agent/json_schema_validate/no_run violated [PIPE-002]", function () {
    P::assertNoRun('agent', 'json_schema_validate');
});

it("fail: agent/json_schema_validate/no_domain_write violated [PIPE-002]", function () {
    P::assertNoDomainWrite('agent', 'json_schema_validate');
});

it("happy: agent/json_schema_validate/structured_error [PIPE-002]", function () {
    P::assertStructuredError('agent', 'json_schema_validate');
});

it("fail: agent/json_schema_validate/optional_deny_audit violated [PIPE-002]", function () {
    P::assertOptionalDenyAudit('agent', 'json_schema_validate');
});

it("fail: agent/hydrate_dto/no_run violated [PIPE-002]", function () {
    P::assertNoRun('agent', 'hydrate_dto');
});

it("fail: agent/hydrate_dto/no_domain_write violated [PIPE-002]", function () {
    P::assertNoDomainWrite('agent', 'hydrate_dto');
});

it("happy: agent/hydrate_dto/structured_error [PIPE-002]", function () {
    P::assertStructuredError('agent', 'hydrate_dto');
});

it("fail: agent/hydrate_dto/optional_deny_audit violated [PIPE-002]", function () {
    P::assertOptionalDenyAudit('agent', 'hydrate_dto');
});

it("fail: agent/server_only_validate/no_run violated [PIPE-002]", function () {
    P::assertNoRun('agent', 'server_only_validate');
});

it("fail: agent/server_only_validate/no_domain_write violated [PIPE-002]", function () {
    P::assertNoDomainWrite('agent', 'server_only_validate');
});

it("happy: agent/server_only_validate/structured_error [PIPE-002]", function () {
    P::assertStructuredError('agent', 'server_only_validate');
});

it("fail: agent/server_only_validate/optional_deny_audit violated [PIPE-002]", function () {
    P::assertOptionalDenyAudit('agent', 'server_only_validate');
});

it("fail: agent/resolve_actor/no_run violated [PIPE-002]", function () {
    P::assertNoRun('agent', 'resolve_actor');
});

it("fail: agent/resolve_actor/no_domain_write violated [PIPE-002]", function () {
    P::assertNoDomainWrite('agent', 'resolve_actor');
});

it("happy: agent/resolve_actor/structured_error [PIPE-002]", function () {
    P::assertStructuredError('agent', 'resolve_actor');
});

it("fail: agent/resolve_actor/optional_deny_audit violated [PIPE-002]", function () {
    P::assertOptionalDenyAudit('agent', 'resolve_actor');
});

it("fail: agent/resolve_scope/no_run violated [PIPE-002]", function () {
    P::assertNoRun('agent', 'resolve_scope');
});

it("fail: agent/resolve_scope/no_domain_write violated [PIPE-002]", function () {
    P::assertNoDomainWrite('agent', 'resolve_scope');
});

it("happy: agent/resolve_scope/structured_error [PIPE-002]", function () {
    P::assertStructuredError('agent', 'resolve_scope');
});

it("fail: agent/resolve_scope/optional_deny_audit violated [PIPE-002]", function () {
    P::assertOptionalDenyAudit('agent', 'resolve_scope');
});

it("fail: agent/idempotency_lookup/no_run violated [PIPE-002]", function () {
    P::assertNoRun('agent', 'idempotency_lookup');
});

it("fail: agent/idempotency_lookup/no_domain_write violated [PIPE-002]", function () {
    P::assertNoDomainWrite('agent', 'idempotency_lookup');
});

it("happy: agent/idempotency_lookup/structured_error [PIPE-002]", function () {
    P::assertStructuredError('agent', 'idempotency_lookup');
});

it("fail: agent/idempotency_lookup/optional_deny_audit violated [PIPE-002]", function () {
    P::assertOptionalDenyAudit('agent', 'idempotency_lookup');
});

it("fail: agent/authorize/no_run violated [PIPE-002]", function () {
    P::assertNoRun('agent', 'authorize');
});

it("fail: agent/authorize/no_domain_write violated [PIPE-002]", function () {
    P::assertNoDomainWrite('agent', 'authorize');
});

it("happy: agent/authorize/structured_error [PIPE-002]", function () {
    P::assertStructuredError('agent', 'authorize');
});

it("fail: agent/authorize/optional_deny_audit violated [PIPE-002]", function () {
    P::assertOptionalDenyAudit('agent', 'authorize');
});

it("fail: agent/needs_approval/no_run violated [PIPE-002]", function () {
    P::assertNoRun('agent', 'needs_approval');
});

it("fail: agent/needs_approval/no_domain_write violated [PIPE-002]", function () {
    P::assertNoDomainWrite('agent', 'needs_approval');
});

it("happy: agent/needs_approval/structured_error [PIPE-002]", function () {
    P::assertStructuredError('agent', 'needs_approval');
});

it("fail: agent/needs_approval/optional_deny_audit violated [PIPE-002]", function () {
    P::assertOptionalDenyAudit('agent', 'needs_approval');
});

it("fail: agent/rate_limit/no_run violated [PIPE-002]", function () {
    P::assertNoRun('agent', 'rate_limit');
});

it("fail: agent/rate_limit/no_domain_write violated [PIPE-002]", function () {
    P::assertNoDomainWrite('agent', 'rate_limit');
});

it("happy: agent/rate_limit/structured_error [PIPE-002]", function () {
    P::assertStructuredError('agent', 'rate_limit');
});

it("fail: agent/rate_limit/optional_deny_audit violated [PIPE-002]", function () {
    P::assertOptionalDenyAudit('agent', 'rate_limit');
});

it("fail: mcp/json_schema_validate/no_run violated [PIPE-002]", function () {
    P::assertNoRun('mcp', 'json_schema_validate');
});

it("fail: mcp/json_schema_validate/no_domain_write violated [PIPE-002]", function () {
    P::assertNoDomainWrite('mcp', 'json_schema_validate');
});

it("happy: mcp/json_schema_validate/structured_error [PIPE-002]", function () {
    P::assertStructuredError('mcp', 'json_schema_validate');
});

it("fail: mcp/json_schema_validate/optional_deny_audit violated [PIPE-002]", function () {
    P::assertOptionalDenyAudit('mcp', 'json_schema_validate');
});

it("fail: mcp/hydrate_dto/no_run violated [PIPE-002]", function () {
    P::assertNoRun('mcp', 'hydrate_dto');
});

it("fail: mcp/hydrate_dto/no_domain_write violated [PIPE-002]", function () {
    P::assertNoDomainWrite('mcp', 'hydrate_dto');
});

it("happy: mcp/hydrate_dto/structured_error [PIPE-002]", function () {
    P::assertStructuredError('mcp', 'hydrate_dto');
});

it("fail: mcp/hydrate_dto/optional_deny_audit violated [PIPE-002]", function () {
    P::assertOptionalDenyAudit('mcp', 'hydrate_dto');
});

it("fail: mcp/server_only_validate/no_run violated [PIPE-002]", function () {
    P::assertNoRun('mcp', 'server_only_validate');
});

it("fail: mcp/server_only_validate/no_domain_write violated [PIPE-002]", function () {
    P::assertNoDomainWrite('mcp', 'server_only_validate');
});

it("happy: mcp/server_only_validate/structured_error [PIPE-002]", function () {
    P::assertStructuredError('mcp', 'server_only_validate');
});

it("fail: mcp/server_only_validate/optional_deny_audit violated [PIPE-002]", function () {
    P::assertOptionalDenyAudit('mcp', 'server_only_validate');
});

it("fail: mcp/resolve_actor/no_run violated [PIPE-002]", function () {
    P::assertNoRun('mcp', 'resolve_actor');
});

it("fail: mcp/resolve_actor/no_domain_write violated [PIPE-002]", function () {
    P::assertNoDomainWrite('mcp', 'resolve_actor');
});

it("happy: mcp/resolve_actor/structured_error [PIPE-002]", function () {
    P::assertStructuredError('mcp', 'resolve_actor');
});

it("fail: mcp/resolve_actor/optional_deny_audit violated [PIPE-002]", function () {
    P::assertOptionalDenyAudit('mcp', 'resolve_actor');
});

it("fail: mcp/resolve_scope/no_run violated [PIPE-002]", function () {
    P::assertNoRun('mcp', 'resolve_scope');
});

it("fail: mcp/resolve_scope/no_domain_write violated [PIPE-002]", function () {
    P::assertNoDomainWrite('mcp', 'resolve_scope');
});

it("happy: mcp/resolve_scope/structured_error [PIPE-002]", function () {
    P::assertStructuredError('mcp', 'resolve_scope');
});

it("fail: mcp/resolve_scope/optional_deny_audit violated [PIPE-002]", function () {
    P::assertOptionalDenyAudit('mcp', 'resolve_scope');
});

it("fail: mcp/idempotency_lookup/no_run violated [PIPE-002]", function () {
    P::assertNoRun('mcp', 'idempotency_lookup');
});

it("fail: mcp/idempotency_lookup/no_domain_write violated [PIPE-002]", function () {
    P::assertNoDomainWrite('mcp', 'idempotency_lookup');
});

it("happy: mcp/idempotency_lookup/structured_error [PIPE-002]", function () {
    P::assertStructuredError('mcp', 'idempotency_lookup');
});

it("fail: mcp/idempotency_lookup/optional_deny_audit violated [PIPE-002]", function () {
    P::assertOptionalDenyAudit('mcp', 'idempotency_lookup');
});

it("fail: mcp/authorize/no_run violated [PIPE-002]", function () {
    P::assertNoRun('mcp', 'authorize');
});

it("fail: mcp/authorize/no_domain_write violated [PIPE-002]", function () {
    P::assertNoDomainWrite('mcp', 'authorize');
});

it("happy: mcp/authorize/structured_error [PIPE-002]", function () {
    P::assertStructuredError('mcp', 'authorize');
});

it("fail: mcp/authorize/optional_deny_audit violated [PIPE-002]", function () {
    P::assertOptionalDenyAudit('mcp', 'authorize');
});

it("fail: mcp/needs_approval/no_run violated [PIPE-002]", function () {
    P::assertNoRun('mcp', 'needs_approval');
});

it("fail: mcp/needs_approval/no_domain_write violated [PIPE-002]", function () {
    P::assertNoDomainWrite('mcp', 'needs_approval');
});

it("happy: mcp/needs_approval/structured_error [PIPE-002]", function () {
    P::assertStructuredError('mcp', 'needs_approval');
});

it("fail: mcp/needs_approval/optional_deny_audit violated [PIPE-002]", function () {
    P::assertOptionalDenyAudit('mcp', 'needs_approval');
});

it("fail: mcp/rate_limit/no_run violated [PIPE-002]", function () {
    P::assertNoRun('mcp', 'rate_limit');
});

it("fail: mcp/rate_limit/no_domain_write violated [PIPE-002]", function () {
    P::assertNoDomainWrite('mcp', 'rate_limit');
});

it("happy: mcp/rate_limit/structured_error [PIPE-002]", function () {
    P::assertStructuredError('mcp', 'rate_limit');
});

it("fail: mcp/rate_limit/optional_deny_audit violated [PIPE-002]", function () {
    P::assertOptionalDenyAudit('mcp', 'rate_limit');
});

it("fail: http/json_schema_validate/no_run violated [PIPE-002]", function () {
    P::assertNoRun('http', 'json_schema_validate');
});

it("fail: http/json_schema_validate/no_domain_write violated [PIPE-002]", function () {
    P::assertNoDomainWrite('http', 'json_schema_validate');
});

it("happy: http/json_schema_validate/structured_error [PIPE-002]", function () {
    P::assertStructuredError('http', 'json_schema_validate');
});

it("fail: http/json_schema_validate/optional_deny_audit violated [PIPE-002]", function () {
    P::assertOptionalDenyAudit('http', 'json_schema_validate');
});

it("fail: http/hydrate_dto/no_run violated [PIPE-002]", function () {
    P::assertNoRun('http', 'hydrate_dto');
});

it("fail: http/hydrate_dto/no_domain_write violated [PIPE-002]", function () {
    P::assertNoDomainWrite('http', 'hydrate_dto');
});

it("happy: http/hydrate_dto/structured_error [PIPE-002]", function () {
    P::assertStructuredError('http', 'hydrate_dto');
});

it("fail: http/hydrate_dto/optional_deny_audit violated [PIPE-002]", function () {
    P::assertOptionalDenyAudit('http', 'hydrate_dto');
});

it("fail: http/server_only_validate/no_run violated [PIPE-002]", function () {
    P::assertNoRun('http', 'server_only_validate');
});

it("fail: http/server_only_validate/no_domain_write violated [PIPE-002]", function () {
    P::assertNoDomainWrite('http', 'server_only_validate');
});

it("happy: http/server_only_validate/structured_error [PIPE-002]", function () {
    P::assertStructuredError('http', 'server_only_validate');
});

it("fail: http/server_only_validate/optional_deny_audit violated [PIPE-002]", function () {
    P::assertOptionalDenyAudit('http', 'server_only_validate');
});

it("fail: http/resolve_actor/no_run violated [PIPE-002]", function () {
    P::assertNoRun('http', 'resolve_actor');
});

it("fail: http/resolve_actor/no_domain_write violated [PIPE-002]", function () {
    P::assertNoDomainWrite('http', 'resolve_actor');
});

it("happy: http/resolve_actor/structured_error [PIPE-002]", function () {
    P::assertStructuredError('http', 'resolve_actor');
});

it("fail: http/resolve_actor/optional_deny_audit violated [PIPE-002]", function () {
    P::assertOptionalDenyAudit('http', 'resolve_actor');
});

it("fail: http/resolve_scope/no_run violated [PIPE-002]", function () {
    P::assertNoRun('http', 'resolve_scope');
});

it("fail: http/resolve_scope/no_domain_write violated [PIPE-002]", function () {
    P::assertNoDomainWrite('http', 'resolve_scope');
});

it("happy: http/resolve_scope/structured_error [PIPE-002]", function () {
    P::assertStructuredError('http', 'resolve_scope');
});

it("fail: http/resolve_scope/optional_deny_audit violated [PIPE-002]", function () {
    P::assertOptionalDenyAudit('http', 'resolve_scope');
});

it("fail: http/idempotency_lookup/no_run violated [PIPE-002]", function () {
    P::assertNoRun('http', 'idempotency_lookup');
});

it("fail: http/idempotency_lookup/no_domain_write violated [PIPE-002]", function () {
    P::assertNoDomainWrite('http', 'idempotency_lookup');
});

it("happy: http/idempotency_lookup/structured_error [PIPE-002]", function () {
    P::assertStructuredError('http', 'idempotency_lookup');
});

it("fail: http/idempotency_lookup/optional_deny_audit violated [PIPE-002]", function () {
    P::assertOptionalDenyAudit('http', 'idempotency_lookup');
});

it("fail: http/authorize/no_run violated [PIPE-002]", function () {
    P::assertNoRun('http', 'authorize');
});

it("fail: http/authorize/no_domain_write violated [PIPE-002]", function () {
    P::assertNoDomainWrite('http', 'authorize');
});

it("happy: http/authorize/structured_error [PIPE-002]", function () {
    P::assertStructuredError('http', 'authorize');
});

it("fail: http/authorize/optional_deny_audit violated [PIPE-002]", function () {
    P::assertOptionalDenyAudit('http', 'authorize');
});

it("fail: http/needs_approval/no_run violated [PIPE-002]", function () {
    P::assertNoRun('http', 'needs_approval');
});

it("fail: http/needs_approval/no_domain_write violated [PIPE-002]", function () {
    P::assertNoDomainWrite('http', 'needs_approval');
});

it("happy: http/needs_approval/structured_error [PIPE-002]", function () {
    P::assertStructuredError('http', 'needs_approval');
});

it("fail: http/needs_approval/optional_deny_audit violated [PIPE-002]", function () {
    P::assertOptionalDenyAudit('http', 'needs_approval');
});

it("fail: http/rate_limit/no_run violated [PIPE-002]", function () {
    P::assertNoRun('http', 'rate_limit');
});

it("fail: http/rate_limit/no_domain_write violated [PIPE-002]", function () {
    P::assertNoDomainWrite('http', 'rate_limit');
});

it("happy: http/rate_limit/structured_error [PIPE-002]", function () {
    P::assertStructuredError('http', 'rate_limit');
});

it("fail: http/rate_limit/optional_deny_audit violated [PIPE-002]", function () {
    P::assertOptionalDenyAudit('http', 'rate_limit');
});

it("fail: cli/json_schema_validate/no_run violated [PIPE-002]", function () {
    P::assertNoRun('cli', 'json_schema_validate');
});

it("fail: cli/json_schema_validate/no_domain_write violated [PIPE-002]", function () {
    P::assertNoDomainWrite('cli', 'json_schema_validate');
});

it("happy: cli/json_schema_validate/structured_error [PIPE-002]", function () {
    P::assertStructuredError('cli', 'json_schema_validate');
});

it("fail: cli/json_schema_validate/optional_deny_audit violated [PIPE-002]", function () {
    P::assertOptionalDenyAudit('cli', 'json_schema_validate');
});

it("fail: cli/hydrate_dto/no_run violated [PIPE-002]", function () {
    P::assertNoRun('cli', 'hydrate_dto');
});

it("fail: cli/hydrate_dto/no_domain_write violated [PIPE-002]", function () {
    P::assertNoDomainWrite('cli', 'hydrate_dto');
});

it("happy: cli/hydrate_dto/structured_error [PIPE-002]", function () {
    P::assertStructuredError('cli', 'hydrate_dto');
});

it("fail: cli/hydrate_dto/optional_deny_audit violated [PIPE-002]", function () {
    P::assertOptionalDenyAudit('cli', 'hydrate_dto');
});

it("fail: cli/server_only_validate/no_run violated [PIPE-002]", function () {
    P::assertNoRun('cli', 'server_only_validate');
});

it("fail: cli/server_only_validate/no_domain_write violated [PIPE-002]", function () {
    P::assertNoDomainWrite('cli', 'server_only_validate');
});

it("happy: cli/server_only_validate/structured_error [PIPE-002]", function () {
    P::assertStructuredError('cli', 'server_only_validate');
});

it("fail: cli/server_only_validate/optional_deny_audit violated [PIPE-002]", function () {
    P::assertOptionalDenyAudit('cli', 'server_only_validate');
});

it("fail: cli/resolve_actor/no_run violated [PIPE-002]", function () {
    P::assertNoRun('cli', 'resolve_actor');
});

it("fail: cli/resolve_actor/no_domain_write violated [PIPE-002]", function () {
    P::assertNoDomainWrite('cli', 'resolve_actor');
});

it("happy: cli/resolve_actor/structured_error [PIPE-002]", function () {
    P::assertStructuredError('cli', 'resolve_actor');
});

it("fail: cli/resolve_actor/optional_deny_audit violated [PIPE-002]", function () {
    P::assertOptionalDenyAudit('cli', 'resolve_actor');
});

it("fail: cli/resolve_scope/no_run violated [PIPE-002]", function () {
    P::assertNoRun('cli', 'resolve_scope');
});

it("fail: cli/resolve_scope/no_domain_write violated [PIPE-002]", function () {
    P::assertNoDomainWrite('cli', 'resolve_scope');
});

it("happy: cli/resolve_scope/structured_error [PIPE-002]", function () {
    P::assertStructuredError('cli', 'resolve_scope');
});

it("fail: cli/resolve_scope/optional_deny_audit violated [PIPE-002]", function () {
    P::assertOptionalDenyAudit('cli', 'resolve_scope');
});

it("fail: cli/idempotency_lookup/no_run violated [PIPE-002]", function () {
    P::assertNoRun('cli', 'idempotency_lookup');
});

it("fail: cli/idempotency_lookup/no_domain_write violated [PIPE-002]", function () {
    P::assertNoDomainWrite('cli', 'idempotency_lookup');
});

it("happy: cli/idempotency_lookup/structured_error [PIPE-002]", function () {
    P::assertStructuredError('cli', 'idempotency_lookup');
});

it("fail: cli/idempotency_lookup/optional_deny_audit violated [PIPE-002]", function () {
    P::assertOptionalDenyAudit('cli', 'idempotency_lookup');
});

it("fail: cli/authorize/no_run violated [PIPE-002]", function () {
    P::assertNoRun('cli', 'authorize');
});

it("fail: cli/authorize/no_domain_write violated [PIPE-002]", function () {
    P::assertNoDomainWrite('cli', 'authorize');
});

it("happy: cli/authorize/structured_error [PIPE-002]", function () {
    P::assertStructuredError('cli', 'authorize');
});

it("fail: cli/authorize/optional_deny_audit violated [PIPE-002]", function () {
    P::assertOptionalDenyAudit('cli', 'authorize');
});

it("fail: cli/needs_approval/no_run violated [PIPE-002]", function () {
    P::assertNoRun('cli', 'needs_approval');
});

it("fail: cli/needs_approval/no_domain_write violated [PIPE-002]", function () {
    P::assertNoDomainWrite('cli', 'needs_approval');
});

it("happy: cli/needs_approval/structured_error [PIPE-002]", function () {
    P::assertStructuredError('cli', 'needs_approval');
});

it("fail: cli/needs_approval/optional_deny_audit violated [PIPE-002]", function () {
    P::assertOptionalDenyAudit('cli', 'needs_approval');
});

it("fail: cli/rate_limit/no_run violated [PIPE-002]", function () {
    P::assertNoRun('cli', 'rate_limit');
});

it("fail: cli/rate_limit/no_domain_write violated [PIPE-002]", function () {
    P::assertNoDomainWrite('cli', 'rate_limit');
});

it("happy: cli/rate_limit/structured_error [PIPE-002]", function () {
    P::assertStructuredError('cli', 'rate_limit');
});

it("fail: cli/rate_limit/optional_deny_audit violated [PIPE-002]", function () {
    P::assertOptionalDenyAudit('cli', 'rate_limit');
});

it("fail: job/json_schema_validate/no_run violated [PIPE-002]", function () {
    P::assertNoRun('job', 'json_schema_validate');
});

it("fail: job/json_schema_validate/no_domain_write violated [PIPE-002]", function () {
    P::assertNoDomainWrite('job', 'json_schema_validate');
});

it("happy: job/json_schema_validate/structured_error [PIPE-002]", function () {
    P::assertStructuredError('job', 'json_schema_validate');
});

it("fail: job/json_schema_validate/optional_deny_audit violated [PIPE-002]", function () {
    P::assertOptionalDenyAudit('job', 'json_schema_validate');
});

it("fail: job/hydrate_dto/no_run violated [PIPE-002]", function () {
    P::assertNoRun('job', 'hydrate_dto');
});

it("fail: job/hydrate_dto/no_domain_write violated [PIPE-002]", function () {
    P::assertNoDomainWrite('job', 'hydrate_dto');
});

it("happy: job/hydrate_dto/structured_error [PIPE-002]", function () {
    P::assertStructuredError('job', 'hydrate_dto');
});

it("fail: job/hydrate_dto/optional_deny_audit violated [PIPE-002]", function () {
    P::assertOptionalDenyAudit('job', 'hydrate_dto');
});

it("fail: job/server_only_validate/no_run violated [PIPE-002]", function () {
    P::assertNoRun('job', 'server_only_validate');
});

it("fail: job/server_only_validate/no_domain_write violated [PIPE-002]", function () {
    P::assertNoDomainWrite('job', 'server_only_validate');
});

it("happy: job/server_only_validate/structured_error [PIPE-002]", function () {
    P::assertStructuredError('job', 'server_only_validate');
});

it("fail: job/server_only_validate/optional_deny_audit violated [PIPE-002]", function () {
    P::assertOptionalDenyAudit('job', 'server_only_validate');
});

it("fail: job/resolve_actor/no_run violated [PIPE-002]", function () {
    P::assertNoRun('job', 'resolve_actor');
});

it("fail: job/resolve_actor/no_domain_write violated [PIPE-002]", function () {
    P::assertNoDomainWrite('job', 'resolve_actor');
});

it("happy: job/resolve_actor/structured_error [PIPE-002]", function () {
    P::assertStructuredError('job', 'resolve_actor');
});

it("fail: job/resolve_actor/optional_deny_audit violated [PIPE-002]", function () {
    P::assertOptionalDenyAudit('job', 'resolve_actor');
});

it("fail: job/resolve_scope/no_run violated [PIPE-002]", function () {
    P::assertNoRun('job', 'resolve_scope');
});

it("fail: job/resolve_scope/no_domain_write violated [PIPE-002]", function () {
    P::assertNoDomainWrite('job', 'resolve_scope');
});

it("happy: job/resolve_scope/structured_error [PIPE-002]", function () {
    P::assertStructuredError('job', 'resolve_scope');
});

it("fail: job/resolve_scope/optional_deny_audit violated [PIPE-002]", function () {
    P::assertOptionalDenyAudit('job', 'resolve_scope');
});

it("fail: job/idempotency_lookup/no_run violated [PIPE-002]", function () {
    P::assertNoRun('job', 'idempotency_lookup');
});

it("fail: job/idempotency_lookup/no_domain_write violated [PIPE-002]", function () {
    P::assertNoDomainWrite('job', 'idempotency_lookup');
});

it("happy: job/idempotency_lookup/structured_error [PIPE-002]", function () {
    P::assertStructuredError('job', 'idempotency_lookup');
});

it("fail: job/idempotency_lookup/optional_deny_audit violated [PIPE-002]", function () {
    P::assertOptionalDenyAudit('job', 'idempotency_lookup');
});

it("fail: job/authorize/no_run violated [PIPE-002]", function () {
    P::assertNoRun('job', 'authorize');
});

it("fail: job/authorize/no_domain_write violated [PIPE-002]", function () {
    P::assertNoDomainWrite('job', 'authorize');
});

it("happy: job/authorize/structured_error [PIPE-002]", function () {
    P::assertStructuredError('job', 'authorize');
});

it("fail: job/authorize/optional_deny_audit violated [PIPE-002]", function () {
    P::assertOptionalDenyAudit('job', 'authorize');
});

it("fail: job/needs_approval/no_run violated [PIPE-002]", function () {
    P::assertNoRun('job', 'needs_approval');
});

it("fail: job/needs_approval/no_domain_write violated [PIPE-002]", function () {
    P::assertNoDomainWrite('job', 'needs_approval');
});

it("happy: job/needs_approval/structured_error [PIPE-002]", function () {
    P::assertStructuredError('job', 'needs_approval');
});

it("fail: job/needs_approval/optional_deny_audit violated [PIPE-002]", function () {
    P::assertOptionalDenyAudit('job', 'needs_approval');
});

it("fail: job/rate_limit/no_run violated [PIPE-002]", function () {
    P::assertNoRun('job', 'rate_limit');
});

it("fail: job/rate_limit/no_domain_write violated [PIPE-002]", function () {
    P::assertNoDomainWrite('job', 'rate_limit');
});

it("happy: job/rate_limit/structured_error [PIPE-002]", function () {
    P::assertStructuredError('job', 'rate_limit');
});

it("fail: job/rate_limit/optional_deny_audit violated [PIPE-002]", function () {
    P::assertOptionalDenyAudit('job', 'rate_limit');
});

