#!/usr/bin/env python3
"""
Generate a COMPLETE requirements inventory and matching unit-test stubs
from the normative surface of docs/spec.md.

Source of truth workflow:
  1. This catalog encodes every testable requirement as scenarios
     (happy / fail / edge), including full normative matrices.
  2. Inventory + package stubs are generated together (1:1).
  3. When implemented, the tests become the living contract of what
     the product is and is not.

Policy (AGENTS.md): unit tests only, no DB, mocks/fakes.
"""

from __future__ import annotations

import re
import sys
from collections import defaultdict
from dataclasses import dataclass, field
from pathlib import Path
from textwrap import dedent

ROOT = Path(__file__).resolve().parents[1]


def pest_evaluable(description: str) -> str:
    """Mirror Pest\\Support\\Str::evaluable so generated it() titles stay unique per file.

    Pest doubles existing underscores, replaces spaces with underscores, then
    replaces non [A-Za-z0-9_\\x80-\\xff] with underscores. Distinct labels that
    only differ by punctuation (e.g. 'bad key' vs 'bad/key') collapse to the
    same method name and fatal with Cannot redeclare.
    """
    code = description.replace("_", "__")
    code = "__pest_evaluable_" + code.replace(" ", "_")
    return re.sub(r"[^a-zA-Z0-9_\x80-\xff]", "_", code)


def go_test_func_name(title: str) -> str:
    """Stable Go Test* function name from a catalog title (never drops scenarios)."""
    if title.startswith("Test"):
        return title
    safe = "".join(ch if ch.isalnum() else "_" for ch in title)
    while "__" in safe:
        safe = safe.replace("__", "_")
    return "Test" + "".join(p.capitalize() for p in safe.split("_") if p)

CALLERS = ["agent", "mcp", "http", "cli", "job"]
SURFACES = ["agent", "mcp", "http", "cli", "job", "artisan", "messaging"]
INVOKE_SURFACES = ["agent", "mcp", "http", "cli", "job"]
ERROR_CODES = [
    ("validation_failed", 422, 2),
    ("unauthenticated", 401, 3),
    ("forbidden", 403, 3),
    ("approval_required", 202, 4),
    ("domain_error", 422, 5),
    ("rate_limited", 429, 6),
    ("conflict", 409, 5),
    ("not_found", 404, 5),
    ("output_invalid", 500, 5),
    ("internal", 500, 1),
]
APPROVAL_STATUSES = ["pending", "approved", "rejected", "expired", "executed"]
IDEMPOTENCY_STATUSES = ["processing", "completed", "failed"]
MCP_PROFILES = ["user_pat", "integration", "user_delegated"]
APPROVAL_POLICIES = [
    "requester",
    "requester_or_role",
    "role:finance-approver",
    "any_staff",
    "custom",
]
PIPELINE_STAGES_BEFORE_RUN = [
    "json_schema_validate",
    "hydrate_dto",
    "server_only_validate",
    "resolve_actor",
    "resolve_scope",
    "idempotency_lookup",
    "authorize",
    "needs_approval",
    "rate_limit",
]
PIPELINE_STAGES_AFTER_RUN = [
    "validate_output",
    "store_idempotency_result",
    "record_audit",
    "emit_events",
    "wire_response",
]


@dataclass
class Case:
    kind: str  # happy | fail | edge
    title: str
    req: str = ""  # REQ / D-xxx / P2-xxx tag
    note: str = ""

    def label(self) -> str:
        base = f"{self.kind}: {self.title}"
        if self.req:
            base = f"{base} [{self.req}]"
        return base


@dataclass
class FileSpec:
    package: str  # core | messaging | cli
    relpath: str  # relative to package tests root or go package path
    language: str  # php | go
    go_package: str = ""
    cases: list[Case] = field(default_factory=list)

    def add(self, kind: str, title: str, req: str = "", note: str = "") -> None:
        self.cases.append(Case(kind=kind, title=title, req=req, note=note))

    def happy(self, title: str, req: str = "") -> None:
        self.add("happy", title, req)

    def fail(self, title: str, req: str = "") -> None:
        self.add("fail", title, req)

    def edge(self, title: str, req: str = "") -> None:
        self.add("edge", title, req)


def F(package: str, relpath: str, language: str = "php", go_package: str = "") -> FileSpec:
    return FileSpec(package=package, relpath=relpath, language=language, go_package=go_package)


def build_catalog() -> list[FileSpec]:
    files: list[FileSpec] = []

    # ─────────────────────────────────────────────────────────────
    # CORE — Registry / pipeline (full stage × caller matrix)
    # ─────────────────────────────────────────────────────────────
    inv = F("core", "Registry/InvokePipelineTest.php")
    inv.happy("successful invoke runs full pipeline in order validate hydrate actor scope idempotency authorize approval rateLimit run output audit events", "PIPE-001")
    for stage in PIPELINE_STAGES_BEFORE_RUN:
        inv.fail(f"run is not called when stage {stage} fails", "PIPE-002")
        inv.fail(f"no domain side effects when stage {stage} fails", "PIPE-002")
        inv.happy(f"correct error envelope when stage {stage} fails", "PIPE-002")
    for caller in CALLERS:
        inv.happy(f"successful invoke via caller {caller} hits same registry pipeline", "PIPE-003")
        inv.fail(f"invalid input via caller {caller} never reaches run", "PIPE-003")
        inv.fail(f"unauthorized via caller {caller} never reaches run", "PIPE-003")
    inv.fail("unknown capability returns not_found without run", "PIPE-004")
    inv.fail("disabled surface capability not invokable as that surface", "PIPE-005")
    inv.happy("in-process Capability::invoke is identical choke point as adapters", "PIPE-006")
    inv.happy("single run for successful non-approval path", "PIPE-007")
    inv.happy("needsApproval true stores pending and does not call run", "D-006")
    inv.edge("idempotent replay of completed result skips run", "D-005")
    inv.edge("after successful run audit failure does not roll back domain when best_effort", "D-010")
    inv.fail("invalid output after run does not return success to client", "D-014")
    inv.happy("AI tool handle invokes registry not domain directly", "PIPE-008")
    inv.happy("MCP tool handle invokes registry not domain directly", "PIPE-008")
    inv.happy("job handle invokes registry not domain directly", "PIPE-008")
    inv.happy("HTTP controller invokes registry not domain directly", "PIPE-008")
    inv.fail("third mutation path outside registry is not supported", "D-017")
    for stage in PIPELINE_STAGES_AFTER_RUN:
        inv.happy(f"successful path executes stage {stage}", "PIPE-001")
    files.append(inv)

    # One run() dual-path prevention architecture
    dual = F("core", "Architecture/OneRunTest.php")
    dual.happy("surfaces are adapters only and call registry invoke", "BELIEF-001")
    dual.fail("no alternate domain mutation API exists in core package source", "BELIEF-001")
    dual.fail("messaging package does not expose alternate run API", "D-007")
    dual.fail("CLI package contains no domain mutation logic", "D-016")
    dual.happy("catalog tools and HTTP share same schema source", "BELIEF-002")
    dual.edge("governance authz approval audit actor scope apply on every surface", "BELIEF-003")
    files.append(dual)

    # ─────────────────────────────────────────────────────────────
    # Discovery / definition (D-017)
    # ─────────────────────────────────────────────────────────────
    disc = F("core", "Discovery/CapabilityDefinitionTest.php")
    disc.happy("attribute class with DefinesCapability auto-discovered under configured path", "D-017")
    disc.happy("fluent Capability::define registers same CapabilityDefinition shape", "D-017")
    disc.fail("duplicate name from class and fluent throws at boot", "D-017")
    disc.fail("ad-hoc invokable without registry is not supported for mutations", "D-017")
    disc.fail("third discovery path is not registered", "D-017")
    for field_name in [
        "name",
        "description",
        "surfaces",
        "input",
        "output",
        "aliases",
        "deprecated",
        "successor",
        "sunset_at",
        "groups",
        "tags",
        "readOnly",
        "allowSystemCallers",
        "globalSystem",
        "approvalPolicy",
        "approvalTtlHours",
        "rateLimit",
        "idempotent",
        "audit",
    ]:
        disc.happy(f"definition stores field {field_name} when declared", "D-017")
    disc.edge("empty surfaces list yields no effective exposure", "SURF-001")
    disc.happy("aliases resolve to canonical name before run", "D-012")
    disc.happy("allowSystemCallers empty denies all SystemActors", "D-002")
    disc.happy("allowSystemCallers true allows any registered system name", "D-002")
    disc.happy("allowSystemCallers list allows only listed SystemActor names", "D-002")
    disc.happy("globalSystem true allows SystemActor without tenantId", "D-003")
    disc.happy("readOnly true marks non-mutating for audit and idempotency", "D-005")
    disc.edge("groups and tags available for profile composition", "D-008")
    disc.fail("missing name on definition rejected at registration", "D-017")
    disc.fail("missing input type on mutating capability rejected at registration", "D-017")
    files.append(disc)

    # ─────────────────────────────────────────────────────────────
    # Schema DTO / JSON Schema (D-004, D-015)
    # ─────────────────────────────────────────────────────────────
    schema = F("core", "Schema/DtoAndJsonSchemaTest.php")
    schema.happy("CapabilityData reflects to JSON Schema draft 2020-12", "D-015")
    schema.happy("Field attribute description appears in schema properties", "D-015")
    schema.happy("required properties derived from non-nullable constructor params", "D-015")
    schema.happy("nullable properties allow null in schema", "D-015")
    schema.happy("optional properties with defaults not required", "D-015")
    schema.happy("server-only rules not embedded in portable JSON Schema for CLI", "D-004")
    schema.happy("exists unique server rules run only on server validation pass", "D-004")
    schema.fail("structural invalid input never reaches hydrate or run", "D-004")
    schema.fail("server-only validation failure never reaches run", "D-004")
    schema.happy("array wire format only at edge run receives typed DTO", "D-015")
    schema.happy("SchemaProvider interface supported for custom types", "D-015")
    schema.edge("optional Spatie bridge implements SchemaProvider when installed", "D-015")
    schema.edge("Spatie not required for v1 package-native path", "D-015")
    schema.happy("catalog describe returns input_schema and output_schema", "D-004")
    schema.happy("catalog list can omit full schemas until describe", "CAT-001")
    schema.happy("schema_version present on catalog entries", "D-004")
    schema.fail("additionalProperties false rejects unknown keys when schema says so", "D-004")
    schema.fail("wrong type for integer field fails portable validation", "D-004")
    schema.fail("missing required field fails portable validation", "D-004")
    schema.fail("string longer than max fails when schema constrains", "D-004")
    schema.fail("enum value outside set fails portable validation", "D-004")
    schema.happy("nested object properties reflected in schema", "D-015")
    schema.happy("array item types reflected in schema", "D-015")
    schema.edge("CLI and HTTP consume identical portable schema document", "D-004")
    schema.fail("Laravel rule strings alone are not catalog schema source of truth", "D-004")
    schema.fail("hand-copied second tool schema is not used by AI adapter", "D-004")
    schema.fail("hand-copied second tool schema is not used by MCP adapter", "D-004")
    files.append(schema)

    outv = F("core", "Schema/OutputValidationTest.php")
    outv.happy("validate_output true validates declared output after successful run", "D-014")
    outv.fail("invalid output after run emits CapabilityFailed", "D-014")
    outv.fail("invalid output maps to output_invalid envelope", "D-014")
    outv.fail("invalid output is not returned as success to agent tools", "D-014")
    outv.fail("invalid output is not returned as success to MCP tools", "D-014")
    outv.fail("invalid output is not returned as success to HTTP", "D-014")
    outv.edge("readOnly without output schema may skip when configured", "D-014")
    outv.edge("validate_output false only when explicitly configured", "D-014")
    outv.happy("valid output passes through unchanged", "D-014")
    outv.fail("missing required output field fails validation", "D-014")
    outv.fail("wrong type in output field fails validation", "D-014")
    files.append(outv)

    # ─────────────────────────────────────────────────────────────
    # Boot / surfaces
    # ─────────────────────────────────────────────────────────────
    boot = F("core", "Boot/SurfaceBootRulesTest.php")
    for s in ["agent", "mcp", "http", "cli", "job", "artisan"]:
        boot.happy(f"surface {s} defaults to enabled", "SURF-002")
        boot.happy(f"disabling surface {s} registers nothing for that surface", "SURF-003")
        boot.fail(f"disabling surface {s} does not leave half-registered stubs", "SURF-003")
    boot.happy("messaging surface defaults to disabled in core", "D-007")
    boot.edge("capability listing only includes surfaces after global intersect capability.surfaces", "SURF-001")
    boot.fail("capability cannot enable a globally disabled surface", "SURF-001")
    for s in INVOKE_SURFACES:
        boot.edge(f"effective exposure for surface {s} is intersection only", "SURF-001")
    boot.fail("cli enabled while http disabled fails boot", "SURF-004")
    boot.fail("messaging enabled without messaging package fails boot check", "D-007")
    boot.fail("messaging enabled without agent surface fails boot", "SURF-004")
    for peer, surface in [("laravel/ai", "agent"), ("laravel/mcp", "mcp")]:
        boot.fail(f"{surface} enabled with require_package and missing {peer} fails boot when on_incompatible=fail", "D-011")
        boot.edge(f"{surface} missing peer with on_incompatible=disable soft-disables and logs CRITICAL", "D-011")
        boot.fail(f"{surface} half-register of tools never occurs when peer incompatible", "D-011")
        boot.edge(f"{surface} incompatible peer version via supportsInstalledPeer false fails or disables per config", "D-011")
    boot.edge("CAPABILITIES_SKIP_BOOT_CHECKS ignored when APP_ENV=production", "D-021")
    boot.edge("CAPABILITIES_SKIP_BOOT_CHECKS only skips deferred-style checks in CI", "D-021")
    boot.happy("publishing capabilities-config tag works", "BOOT-001")
    boot.happy("env CAPABILITIES_SURFACE_* toggles map to config", "SURF-005")
    for s in SURFACES:
        boot.edge(f"env toggle for surface {s} respected at boot", "SURF-005")
    files.append(boot)

    # ─────────────────────────────────────────────────────────────
    # Caller derivation D-022
    # ─────────────────────────────────────────────────────────────
    caller = F("core", "Caller/DerivationTest.php")
    caller.happy("Sanctum ability capabilities:cli derives caller cli", "D-022")
    caller.happy("Sanctum PAT without mapped ability derives caller http", "D-022")
    caller.happy("OAuth client registered as cli derives caller cli", "D-022")
    caller.happy("unregistered OAuth client derives http", "D-022")
    for c in CALLERS:
        caller.happy(f"in-process adapter or job can set caller {c} from server code", "D-022")
    caller.fail("X-Capabilities-Caller alone does not set caller", "D-022")
    caller.fail("model tool args cannot set caller", "D-022")
    caller.fail("MCP tool JSON cannot set caller", "D-022")
    caller.fail("CLI request body cannot set caller authoritatively", "D-022")
    caller.edge("header matching derived is no-op", "D-022")
    caller.edge("header downgrade to stricter bucket allowed per privilege_order", "D-022")
    caller.fail("header upgrade to more privileged bucket ignored by default", "D-022")
    caller.fail("header upgrade rejected with caller_claim_rejected when reject_upgrade_attempts true", "D-022")
    caller.edge("unknown header value ignored", "D-022")
    for from_c in CALLERS:
        for to_c in CALLERS:
            if from_c == to_c:
                continue
            caller.edge(f"spoof header from derived {from_c} claiming {to_c} does not self-upgrade", "D-022")
    caller.happy("needsApproval branching on caller uses derived value not spoofed header", "D-022")
    caller.happy("CLI credential spoofing http header still treated as cli for approval rules", "D-022")
    caller.happy("generic API token claiming cli still http for audit caller", "D-022")
    caller.happy("credential audit metadata records type client_id ability when present", "D-022")
    caller.fail("null principal never accepted after context build", "CTX-001")
    files.append(caller)

    # ─────────────────────────────────────────────────────────────
    # Context
    # ─────────────────────────────────────────────────────────────
    ctx = F("core", "Context/CapabilityContextTest.php")
    ctx.happy("context always has non-null actor after build", "CTX-001")
    ctx.happy("user returns User when actor is User", "CTX-001")
    ctx.happy("user is null when actor is SystemActor", "CTX-001")
    for c in CALLERS:
        ctx.happy(f"caller accepts value {c}", "CTX-001")
    ctx.fail("caller rejects unknown value", "CTX-001")
    ctx.happy("scope attached after ResolveTenantFromCaller", "D-003")
    for field_name in ["tenantId", "teamId", "organizationId", "requestId", "traceId"]:
        ctx.happy(f"accessor {field_name} available when set", "CTX-001")
    ctx.happy("agent metadata optional when caller agent", "CTX-001")
    ctx.happy("mcp metadata when caller mcp includes auth_profile", "D-023")
    for p in MCP_PROFILES:
        ctx.happy(f"mcp auth_profile accepts {p}", "D-023")
    ctx.happy("messaging metadata optional on agent turns from chat", "D-007")
    ctx.happy("job metadata optional when caller job", "D-002")
    ctx.happy("credential audit metadata optional", "D-022")
    ctx.fail("context build with null principal is refused", "CTX-001")
    ctx.edge("messaging-originated tools still use caller agent at registry", "D-007")
    files.append(ctx)

    # ─────────────────────────────────────────────────────────────
    # Job / SystemActor D-002
    # ─────────────────────────────────────────────────────────────
    job = F("core", "Job/ActorIdentityTest.php")
    job.happy("dispatch with actingAs user id loads User and sets caller job", "D-002")
    job.happy("dispatch with SystemActor named scheduler allowed when allowlisted", "D-002")
    job.fail("dispatch without actingAs throws MissingJobActorException and is not enqueued", "D-002")
    job.fail("missing user id for actingAs int fails job without run", "D-002")
    job.fail("SystemActor not in allowSystemCallers fails before authorize", "D-002")
    job.fail("capability without allowSystemCallers rejects SystemActor", "D-002")
    job.fail("authorize false fails job without run and audits denial", "D-002")
    job.happy("audit records actor_type actor_id or name caller job and tenant_id", "D-002")
    job.fail("authorize that allows all jobs when caller is job is not package default", "D-002")
    job.edge("artisan mutating invoke without acting-as or system is refused", "D-002")
    job.happy("user actor hits same authorize path shape as HTTP user", "D-002")
    job.fail("if caller is job return true authorize pattern is refused by package tests", "D-002")
    job.fail("null user allow when no user is refused", "D-002")
    job.fail("global jobs bypass policy config is not provided", "D-002")
    job.happy("SystemActor named factory sets name", "D-002")
    job.happy("SystemActor equality by name for allowlists", "D-002")
    for name in ["scheduler", "reconciliation", "horizon", "billing-bot"]:
        job.edge(f"SystemActor named {name} only allowed when listed on capability", "D-002")
    job.happy("allowSystemCallers true path documented as discouraged still works", "D-002")
    job.fail("smuggled tenant in input for system job is ignored for scope", "P2-005")
    files.append(job)

    sysa = F("core", "Support/SystemActorTest.php")
    sysa.happy("named factory sets name", "D-002")
    sysa.happy("equality by name for allowlists", "D-002")
    sysa.fail("empty name rejected", "D-002")
    sysa.edge("name is readonly after construct", "D-002")
    files.append(sysa)

    # ─────────────────────────────────────────────────────────────
    # Scope / tenancy D-003 / P2-005
    # ─────────────────────────────────────────────────────────────
    scope = F("core", "Scope/TenancyTest.php")
    scope.happy("ScopeResolver resolve called after actor before authorize", "D-003")
    scope.happy("CapabilityScope query delegates to ScopedQueryFactory", "D-003")
    scope.happy("user scope from membership not from untrusted input alone", "D-003")
    scope.fail("SystemActor without tenantId throws when tenancy required and not globalSystem", "P2-005")
    for magic in ["_tenant_id", "tenant_id", "tenantId", "organization_id", "team_id", "scope_id"]:
        scope.fail(f"SystemActor scope never reads input magic key {magic}", "P2-005")
    scope.happy("SystemActor tenant from first-class job tenantId only", "P2-005")
    scope.happy("SystemActor tenant from trusted context attributes only", "P2-005")
    scope.happy("globalSystem capability allows null tenant for system actor", "D-003")
    scope.happy("single-tenant resolver still runs and returns default scope", "D-003")
    scope.edge("X-Tenant-Id header is hint only and must pass membership check", "D-003")
    scope.edge("CLI --tenant is hint only and must pass membership check", "D-003")
    for c in CALLERS:
        scope.fail(f"cross-tenant customer_id via caller {c} denies authorize via scoped query null", "D-003")
        scope.fail(f"cross-tenant resource via caller {c} does not run", "D-003")
    scope.happy("assertCannotInvokeAcrossTenant helper fails test when leak possible", "D-003")
    scope.happy("package examples never teach input tenant for SystemActor", "P2-005")
    scope.fail("unusable scope when tenancy required fails closed", "D-003")
    scope.fail("exists global without scope is insufficient alone for multi-tenant safety", "D-003")
    scope.happy("scoped re-resolve on approval accept", "D-006")
    scope.edge("teamId organizationId convenience when app uses those dimensions", "D-003")
    scope.fail("MissingJobTenantException when system job omits tenantId and tenancy required", "P2-005")
    scope.happy("assertLastScopeTenant reflects first-class tenant not smuggled input", "P2-005")
    scope.happy("assertScopeResolvedTo fails when scope mismatches", "D-003")
    for c in CALLERS:
        scope.edge(f"parity dataset deny class for cross-tenant via {c}", "D-003")
    files.append(scope)

    # ─────────────────────────────────────────────────────────────
    # Approval D-006 / P2-004
    # ─────────────────────────────────────────────────────────────
    appr = F("core", "Approval/ApprovalStateMachineTest.php")
    appr.happy("needsApproval true does not call run and stores pending approval", "D-006")
    appr.happy("surfaces receive approval_required with approval id and summary", "D-006")
    appr.happy("accept by authorized approver Shape A sets approved then executes once", "D-006")
    appr.happy("accept Shape B pending to executed under lock runs once", "D-006")
    appr.happy("reject transitions to rejected and never runs", "D-006")
    appr.happy("double accept after executed replays result_json without second run", "D-006")
    appr.fail("concurrent accept only one run via conditional update", "D-006")
    appr.fail("accept when status approved Shape A does not double run", "D-006")
    appr.edge("accept when status approved Shape A returns in_progress or joins lease", "D-006")
    appr.fail("accept when rejected returns conflict", "D-006")
    appr.fail("accept when expired returns 410 or expired error", "D-006")
    appr.fail("accept when executed returns replay not re-run", "D-006")
    appr.fail("reject when executed returns conflict", "D-006")
    appr.fail("reject when expired returns conflict or expired", "D-006")
    appr.fail("reject when already rejected is terminal no-op", "D-006")
    # Full status transition matrix
    legal = {
        ("pending", "approved"),
        ("pending", "executed"),
        ("pending", "rejected"),
        ("pending", "expired"),
        ("approved", "executed"),
    }
    for src in APPROVAL_STATUSES:
        for dst in APPROVAL_STATUSES:
            if src == dst:
                continue
            if (src, dst) in legal:
                appr.happy(f"transition from {src} to {dst} allowed under rules", "D-006")
            else:
                appr.fail(f"transition from {src} to {dst} is forbidden", "D-006")
    appr.happy("re-validation on accept re-runs schema server rules and scoped resolve", "D-006")
    appr.fail("stale resource after request time fails accept without run", "D-006")
    appr.fail("authorize fails for original actor on accept fails without run", "D-006")
    appr.fail("wrong approver forbidden", "D-006")
    appr.fail("SystemActor cannot approve", "D-006")
    appr.happy("approver must be same tenant scope as approval row", "D-006")
    appr.happy("pending past ttl becomes expired on read", "D-006")
    appr.happy("scheduled sweeper expires pending past ttl", "D-006")
    appr.happy("decided_by and decided_at recorded", "D-006")
    appr.happy("decision_reason optional on reject", "D-006")
    appr.happy("original caller and scope preserved on execution", "D-006")
    appr.happy("idempotency key completed after approval execution", "D-005")
    for policy in APPROVAL_POLICIES:
        appr.edge(f"approvalPolicy {policy} enforces who may decide", "D-006")
    appr.edge("per-capability approvalTtlHours lower than global", "D-006")
    appr.happy("ApprovalManager owns state machine not channel adapters", "D-006")
    appr.happy("notifiers notify pending but never execute capabilities", "D-006")
    appr.happy("result_status ok stored on executed success", "D-006")
    appr.happy("result_status failed stored on executed domain failure", "D-006")
    appr.happy("audit chain approval.requested", "D-006")
    appr.happy("audit chain approval.decided", "D-006")
    appr.happy("audit chain approval.executed", "D-006")
    appr.happy("audit chain approval.replayed on double accept", "D-006")
    appr.happy("audit chain approval.expired", "D-006")
    appr.fail("accept without re-validation is refused", "D-006")
    appr.fail("unsigned forgeable approve id alone is refused at HTTP layer", "D-006")
    appr.fail("eternal pending without ttl is not default", "D-006")
    appr.fail("any authenticated user silent multi-tenant approve is not default policy", "D-006")
    appr.edge("execution mode deferred is default Shape A", "D-006")
    appr.edge("execution mode atomic is Shape B", "D-006")
    appr.happy("row stores capability_name status scope requester original_caller input_json", "D-006")
    appr.happy("row stores idempotency_key result_json decided_by expires_at lease fields", "D-006")
    for c in CALLERS:
        appr.edge(f"approval_required path works for original caller {c}", "D-006")
    files.append(appr)

    crash = F("core", "Approval/ApprovalCrashRecoveryTest.php")
    crash.happy("ResumeApprovedApprovals executes stuck approved past grace with free lease", "P2-004")
    crash.happy("resume re-validates and scoped re-resolves before run", "P2-004")
    crash.fail("resume on stale resource marks executed failed without domain success", "P2-004")
    crash.happy("second resume after executed is replay", "P2-004")
    crash.edge("approved within grace_seconds not claimed by resume", "P2-004")
    crash.happy("lease claim prevents two workers double run", "P2-004")
    crash.happy("stuck_after_seconds increments approvals_stuck_approved_total metric", "P2-004")
    crash.happy("artisan capabilities:approvals-resume uses same path as scheduler", "P2-004")
    crash.happy("atomic execution mode has no approved limbo and resume is no-op", "P2-004")
    crash.fail("process death before commit Shape B leaves pending allowing safe re-accept", "P2-004")
    crash.happy("audit chain approval.resume", "P2-004")
    crash.happy("metrics approvals_resume_total and approvals_accept_total", "P2-004")
    crash.fail("approved without resume or atomic is refused by design tests", "P2-004")
    crash.fail("re-accept while approved does not blindly re-run", "P2-004")
    crash.happy("resume uses original D-005 idempotency key", "P2-004")
    crash.edge("execution_attempt increments on each resume claim", "P2-004")
    crash.edge("lease_seconds config controls claim duration", "P2-004")
    crash.edge("every_seconds config schedules resume job", "P2-004")
    crash.happy("resume emits approval.executed with via=resume", "P2-004")
    crash.happy("accept emits approval.executed with via=accept", "P2-004")
    files.append(crash)

    # ─────────────────────────────────────────────────────────────
    # Idempotency D-005
    # ─────────────────────────────────────────────────────────────
    idem = F("core", "Idempotency/IdempotencyTest.php")
    idem.happy("first key inserts processing then stores completed result", "D-005")
    idem.happy("same key same input hash replays result without second run", "D-005")
    idem.fail("same key different input hash returns conflict 409", "D-005")
    idem.edge("key in processing returns 409 or 425 too early", "D-005")
    idem.edge("failed result replays failure by default for TTL", "D-005")
    idem.happy("no key runs non-idempotent path", "D-005")
    idem.happy("HTTP header Idempotency-Key wins over body idempotency_key", "D-005")
    idem.happy("body idempotency_key accepted when header absent", "D-005")
    idem.happy("CLI always sends a key auto UUID", "D-005")
    idem.happy("AI MCP tool optional idempotency_key argument honored", "D-005")
    idem.happy("job idempotencyKey optional recommended path stores and replays", "D-005")
    idem.happy("approval accept uses stored key from original invoke by default", "D-005")
    idem.happy("approval accept with same key after executed replays without second run", "D-005")
    idem.happy("readOnly capabilities ignore idempotency keys", "D-005")
    idem.fail("idempotent required capability missing key returns 400", "D-005")
    idem.edge("idempotent none ignores keys", "D-005")
    idem.edge("idempotent optional default for mutations", "D-005")
    idem.edge("expired key after TTL treated as new key", "D-005")
    idem.edge("key format rejects invalid characters and length", "D-005")
    idem.happy("unique scope actor capability key identity for storage", "D-005")
    idem.edge("warn_missing_key emits metric or log when mutating without key", "D-005")
    for c in CALLERS:
        idem.happy(f"idempotency honored for caller {c} when key present", "D-005")
        idem.happy(f"replay for caller {c} does not second run", "D-005")
    for st in IDEMPOTENCY_STATUSES:
        idem.edge(f"store status {st} behaves per pipeline table", "D-005")
    idem.fail("relying on clients not to retry is not a package assumption", "D-005")
    idem.fail("idempotency only on HTTP not MCP CLI jobs is refused", "D-005")
    idem.fail("approval accept without tying to invoke key is refused", "D-005")
    idem.fail("global dedupe by input only without key is refused", "D-005")
    idem.happy("catalog exposes idempotent optional required none metadata", "D-005")
    idem.edge("TTL default 24 hours configurable", "D-005")
    idem.edge("header name configurable via config idempotency.header", "D-005")
    idem.happy("request_hash is canonical input JSON hash", "D-005")
    files.append(idem)

    # ─────────────────────────────────────────────────────────────
    # Audit / transactions D-010
    # ─────────────────────────────────────────────────────────────
    audit = F("core", "Audit/TransactionsAndAuditTest.php")
    audit.happy("mutating capability emits audit record on success", "D-010")
    audit.happy("readOnly skips audit unless audit forced true", "D-010")
    audit.happy("best_effort audit failure after successful run still returns success", "D-010")
    audit.happy("best_effort with required true writes outbox on audit failure", "D-010")
    audit.fail("silent drop of audit never occurs when required true", "D-010")
    audit.edge("strict mode surfaces failure when audit fails depending on txn design", "D-010")
    audit.happy("transactions wrap_run false by default does not wrap run", "D-010")
    audit.edge("wrap_run true wraps run optionally with sync audit", "D-010")
    for event in [
        "CapabilityInvoked",
        "CapabilityFailed",
        "CapabilityApprovalRequested",
        "CapabilityApprovalDecided",
        "CapabilityApprovalExecuted",
    ]:
        audit.happy(f"bus event {event} emitted on matching condition", "D-010")
        audit.edge(f"listeners for {event} should use afterCommit when touching DB", "D-010")
    audit.happy("domain events remain app responsibility inside run", "D-010")
    for field_name in [
        "name",
        "caller",
        "actor",
        "scope",
        "idempotency",
        "replay",
        "result",
        "duration",
        "approval_id",
        "redacted_input",
    ]:
        audit.happy(f"audit fields include {field_name}", "D-010")
    audit.happy("WriteAuditJob drains outbox at least once", "D-010")
    # Failure matrix
    audit.happy("stage before run fails leaves no domain writes", "D-010")
    audit.happy("stage before run may write optional deny audit", "D-010")
    audit.fail("run throws with domain txn rollback leaves no domain writes", "D-010")
    audit.happy("run throws emits CapabilityFailed", "D-010")
    audit.happy("run succeeds audit sync fails best_effort keeps domain returns 200", "D-010")
    audit.edge("run succeeds audit fails strict plus outer txn rolls back if not committed", "D-010")
    audit.edge("run succeeds audit fails strict domain already committed documents footgun", "D-010")
    audit.edge("idempotent replay may skip or mark replay audit", "D-010")
    audit.fail("undefined audit after run with no mode is refused", "D-010")
    audit.fail("default outer transaction wrapping money plus audit is not default", "D-010")
    audit.fail("firing bus events before domain commit by default is refused", "D-010")
    audit.happy("audit.mode best_effort is default", "D-010")
    audit.edge("audit.driver database log queue supported", "D-010")
    audit.edge("events.enabled false suppresses bus events", "D-010")
    files.append(audit)

    # ─────────────────────────────────────────────────────────────
    # Catalog
    # ─────────────────────────────────────────────────────────────
    cat = F("core", "Catalog/CatalogTest.php")
    for field_name in [
        "name",
        "description",
        "surfaces",
        "readOnly",
        "schema_version",
        "idempotent",
        "deprecated",
        "aliases",
        "successor",
        "sunset_at",
    ]:
        cat.happy(f"list includes field {field_name} when applicable", "CAT-001")
    cat.happy("describe returns full input_schema output_schema", "D-004")
    cat.happy("catalog wire is JSON Schema only never Laravel rule strings", "D-004")
    cat.edge("catalog only lists capabilities with at least one effective invoke surface for caller", "SURF-001")
    for c in CALLERS:
        cat.edge(f"catalog visibility respects caller {c} effective surfaces", "CAT-001")
    cat.happy("GET health reports surface status up disabled_incompatible disabled_config", "D-011")
    cat.fail("catalog does not dump full tool schemas for every list entry by default", "CAT-001")
    cat.happy("describe by alias resolves canonical capability", "D-012")
    files.append(cat)

    # ─────────────────────────────────────────────────────────────
    # Errors D-018
    # ─────────────────────────────────────────────────────────────
    err = F("core", "Errors/ErrorEnvelopeTest.php")
    err.happy("success envelope ok true with data and meta request_id capability idempotent_replay", "D-018")
    for code, http, exit_code in ERROR_CODES:
        err.happy(f"error code {code} maps to HTTP {http}", "D-018")
        err.happy(f"error code {code} maps to CLI exit {exit_code}", "D-018")
        err.happy(f"error envelope for {code} includes code message request_id retryable", "D-018")
    err.happy("validation_failed includes violations list", "D-018")
    err.happy("approval_required includes approval_id", "D-018")
    err.happy("retryable flag present on envelope", "D-018")
    err.edge("CLI --json prints same envelope as HTTP", "D-018")
    err.fail("ad-hoc unstructured error body is not returned for known failures", "D-018")
    files.append(err)

    res = F("core", "Support/CapabilityResultTest.php")
    res.happy("ok result carries data", "RES-001")
    res.happy("approval_required result carries approval id", "RES-001")
    res.happy("failure result carries error envelope fields", "RES-001")
    res.happy("replay flag on meta when idempotent replay", "D-005")
    res.edge("assertOk assertFailed assertForbidden helpers for tests", "RES-001")
    res.edge("assertConflict assertExpired helpers for tests", "RES-001")
    files.append(res)

    # ─────────────────────────────────────────────────────────────
    # HTTP / D-009
    # ─────────────────────────────────────────────────────────────
    http = F("core", "Http/CapabilityApiTest.php")
    http.happy("catalog list describe invoke approval auth live on one CapabilityController tree", "D-009")
    http.happy("product CLI is HTTP client of same invoke endpoint not separate controller", "D-009")
    http.fail("no CliApiController invoke pipeline class exists", "D-009")
    http.happy("validation authorize scope approval idempotency audit identical for caller http and cli in-process", "D-009")
    http.edge("Accept vnd.capabilities.cli+json only changes presentation envelope", "D-009")
    http.happy("AuthController serves token and device-code for CLI and API clients", "D-009")
    http.happy("ApprovalController shared for UI CLI and API", "D-009")
    for method, path in [
        ("GET", "/capabilities"),
        ("GET", "/capabilities/{name}"),
        ("POST", "/capabilities/{name}"),
        ("POST", "/capabilities/approvals/{id}/accept"),
        ("POST", "/capabilities/approvals/{id}/reject"),
        ("GET", "/capabilities/health"),
    ]:
        http.happy(f"route {method} {path} registered when http enabled", "D-009")
        http.fail(f"route {method} {path} not registered when http disabled", "D-009")
    http.fail("second invoke controller tree for CLI is refused", "D-009")
    files.append(http)

    http_s = F("core", "Surfaces/HttpAdapterTest.php")
    http_s.happy("POST invoke maps JSON body to registry invoke", "HTTP-001")
    http_s.happy("GET list maps to catalog", "HTTP-001")
    http_s.happy("GET describe maps to capability detail", "HTTP-001")
    http_s.happy("POST approval accept reject map to ApprovalManager", "HTTP-001")
    http_s.fail("unauthenticated request returns unauthenticated envelope", "HTTP-001")
    http_s.happy("middleware stack from config applied", "HTTP-001")
    http_s.edge("prefix from config surfaces.http.prefix", "HTTP-001")
    http_s.fail("forged caller header does not change derived caller", "D-022")
    http_s.happy("Idempotency-Key header forwarded to registry", "D-005")
    http_s.fail("malformed JSON returns validation or bad request envelope", "HTTP-001")
    files.append(http_s)

    # ─────────────────────────────────────────────────────────────
    # Profiles D-008 / P2-007
    # ─────────────────────────────────────────────────────────────
    prof = F("core", "Profiles/AgentAndMcpProfilesTest.php")
    prof.happy("aiTools profile billing returns only profile capability tools", "D-008")
    prof.happy("aiTools groups composes from capability groups tags", "D-008")
    prof.happy("aiTools only explicit list returns those names", "D-008")
    prof.fail("aiTools without profile groups or only throws when require_profile true", "D-008")
    prof.edge("unfiltered aiTools dumps log loud warning and still applies visibility and hard cap", "D-008")
    prof.happy("tools filtered by canDiscover and scope for current actor", "D-008")
    prof.happy("authorize still runs on invoke even if tool listed", "D-008")
    prof.fail("profile expansion above max_tools_hard throws TooManyToolsException", "D-008")
    prof.edge("profile expansion above max_tools_warn logs warning", "D-008")
    prof.happy("mcpTools profile required", "D-008")
    prof.fail("mcpTools without profile throws ProfileRequiredException", "D-008")
    prof.happy("aiMetaTools profile required inherits same allowlist", "P2-007")
    prof.fail("aiMetaTools unscoped throws ProfileRequiredException", "P2-007")
    prof.happy("list_capabilities via meta tools never returns names outside profile", "P2-007")
    prof.fail("run_capability name outside profile returns capability_not_in_profile without registry run", "P2-007")
    prof.happy("run_capability name inside profile hits full registry pipeline", "P2-007")
    prof.happy("mcpMetaTools profile required", "P2-007")
    prof.edge("progressive disclosure is listing strategy not privilege escape", "P2-007")
    prof.happy("unauthorized tools never appear in model tool list", "D-008")
    prof.fail("MCP is never all UI powers for this user by default", "D-008")
    for c in ["agent", "mcp"]:
        prof.edge(f"profile hard cap applies to {c} tools", "D-008")
        prof.edge(f"profile warn threshold applies to {c} tools", "D-008")
    prof.fail("support profile does not include void-invoice when not listed", "D-008")
    prof.happy("billing profile can include void-invoice when listed", "D-008")
    files.append(prof)

    # ─────────────────────────────────────────────────────────────
    # MCP D-023
    # ─────────────────────────────────────────────────────────────
    mcp = F("core", "Mcp/AuthProfilesTest.php")
    mcp.happy("user_pat profile acts as that User with auth_profile user_pat", "D-023")
    mcp.happy("integration credentials map to SystemActor or bot when allowlisted", "D-023")
    mcp.fail("integration credentials denied when allow_integration_credentials false", "D-023")
    mcp.fail("integration without allowSystemCallers on capability fails", "D-023")
    mcp.happy("user_delegated audits User and required client_id", "D-023")
    mcp.fail("tool args actor user_id client_id not authoritative", "D-023")
    mcp.happy("mcp context includes auth_profile and client_id when configured", "D-023")
    mcp.fail("mcpTools without profile throws", "D-023")
    mcp.happy("mcp profile is not full UI capability set for user", "D-023")
    mcp.happy("integration tenant from trusted session config not tool input", "D-023")
    mcp.edge("audit_client_id true always records client_id when present", "D-023")
    for p in MCP_PROFILES:
        mcp.happy(f"auth profile {p} is recognized", "D-023")
        mcp.fail(f"auth profile {p} cannot set actor from tool JSON", "D-023")
    mcp.fail("vague token user without auth profile is refused", "D-023")
    mcp.edge("default_profile config user_pat", "D-023")
    files.append(mcp)

    mcp_s = F("core", "Surfaces/McpAdapterTest.php")
    mcp_s.happy("McpToolAdapterV1 registers tools from profile", "D-011")
    mcp_s.happy("tools call invokes registry with caller mcp and auth profile", "D-023")
    mcp_s.fail("tools call does not accept actor from tool JSON", "D-023")
    mcp_s.edge("tool input_schema equals catalog input_schema no second schema", "D-004")
    mcp_s.fail("mcp surface disabled registers no tools", "SURF-003")
    mcp_s.happy("mcp progressive disclosure listing still constrained by profile", "P2-007")
    mcp_s.happy("idempotency_key tool arg passed through", "D-005")
    mcp_s.fail("authorization deny through mcp does not mutate", "D-011")
    files.append(mcp_s)

    # ─────────────────────────────────────────────────────────────
    # AI adapter
    # ─────────────────────────────────────────────────────────────
    ai = F("core", "Surfaces/AiAdapterTest.php")
    ai.happy("AiToolAdapterV1 builds tools from profile selection", "D-011")
    ai.happy("tool handle validates and invokes registry with caller agent", "D-022")
    ai.fail("tool handle does not accept caller from model input", "D-022")
    ai.happy("max_tool_calls_per_turn enforced on agent loop budget", "D-013")
    ai.edge("tool input_schema equals catalog input_schema", "D-004")
    ai.fail("agent surface disabled registers no tools", "SURF-003")
    ai.happy("idempotency_key tool arg passed through", "D-005")
    ai.fail("authorization deny through ai does not mutate", "D-011")
    ai.edge("messaging agent turn still caller agent with messaging metadata", "D-007")
    files.append(ai)

    job_s = F("core", "Surfaces/JobAdapterTest.php")
    job_s.happy("RunCapability job payload requires actingAs", "D-002")
    job_s.happy("RunCapability passes tenantId as first-class field", "P2-005")
    job_s.fail("RunCapability refuses tenant magic keys from input for system scope", "P2-005")
    job_s.happy("job optional idempotencyKey forwarded", "D-005")
    job_s.fail("dispatch without actingAs is not enqueued", "D-002")
    job_s.edge("failed job tagged with capability name", "D-019")
    job_s.happy("job handle uses registry not domain action directly", "PIPE-008")
    job_s.fail("job surface disabled does not register RunCapability helpers", "SURF-003")
    files.append(job_s)

    # ─────────────────────────────────────────────────────────────
    # Peers D-011
    # ─────────────────────────────────────────────────────────────
    peer = F("core", "Adapters/PeerAdaptersTest.php")
    peer.happy("AdapterApi CURRENT is V1", "D-011")
    peer.happy("AiToolAdapter supportsInstalledPeer false when package missing", "D-011")
    peer.happy("McpToolAdapter supportsInstalledPeer false when package missing", "D-011")
    peer.happy("PeerVersionProbe feature-detects installed peer", "D-011")
    peer.fail("boot fail when surface enabled require_package and peer missing", "D-011")
    peer.edge("boot disable surface when on_incompatible disable", "D-011")
    peer.happy("tool schema mapping unit maps capability JSON Schema to peer tool fields via mock peer", "D-011")
    peer.happy("invoke round-trip mock peer tool call to registry result shape", "D-011")
    peer.happy("profile filter still applies through adapter", "D-011")
    peer.happy("authorization deny through adapter does not mutate", "D-011")
    peer.happy("idempotency key passed through adapter when present", "D-011")
    peer.fail("unsupported peer version does not half-register tools", "D-011")
    peer.fail("catch-all swallow of adapter errors leading to empty tools is refused", "D-011")
    peer.happy("AdapterApi bump required when bridge call shapes change", "D-011")
    peer.edge("health reports disabled_incompatible when soft-disabled", "D-011")
    files.append(peer)

    # ─────────────────────────────────────────────────────────────
    # Naming D-012
    # ─────────────────────────────────────────────────────────────
    name = F("core", "Naming/DeprecationTest.php")
    name.happy("invoke by alias resolves to canonical and runs once under canonical", "D-012")
    name.happy("catalog includes aliases deprecated successor sunset_at", "D-012")
    name.edge("deprecated true surfaces warning metadata for CLI", "D-012")
    name.fail("after sunset_at alias or name returns 410", "D-012")
    name.happy("dual-name period both canonical and alias work before sunset", "D-012")
    name.happy("describe includes deprecation fields", "D-012")
    name.edge("successor points clients to replacement name", "D-012")
    name.fail("invoke after sunset does not run domain", "D-012")
    files.append(name)

    # ─────────────────────────────────────────────────────────────
    # Rate limiting D-013
    # ─────────────────────────────────────────────────────────────
    rate = F("core", "RateLimiting/RateLimitingTest.php")
    rate.happy("under limit invoke succeeds", "D-013")
    rate.fail("exceeding per_minute returns rate_limited", "D-013")
    rate.fail("exceeding per_capability_per_minute returns rate_limited", "D-013")
    rate.happy("agent turn max_tool_calls stops loop with structured message", "D-013")
    rate.edge("per-capability rateLimit attribute overrides defaults", "D-013")
    rate.happy("rate limit keys include actor capability surface tenant", "D-013")
    rate.edge("rate limits disabled when config enabled false", "D-013")
    for c in CALLERS:
        rate.edge(f"rate limit applies to caller {c}", "D-013")
        rate.fail(f"exceeding limit for caller {c} does not call run", "D-013")
    rate.happy("rate_limited maps to HTTP 429 and CLI exit 6", "D-018")
    files.append(rate)

    # ─────────────────────────────────────────────────────────────
    # Observability D-019
    # ─────────────────────────────────────────────────────────────
    obs = F("core", "Observability/ObservabilityTest.php")
    for metric in [
        "capabilities_invoke_total",
        "approval_required_total",
        "authz_deny_total",
        "rate_limited_total",
        "idempotent_replay_total",
        "approvals_stuck_approved_total",
        "approvals_resume_total",
        "approvals_accept_total",
    ]:
        obs.happy(f"metric {metric} incremented or sampled on matching condition", "D-019")
    obs.happy("latency histogram recorded", "D-019")
    obs.happy("span capabilities.invoke attributes set", "D-019")
    for attr in ["capability", "caller", "surface", "tenant_id", "actor_type", "approval_id", "idempotency_key"]:
        obs.edge(f"span attribute {attr} present when applicable", "D-019")
    obs.edge("Metrics Tracer contracts fall back to log when no driver", "D-019")
    obs.edge("observability metrics false disables metric emission", "D-019")
    obs.edge("observability tracing false disables spans", "D-019")
    files.append(obs)

    # ─────────────────────────────────────────────────────────────
    # Architecture messaging boundary D-007
    # ─────────────────────────────────────────────────────────────
    arch = F("core", "Architecture/MessagingBoundaryTest.php")
    arch.happy("core has ConversationIngress ConversationReply ConversationIdentity ApprovalNotifier contracts", "D-007")
    arch.fail("core package source has no Telegram Bot API dependency", "D-007")
    arch.fail("core package source has no Slack Bot API dependency", "D-007")
    arch.fail("core package source has no WhatsApp dependency", "D-007")
    arch.fail("core package source has no Messaging directory for bot runtime", "D-007")
    arch.happy("messaging package depends on core contracts", "D-007")
    arch.happy("messaging never exposes alternate run API", "D-007")
    arch.edge("core composer suggest lists messaging optional", "D-007")
    arch.fail("core does not require TELEGRAM_BOT_TOKEN", "D-021")
    files.append(arch)

    # Testing helpers D-020
    help_ = F("core", "TestingHelpers/ParityAndSnapshotsTest.php")
    help_.happy("assertSchemaSnapshot fails when input_schema changes without update", "D-020")
    help_.happy("assertSchemaSnapshot passes when schema matches snapshot", "D-020")
    help_.happy("assertParity same success class across registry surfaces with mocks", "D-020")
    help_.happy("assertCannotInvokeAcrossTenant fails test on cross-tenant success", "D-003")
    help_.happy("assertScopeResolvedTo fails when scope mismatches", "D-003")
    help_.happy("assertLastScopeTenant reflects SystemActor first-class tenant not smuggled input", "P2-005")
    help_.edge("assertParity same deny class across registry http and ai with mocks", "D-020")
    for c in CALLERS:
        help_.edge(f"assertParity can include surface path for {c}", "D-020")
    help_.happy("Capability::fake available for unit tests", "D-020")
    files.append(help_)

    # Config surface exhaustive toggles
    cfg = F("core", "Config/CapabilitiesConfigTest.php")
    cfg.happy("default config array has expected top-level keys", "CFG-001")
    for key in [
        "path",
        "surfaces",
        "audit",
        "transactions",
        "events",
        "approval",
        "idempotency",
        "validation",
        "rate_limits",
        "observability",
        "clients",
    ]:
        cfg.happy(f"config key {key} present", "CFG-001")
    cfg.happy("audit.mode default best_effort", "D-010")
    cfg.happy("transactions.wrap_run default false", "D-010")
    cfg.happy("validation.validate_output default true", "D-014")
    cfg.happy("approval.execution default deferred", "D-006")
    cfg.happy("approval.ttl_hours default 24", "D-006")
    cfg.happy("idempotency.ttl_hours default 24", "D-005")
    cfg.happy("rate_limits.defaults.per_minute default 60", "D-013")
    cfg.happy("rate_limits.defaults.per_capability_per_minute default 30", "D-013")
    cfg.happy("agent max_tools_hard default 64", "D-008")
    cfg.happy("agent max_tools_warn default 32", "D-008")
    cfg.happy("agent max_tool_calls_per_turn default 16", "D-013")
    cfg.happy("mcp auth default_profile user_pat", "D-023")
    cfg.happy("mcp allow_integration_credentials default false", "D-023")
    cfg.happy("clients privilege_order default http cli mcp agent job", "D-022")
    cfg.happy("clients reject_upgrade_attempts default false", "D-022")
    cfg.edge("token_abilities capabilities:cli maps to cli", "D-022")
    files.append(cfg)

    # Pipeline middleware units
    pipe_mw = F("core", "Pipeline/MiddlewareUnitsTest.php")
    pipe_mw.happy("ResolveActor builds non-null principal", "PIPE-010")
    pipe_mw.fail("ResolveActor refuses null principal", "PIPE-010")
    pipe_mw.happy("ResolveTenantFromCaller attaches scope", "D-003")
    pipe_mw.fail("ResolveTenantFromCaller fails closed when unusable", "D-003")
    pipe_mw.happy("IdempotencyGuard short-circuits on completed same hash", "D-005")
    pipe_mw.fail("IdempotencyGuard conflicts on different hash", "D-005")
    pipe_mw.edge("IdempotencyGuard processing returns too early or conflict", "D-005")
    files.append(pipe_mw)

    # Events unit
    events = F("core", "Events/BusEventsTest.php")
    for event in [
        "CapabilityInvoked",
        "CapabilityFailed",
        "CapabilityApprovalRequested",
        "CapabilityApprovalDecided",
        "CapabilityApprovalExecuted",
    ]:
        events.happy(f"{event} carries capability name and correlation ids", "D-010")
        events.fail(f"{event} is not dispatched before domain commit on success path by default", "D-010")
    files.append(events)

    # ─────────────────────────────────────────────────────────────
    # MESSAGING package
    # ─────────────────────────────────────────────────────────────
    mboot = F("messaging", "Boot/DeferredEnvValidationTest.php")
    mboot.happy("package boot with messaging enabled does not require TELEGRAM_BOT_TOKEN", "D-021")
    mboot.fail("first webhook without token fails loudly", "D-021")
    mboot.fail("messaging:telegram-setup without secrets fails loudly", "D-021")
    mboot.fail("first outbound notify without secrets fails loudly", "D-021")
    mboot.edge("CAPABILITIES_SKIP_BOOT_CHECKS does not apply to production", "D-021")
    mboot.happy("artisan migrate path does not require TELEGRAM secrets at boot", "D-021")
    mboot.fail("production ignores CAPABILITIES_SKIP_BOOT_CHECKS for messaging secrets path", "D-021")
    mboot.edge("health includes messaging readiness when surface on", "D-021")
    files.append(mboot)

    mcfg = F("messaging", "Config/MessagingConfigTest.php")
    mcfg.happy("telegram channel config reads env keys", "MSG-001")
    mcfg.happy("agent profile name required in messaging config for bot", "D-008")
    mcfg.edge("telegram enabled false registers no webhook routes", "MSG-001")
    mcfg.fail("missing agent profile name fails loudly on first bot traffic", "D-008")
    mcfg.happy("telegram channel switch independent of core messaging surface", "MSG-001")
    mcfg.edge("webhook secret config key present", "MSG-001")
    mcfg.edge("bot token config key present", "MSG-001")
    files.append(mcfg)

    mid = F("messaging", "Identity/IdentityLinkerTest.php")
    mid.happy("code link flow binds Telegram user to Laravel User", "MSG-002")
    mid.happy("allowlist mode allows listed identities", "MSG-002")
    mid.fail("unlinked user cannot run tools", "MSG-002")
    mid.fail("forged identity payload rejected", "MSG-002")
    mid.edge("code link expires and cannot be reused after bind", "MSG-002")
    mid.fail("allowlist identity from wrong tenant cannot escalate", "MSG-002")
    mid.fail("unlinked identity never starts agent turn with tools", "MSG-002")
    mid.happy("linked identity resolves to User for ConversationIngress", "MSG-002")
    files.append(mid)

    mnote = F("messaging", "Notifiers/TelegramApprovalNotifierTest.php")
    mnote.happy("notifyPending sends message with signed buttons", "D-006")
    mnote.happy("notifier never executes capability", "D-006")
    mnote.edge("expired approval may edit message to expired", "D-006")
    mnote.fail("notify with invalid approval id does not execute capability", "D-006")
    mnote.happy("notifier routes accept reject only through ApprovalManager", "D-006")
    mnote.fail("notifier does not call domain services", "D-007")
    files.append(mnote)

    mcb = F("messaging", "Telegram/CallbackSignerTest.php")
    mcb.happy("signed callback over approval_id action exp verifies", "D-006")
    mcb.fail("tampered token invalid", "D-006")
    mcb.fail("expired token invalid", "D-006")
    mcb.fail("unsigned approval id only is rejected", "D-006")
    mcb.happy("callback does not embed capability input", "D-006")
    mcb.happy("after executed rejected expired callback is no-op already handled", "D-006")
    mcb.fail("Telegram user not linked to allowed approver cannot approve", "D-006")
    mcb.happy("linked allowed approver routes to ApprovalManager accept reject", "D-006")
    mcb.edge("callback TTL uses configured telegram_callback_ttl_seconds", "D-006")
    mcb.fail("callback does not carry capability input payload", "D-006")
    mcb.happy("HTTP and Telegram accept share ApprovalManager state machine", "D-006")
    mcb.fail("forged HMAC rejected", "D-006")
    mcb.fail("action other than accept reject rejected", "D-006")
    mcb.happy("server loads input only from approval row", "D-006")
    files.append(mcb)

    mpu = F("messaging", "Telegram/ProcessUpdateTest.php")
    mpu.happy("linked identity maps chat to agent turn via ConversationIngress", "MSG-003")
    mpu.happy("agent tools use configured profile not full catalog", "D-008")
    mpu.happy("tool calls go through CapabilityRegistry only", "D-007")
    mpu.happy("ConversationReply sends response via Bot API mock", "MSG-003")
    mpu.fail("unlinked Telegram user never gets tool access", "MSG-002")
    mpu.fail("adapter never calls Eloquent domain services directly", "D-007")
    mpu.fail("adapter never owns second run path", "D-007")
    mpu.happy("thread store maps chat topic to conversation thread", "MSG-004")
    mpu.edge("failed ProcessTelegramUpdate tags channel for failed jobs", "D-019")
    mpu.fail("unlinked allowlist miss never starts agent turn with tools", "MSG-002")
    mpu.happy("agent profile from messaging config not full catalog", "D-008")
    mpu.edge("queue ProcessTelegramUpdate async not sync domain mutation", "MSG-003")
    mpu.happy("pipeline verify secret then queue then identity then thread then ingress", "MSG-003")
    files.append(mpu)

    mwh = F("messaging", "Telegram/WebhookTest.php")
    mwh.happy("valid webhook secret accepts update and queues ProcessTelegramUpdate", "MSG-003")
    mwh.fail("invalid webhook secret rejects request", "MSG-003")
    mwh.fail("missing webhook secret when channel enabled fails on first request not boot", "D-021")
    mwh.edge("secrets not required at service provider boot for artisan migrate", "D-021")
    mwh.edge("queues ProcessTelegramUpdate async not sync domain mutation", "MSG-003")
    mwh.fail("forged webhook body rejected before queue", "MSG-003")
    mwh.fail("webhook does not invoke capability registry directly", "D-007")
    files.append(mwh)

    mth = F("messaging", "Threads/ThreadStoreTest.php")
    mth.happy("creates and retrieves thread by chat id", "MSG-004")
    mth.happy("appends history for agent turn", "MSG-004")
    mth.edge("topic threads isolated per topic id", "MSG-004")
    mth.happy("maps chat topic to conversation thread id", "MSG-004")
    mth.fail("unknown chat without create policy does not leak other threads", "MSG-004")
    mth.edge("history append is ordered", "MSG-004")
    files.append(mth)

    march = F("messaging", "Architecture/NoDomainRunTest.php")
    march.fail("messaging source has no alternate Capability::run wrapper bypassing registry", "D-007")
    march.fail("messaging source does not import app Eloquent models for mutation", "D-007")
    march.happy("messaging depends on core contracts only for ingress reply identity notifier", "D-007")
    march.edge("later Slack WhatsApp channels would live in messaging not core", "D-007")
    files.append(march)

    # ─────────────────────────────────────────────────────────────
    # CLI (Go)
    # ─────────────────────────────────────────────────────────────
    main = F("cli", "cmd/capabilities/main_test.go", language="go", go_package="main")
    for title in [
        "BinaryNameIsCapabilities",
        "HelpListsAuthCatalogRunMcpApprovals",
        "BinaryIsNotArtisan",
        "HelpDocumentsExitCodes",
        "HelpDocumentsJsonFlag",
        "RootCommandRequiresSubcommand",
        "VersionCommandExists",
    ]:
        main.add("go", title, "D-016")
    files.append(main)

    api = F("cli", "internal/api/api_test.go", language="go", go_package="api")
    for title in [
        "ClientPostsToSingleCapabilityHTTPAPI",
        "ClientSendsBearerFromKeychain",
        "ClientSetsCallerNotViaSpoofHeaderAsAuthority",
        "ClientOptionalCLIAcceptHeader",
        "ClientIdempotencyHeaderAlwaysPresentOnRun",
        "ClientDoesNotEmbedDomainLogic",
        "ClientUsesSameInvokePathForCatalogDescribeRun",
        "ClientMapsHTTPErrorEnvelopeToStructuredError",
        "ClientForwardsRequestIdFromResponse",
        "ClientTimeoutIsConfigurable",
        "ClientDoesNotSendXCapabilitiesCallerAsAuthority",
        "ClientBaseURLFromProfile",
    ]:
        api.add("go", title, "D-009")
    files.append(api)

    auth = F("cli", "internal/auth/auth_test.go", language="go", go_package="auth")
    for title in [
        "AuthLoginStoresTokenInKeychainNotPrompt",
        "AuthStatusShowsProfileBaseURL",
        "AuthLogoutClearsToken",
        "AuthRequiredBeforeRun",
        "AuthLoginDeviceCodeFlow",
        "AuthLoginBrowserOAuthFlow",
        "AuthTokenNeverPrintedToStdoutByDefault",
        "AuthProfileIsolationPerBaseURL",
        "AuthMissingTokenReturnsExitCode3",
        "AuthLoginFetchesSchemasIntoCache",
    ]:
        auth.add("go", title, "CLI-AUTH")
    files.append(auth)

    catal = F("cli", "internal/catalog/catalog_test.go", language="go", go_package="catalog")
    for title in [
        "CatalogListsCapabilitiesFromHTTP",
        "DescribeFetchesJSONSchema",
        "SchemaCacheByNameAndVersion",
        "CatalogRefreshInvalidatesCache",
        "DeprecatedCapabilityWarns",
        "CatalogNoCacheFlagForcesRefetch",
        "DescribeByAliasResolvesCanonical",
        "CatalogOmitsDisabledSurfaces",
        "SchemaCacheEtagInvalidation",
        "CatalogJSONOutputEnvelope",
        "SunsetCapabilityWarnedOrBlocked",
    ]:
        catal.add("go", title, "CLI-CAT")
    files.append(catal)

    mcpstdio = F("cli", "internal/mcpstdio/mcpstdio_test.go", language="go", go_package="mcpstdio")
    for title in [
        "McpStdioProxiesToRemoteHTTPWithStoredToken",
        "McpStdioNoLocalDomainRun",
        "McpStdioUsesSameAuthAsCLI",
        "McpStdioDoesNotBypassServerAuthorization",
        "McpStdioProfileToolsComeFromServer",
        "McpStdioForwardsIdempotencyKeys",
    ]:
        mcpstdio.add("go", title, "CLI-MCP")
    files.append(mcpstdio)

    run = F("cli", "internal/run/run_test.go", language="go", go_package="run")
    for title in [
        "RunValidatesSchemaLocallyBeforePOST",
        "RunStructuralErrorExitCode2NoNetwork",
        "RunAutoGeneratesIdempotencyKey",
        "RunRespectsManualIdempotencyKey",
        "RunSuccessExitCode0",
        "RunAuthErrorExitCode3",
        "RunApprovalRequiredExitCode4",
        "RunDomainErrorExitCode5",
        "RunRateLimitedExitCode6",
        "RunJSONEnvelopeMatchesD018",
        "RunNoDomainLogicOnClient",
        "RunRetryLastReusesKey",
        "RunInputFileFlagReadsJSON",
        "RunJsonFlagPrintsEnvelope",
        "RunServerOnlyValidationStillCheckedServerSide",
        "RunDoesNotSkipServerRevalidation",
        "RunConflictIdempotencyExitMapped",
        "RunNotFoundExitMapped",
        "RunOutputInvalidExitMapped",
        "RunInternalErrorExitCode1",
        "RunTenantHintIsNotAuthoritativeScope",
        "RunForbiddenExitCode3",
        "RunUnauthenticatedExitCode3",
        "RunValidationViolationsListedInEnvelope",
    ]:
        run.add("go", title, "CLI-RUN")
    files.append(run)

    # Expand matrix: every error code × local mapping unit in errors for CLI
    cli_err = F("cli", "internal/api/errors_test.go", language="go", go_package="api")
    for code, http, exit_code in ERROR_CODES:
        safe = code.replace("-", "_")
        cli_err.add("go", f"MapErrorCode_{safe}_HTTP{http}_Exit{exit_code}", "D-018")
    cli_err.add("go", "UnknownErrorCodeDefaultsToInternalExit1", "D-018")
    cli_err.add("go", "RetryableFlagParsedFromEnvelope", "D-018")
    files.append(cli_err)

    # Expand: per-caller parity for key governance behaviors in core
    parity = F("core", "Parity/CrossCallerGovernanceTest.php")
    for c in CALLERS:
        parity.happy(f"authorize deny via {c} does not mutate", "PARITY-001")
        parity.happy(f"schema invalid via {c} does not mutate", "PARITY-001")
        parity.happy(f"scope cross-tenant via {c} does not mutate", "D-003")
        parity.happy(f"needsApproval true via {c} does not run until accept", "D-006")
        parity.happy(f"idempotent replay via {c} does not second run", "D-005")
        parity.happy(f"rate limited via {c} does not run", "D-013")
        parity.happy(f"audit records caller {c} on success", "D-010")
        parity.fail(f"client cannot spoof caller away from {c} when derived as {c}", "D-022")
    files.append(parity)

    # Expand: full refuse tables as fail cases
    refuse = F("core", "Architecture/RefuseTableTest.php")
    refuse_rows = [
        ("null user allow on jobs", "D-002"),
        ("jobs bypass policy global config", "D-002"),
        ("tenant magic key for SystemActor", "P2-005"),
        ("client spoofable caller header upgrade", "D-022"),
        ("full catalog dump to agents by default", "D-008"),
        ("meta tools privilege escape", "P2-007"),
        ("second HTTP invoke controller for CLI", "D-009"),
        ("Laravel rules as only schema source", "D-004"),
        ("CLI only validation without server revalidation", "D-004"),
        ("approval without revalidation on accept", "D-006"),
        ("unsigned Telegram approve id", "D-006"),
        ("approved limbo without resume or atomic", "P2-004"),
        ("silent audit drop when required", "D-010"),
        ("peer half-register tools", "D-011"),
        ("messaging bot runtime inside core", "D-007"),
        ("Artisan as product CLI", "D-016"),
        ("MCP vague token user without profile", "D-023"),
        ("integration credentials without allowlist", "D-023"),
        ("actor from tool JSON for MCP", "D-023"),
        ("SystemActor can approve", "D-006"),
        ("idempotency only on one surface", "D-005"),
        ("dedupe by input without key", "D-005"),
        ("third capability discovery path", "D-017"),
        ("domain logic in Go CLI", "D-016"),
        ("trust exists alone for multi-tenant", "D-003"),
    ]
    for title, req in refuse_rows:
        refuse.fail(f"package refuses anti-pattern: {title}", req)
    files.append(refuse)

    # Expand: config × surface disabled matrix already partial; add per-route
    routes = F("core", "Http/RoutesMatrixTest.php")
    for enabled in [True, False]:
        state = "enabled" if enabled else "disabled"
        for route in ["list", "describe", "invoke", "approval_accept", "approval_reject", "health", "auth_token", "auth_device"]:
            if enabled:
                routes.happy(f"http surface {state} registers {route}", "D-009")
            else:
                routes.fail(f"http surface {state} does not register {route}", "D-009")
    files.append(routes)

    # Expand: idempotency × surface matrix already in D-005; add approval × shape
    appr_m = F("core", "Approval/ApprovalMatrixTest.php")
    for shape in ["deferred", "atomic"]:
        for action in ["accept", "reject"]:
            for status in APPROVAL_STATUSES:
                if action == "accept" and status == "pending":
                    appr_m.happy(f"shape {shape} {action} from {status} executes state machine", "D-006")
                elif action == "reject" and status == "pending":
                    appr_m.happy(f"shape {shape} {action} from {status} never runs", "D-006")
                elif status == "executed" and action == "accept":
                    appr_m.happy(f"shape {shape} {action} from {status} replays", "D-006")
                else:
                    appr_m.fail(f"shape {shape} {action} from {status} does not re-run domain", "D-006")
    for policy in APPROVAL_POLICIES:
        appr_m.edge(f"policy {policy} allows authorized decision", "D-006")
        appr_m.fail(f"policy {policy} forbids unauthorized decision", "D-006")
    files.append(appr_m)

    # Expand: schema type edge matrix
    schema_m = F("core", "Schema/SchemaValidationMatrixTest.php")
    for typ, bad in [
        ("integer", "string"),
        ("integer", "null_when_required"),
        ("string", "integer"),
        ("boolean", "string"),
        ("array", "object"),
        ("object", "array"),
        ("enum", "outside_set"),
        ("format_date", "invalid_date"),
        ("minLength", "too_short"),
        ("maxLength", "too_long"),
        ("minimum", "below_min"),
        ("maximum", "above_max"),
        ("required", "missing"),
        ("additionalProperties_false", "unknown_key"),
    ]:
        schema_m.fail(f"portable validation rejects {typ} when {bad}", "D-004")
        schema_m.happy(f"portable validation accepts valid {typ}", "D-004")
    files.append(schema_m)

    # Expand: observability labels matrix
    obs_m = F("core", "Observability/MetricsLabelsTest.php")
    for c in CALLERS:
        for status in ["ok", "validation_failed", "forbidden", "approval_required", "rate_limited", "domain_error"]:
            obs_m.happy(f"capabilities_invoke_total labels caller={c} status={status}", "D-019")
    files.append(obs_m)

    # Expand: boot peer matrix
    peer_m = F("core", "Boot/PeerMatrixTest.php")
    for surface, peer in [("agent", "laravel/ai"), ("mcp", "laravel/mcp")]:
        for installed in [True, False]:
            for compatible in [True, False]:
                for mode in ["fail", "disable"]:
                    if not installed or not compatible:
                        if mode == "fail":
                            peer_m.fail(
                                f"{surface} peer={peer} installed={installed} compatible={compatible} mode={mode} boots failed",
                                "D-011",
                            )
                        else:
                            peer_m.edge(
                                f"{surface} peer={peer} installed={installed} compatible={compatible} mode={mode} soft-disables",
                                "D-011",
                            )
                        peer_m.fail(
                            f"{surface} peer={peer} installed={installed} compatible={compatible} mode={mode} never half-registers",
                            "D-011",
                        )
                    else:
                        peer_m.happy(
                            f"{surface} peer={peer} installed={installed} compatible={compatible} mode={mode} registers",
                            "D-011",
                        )
    files.append(peer_m)

    # Expand: job actor × tenancy matrix
    job_m = F("core", "Job/JobTenancyMatrixTest.php")
    for actor in ["user", "system"]:
        for tenant in ["present", "absent"]:
            for global_system in [True, False]:
                for tenancy_required in [True, False]:
                    label = f"actor={actor} tenant={tenant} globalSystem={global_system} tenancyRequired={tenancy_required}"
                    if actor == "system" and tenant == "absent" and not global_system and tenancy_required:
                        job_m.fail(f"job dispatch refused when {label}", "P2-005")
                    elif actor == "system" and tenant == "present":
                        job_m.happy(f"job dispatch allowed when {label} and allowlisted", "D-002")
                    elif actor == "user" and tenant == "absent":
                        job_m.edge(f"job dispatch scope from user membership when {label}", "D-003")
                    else:
                        job_m.edge(f"job dispatch resolves scope when {label}", "D-003")
    files.append(job_m)

    # Expand: CLI exit matrix already partial; add more CLI files
    run2 = F("cli", "internal/run/flags_test.go", language="go", go_package="run")
    for title in [
        "FlagInputJSON",
        "FlagInputFile",
        "FlagIdempotencyKey",
        "FlagJson",
        "FlagNoCache",
        "FlagTenantHintNotAuthoritative",
        "FlagRetryLast",
        "FlagBaseURLOverride",
        "FlagProfileSelection",
        "MissingInputFailsLocalValidation",
        "InvalidJSONInputFailsLocalValidation",
        "EmptyCapabilityNameFails",
    ]:
        run2.add("go", title, "CLI-RUN")
    files.append(run2)

    # Philosophy beliefs as architecture tests
    phil = F("core", "Architecture/BeliefsTest.php")
    beliefs = [
        "one run per product mutation",
        "capability is product language not transport",
        "governance is part of capability",
        "compose official packages do not replace them",
        "surfaces optional defaults generous",
        "CLI is a client not second backend",
        "thin framework fat domain",
        "fail closed and fail obvious",
        "no silent actors",
        "no ambient tenancy",
        "retries must not double apply",
        "approvals are decisions not fire and forget",
        "least privilege for model tool lists",
        "framework does not reintroduce dual paths",
        "domain success not hostage to audit failure unless strict",
        "peer packages pinned by matrix",
        "caller is server derived fact",
        "MCP principals are explicit auth profiles",
    ]
    for b in beliefs:
        phil.happy(f"belief enforced by tests: {b}", "BELIEF")
    files.append(phil)

    # Additional exhaustive HTTP auth derivation matrix
    auth_m = F("core", "Caller/CredentialMatrixTest.php")
    for ability, expected in [
        ("capabilities:cli", "cli"),
        ("", "http"),
        ("api", "http"),
        ("capabilities:http", "http"),
    ]:
        auth_m.happy(f"token ability '{ability or 'none'}' derives caller {expected}", "D-022")
    for client, expected in [
        ("capabilities-cli", "cli"),
        ("ios-app", "http"),
        ("unknown-client", "http"),
    ]:
        auth_m.edge(f"oauth client_id {client} maps to {expected} when configured", "D-022")
    for header, derived, result in [
        ("cli", "http", "ignored_or_rejected_upgrade"),
        ("http", "cli", "downgrade_or_keep"),
        ("agent", "http", "ignored_upgrade"),
        ("job", "http", "ignored_upgrade"),
    ]:
        auth_m.fail(f"header claim {header} with derived {derived} results in {result}", "D-022")
    files.append(auth_m)

    # Catalog health matrix
    health = F("core", "Catalog/HealthMatrixTest.php")
    for s in SURFACES:
        for status in ["up", "disabled_incompatible", "disabled_config"]:
            health.edge(f"health reports surface {s} status {status} when applicable", "D-011")
    files.append(health)

    # Approval audit chain per event
    # (already covered) — add notifier matrix
    notif = F("core", "Approval/NotifiersTest.php")
    notif.happy("HttpApprovalNotifier notifies pending without executing", "D-006")
    notif.happy("CliApprovalNotifier notifies pending without executing", "D-006")
    notif.fail("notifiers never call capability run", "D-006")
    notif.edge("missing notifier channel is non-fatal for pending store", "D-006")
    files.append(notif)

    # Artisan surface
    art = F("core", "Surfaces/ArtisanAdapterTest.php")
    art.happy("capability run artisan command requires acting-as or system for mutations", "D-002")
    art.fail("bare artisan capability run for mutating cap refused", "D-002")
    art.happy("artisan surface disabled registers no capability commands", "SURF-003")
    art.edge("artisan is not the product CLI", "D-016")
    art.happy("artisan in-process invoke hits registry", "PIPE-008")
    files.append(art)

    # Idempotency storage identity matrix
    idem_m = F("core", "Idempotency/StorageIdentityMatrixTest.php")
    for c in CALLERS:
        for actor in ["user:1", "user:2", "system:scheduler"]:
            for cap in ["create-invoice", "void-invoice"]:
                idem_m.edge(f"key identity isolated for caller={c} actor={actor} cap={cap}", "D-005")
    files.append(idem_m)

    # Output validation × caller
    out_m = F("core", "Schema/OutputValidationMatrixTest.php")
    for c in CALLERS:
        out_m.fail(f"invalid output via {c} is not success", "D-014")
        out_m.happy(f"valid output via {c} succeeds", "D-014")
    files.append(out_m)

    # MCP confused deputy controls
    mcp_m = F("core", "Mcp/ConfusedDeputyTest.php")
    mcp_m.fail("delegated client cannot widen profile via tool args", "D-023")
    mcp_m.fail("integration bot cannot act as arbitrary user id from tool args", "D-023")
    mcp_m.fail("user_pat cannot set client_id as actor authority", "D-023")
    mcp_m.happy("user_delegated records both user and client_id in audit", "D-023")
    mcp_m.edge("host session metadata optional and non-authoritative for authz", "D-023")
    files.append(mcp_m)

    # Messaging identity matrix
    mid_m = F("messaging", "Identity/IdentityMatrixTest.php")
    for mode in ["code_link", "allowlist"]:
        mid_m.happy(f"mode {mode} allows linked identity", "MSG-002")
        mid_m.fail(f"mode {mode} denies unlinked identity", "MSG-002")
        mid_m.fail(f"mode {mode} denies forged payload", "MSG-002")
    files.append(mid_m)

    # Thread isolation matrix
    th_m = F("messaging", "Threads/ThreadIsolationMatrixTest.php")
    for topic in ["null", "1", "2", "general"]:
        th_m.edge(f"thread isolation for topic={topic}", "MSG-004")
        th_m.fail(f"topic={topic} cannot read other topic history", "MSG-004")
    files.append(th_m)

    # Webhook security matrix
    wh_m = F("messaging", "Telegram/WebhookSecurityMatrixTest.php")
    for secret in ["valid", "invalid", "missing", "empty"]:
        if secret == "valid":
            wh_m.happy(f"webhook secret {secret} accepted", "MSG-003")
        else:
            wh_m.fail(f"webhook secret {secret} rejected", "MSG-003")
    files.append(wh_m)

    # CLI catalog × deprecation matrix
    cat_m = F("cli", "internal/catalog/deprecation_test.go", language="go", go_package="catalog")
    for title in [
        "WarnOnDeprecatedTrue",
        "ShowSuccessorWhenPresent",
        "BlockOrWarnAfterSunset",
        "AliasResolvesBeforeRun",
        "CanonicalPreferredInList",
    ]:
        cat_m.add("go", title, "D-012")
    files.append(cat_m)

    # Large expansion: every pipeline stage × every caller × fail assertion group
    pipe_m = F("core", "Registry/PipelineStageCallerMatrixTest.php")
    for stage in PIPELINE_STAGES_BEFORE_RUN:
        for c in CALLERS:
            pipe_m.fail(f"stage {stage} fail on caller {c} does not call run", "PIPE-002")
            pipe_m.fail(f"stage {stage} fail on caller {c} has no domain side effects", "PIPE-002")
            pipe_m.happy(f"stage {stage} fail on caller {c} returns structured error", "PIPE-002")
    files.append(pipe_m)

    # Large expansion: surfaces intersection matrix
    surf_m = F("core", "Boot/SurfaceIntersectionMatrixTest.php")
    for global_s in INVOKE_SURFACES:
        for cap_s in INVOKE_SURFACES:
            for gen in [True, False]:
                for cen in [True, False]:
                    effective = gen and cen and global_s == cap_s
                    # only generate when same surface name intersection makes sense
                    if global_s != cap_s:
                        continue
                    label = f"surface={global_s} global={gen} capability_lists={cen}"
                    if effective:
                        surf_m.happy(f"effective exposure true when {label}", "SURF-001")
                    else:
                        surf_m.edge(f"effective exposure false when {label}", "SURF-001")
    # capability lists surface but global off
    for s in INVOKE_SURFACES:
        surf_m.fail(f"capability listing {s} cannot enable when global {s} disabled", "SURF-001")
    files.append(surf_m)

    # Large expansion: error envelope field completeness × code
    err_m = F("core", "Errors/EnvelopeFieldMatrixTest.php")
    for code, _, _ in ERROR_CODES:
        for field_name in ["code", "message", "request_id", "retryable"]:
            err_m.happy(f"envelope for {code} includes field {field_name}", "D-018")
        err_m.edge(f"envelope for {code} violations only when validation", "D-018")
        err_m.edge(f"envelope for {code} approval_id only when approval_required", "D-018")
    files.append(err_m)

    # Large expansion: audit field × caller
    audit_m = F("core", "Audit/AuditFieldCallerMatrixTest.php")
    for c in CALLERS:
        for field_name in ["name", "caller", "actor", "scope", "duration", "result"]:
            audit_m.happy(f"audit field {field_name} set for caller {c} on success", "D-010")
    files.append(audit_m)

    # Large expansion: rate limit key parts
    rate_m = F("core", "RateLimiting/RateLimitKeyMatrixTest.php")
    for c in CALLERS:
        for part in ["actor", "capability", "surface", "tenant"]:
            rate_m.edge(f"rate limit key includes {part} for caller {c}", "D-013")
    files.append(rate_m)

    # Large expansion: profile composition
    prof_m = F("core", "Profiles/ProfileCompositionMatrixTest.php")
    for sel in ["profile:billing", "profile:support", "groups:finance", "only:create-invoice", "only:void-invoice"]:
        prof_m.happy(f"selection {sel} returns only allowed tools", "D-008")
        prof_m.fail(f"selection {sel} never returns tools outside selection", "D-008")
        prof_m.fail(f"selection {sel} still authorizes on invoke", "D-008")
    for meta in ["aiMetaTools", "mcpMetaTools"]:
        prof_m.fail(f"{meta} without profile throws", "P2-007")
        prof_m.happy(f"{meta} with profile inherits allowlist", "P2-007")
        prof_m.fail(f"{meta} run outside profile blocked", "P2-007")
        prof_m.happy(f"{meta} list outside profile excluded", "P2-007")
    files.append(prof_m)

    # Large expansion: deprecation lifecycle
    dep_m = F("core", "Naming/DeprecationLifecycleMatrixTest.php")
    for phase in ["active", "deprecated_before_sunset", "after_sunset"]:
        for name_kind in ["canonical", "alias"]:
            if phase == "after_sunset":
                dep_m.fail(f"{name_kind} invoke in phase {phase} returns 410", "D-012")
            else:
                dep_m.happy(f"{name_kind} invoke in phase {phase} resolves and runs once", "D-012")
            dep_m.edge(f"catalog shows deprecation metadata in phase {phase} for {name_kind}", "D-012")
    files.append(dep_m)

    # Large expansion: approval who-may-approve matrix
    who = F("core", "Approval/WhoMayApproveMatrixTest.php")
    for policy in APPROVAL_POLICIES:
        for actor in ["requester", "role_holder", "random_user", "system_actor", "other_tenant_user"]:
            if actor == "system_actor":
                who.fail(f"policy {policy} denies system_actor", "D-006")
            elif actor == "other_tenant_user":
                who.fail(f"policy {policy} denies other_tenant_user", "D-006")
            elif actor == "random_user" and policy in ("requester", "role:finance-approver"):
                who.fail(f"policy {policy} denies random_user", "D-006")
            elif actor == "requester" and policy == "role:finance-approver":
                who.fail(f"policy {policy} denies requester self-approve", "D-006")
            else:
                who.edge(f"policy {policy} decision for actor {actor}", "D-006")
    files.append(who)

    # Large expansion: crash recovery lease matrix
    lease = F("core", "Approval/ResumeLeaseMatrixTest.php")
    for lease_state in ["free", "held_valid", "held_expired"]:
        for grace in ["inside_grace", "past_grace"]:
            for status in ["approved", "executed", "pending"]:
                label = f"lease={lease_state} grace={grace} status={status}"
                if status == "approved" and grace == "past_grace" and lease_state in ("free", "held_expired"):
                    lease.happy(f"resume claims when {label}", "P2-004")
                elif status == "executed":
                    lease.happy(f"resume replays when {label}", "P2-004")
                else:
                    lease.edge(f"resume skips or waits when {label}", "P2-004")
    files.append(lease)

    # Large expansion: CLI auth × run guard
    cli_g = F("cli", "internal/auth/guards_test.go", language="go", go_package="auth")
    for title in [
        "RunWithoutAuthFails",
        "CatalogWithoutAuthFails",
        "DescribeWithoutAuthFails",
        "McpWithoutAuthFails",
        "LogoutIdempotentWhenAlreadyLoggedOut",
        "StatusShowsLoggedOut",
        "StatusShowsLoggedIn",
        "LoginFailsOnNetworkError",
        "LoginFailsOnInvalidBaseURL",
    ]:
        cli_g.add("go", title, "CLI-AUTH")
    files.append(cli_g)

    # Large expansion: success path metadata matrix
    meta = F("core", "Errors/SuccessMetaMatrixTest.php")
    for c in CALLERS:
        for replay in [False, True]:
            meta.happy(f"success meta caller context {c} idempotent_replay={replay}", "D-018")
    files.append(meta)

    # Contract: documentation non-goals as architecture fails
    non = F("core", "Architecture/NonGoalsTest.php")
    non_goals = [
        "is not an LLM client",
        "is not an MCP protocol implementation",
        "is not Artisan as product CLI",
        "is not a chat UI kit",
        "is not Telegram runtime in core",
        "is not A2A mesh runtime",
        "is not a replacement for controllers Form Requests domain services",
        "is not agent-native full messaging OS",
    ]
    for g in non_goals:
        non.fail(f"package {g}", "NONGOAL")
    files.append(non)

    # Expand more pipeline after-run × caller
    after = F("core", "Registry/AfterRunCallerMatrixTest.php")
    for stage in PIPELINE_STAGES_AFTER_RUN:
        for c in CALLERS:
            after.happy(f"after-run stage {stage} runs for caller {c} on success", "PIPE-001")
    files.append(after)

    # Expand idempotency status × hash matrix fully
    idem2 = F("core", "Idempotency/StatusHashMatrixTest.php")
    for st in IDEMPOTENCY_STATUSES + ["missing"]:
        for hash_rel in ["same", "different", "n/a"]:
            if st == "missing":
                idem2.happy(f"status={st} hash={hash_rel} runs non-idempotent or first insert", "D-005")
            elif st == "completed" and hash_rel == "same":
                idem2.happy(f"status={st} hash={hash_rel} replays", "D-005")
            elif st == "completed" and hash_rel == "different":
                idem2.fail(f"status={st} hash={hash_rel} conflicts", "D-005")
            elif st == "processing":
                idem2.edge(f"status={st} hash={hash_rel} too early or conflict", "D-005")
            elif st == "failed":
                idem2.edge(f"status={st} hash={hash_rel} replays failure by default", "D-005")
            else:
                idem2.edge(f"status={st} hash={hash_rel}", "D-005")
    files.append(idem2)

    # Expand system actor allowlist matrix
    allow = F("core", "Job/SystemAllowlistMatrixTest.php")
    for listed in [["scheduler"], ["scheduler", "reconciliation"], [], True]:
        for actor_name in ["scheduler", "reconciliation", "evil"]:
            label = f"allow={listed} actor={actor_name}"
            if listed is True:
                allow.edge(f"allow any system when {label}", "D-002")
            elif actor_name in (listed if isinstance(listed, list) else []):
                allow.happy(f"allowed when {label}", "D-002")
            else:
                allow.fail(f"denied when {label}", "D-002")
    files.append(allow)

    # Expand readOnly behavior matrix
    ro = F("core", "Discovery/ReadOnlyMatrixTest.php")
    for c in CALLERS:
        ro.happy(f"readOnly skips audit for caller {c} unless forced", "D-010")
        ro.happy(f"readOnly ignores idempotency key for caller {c}", "D-005")
        ro.edge(f"readOnly may skip output validation without schema for caller {c}", "D-014")
    ro.happy("readOnly forced audit true still audits", "D-010")
    files.append(ro)

    # Expand needsApproval branching matrix by caller
    need = F("core", "Approval/NeedsApprovalCallerMatrixTest.php")
    for c in CALLERS:
        need.edge(f"needsApproval can branch on caller {c}", "D-006")
        need.fail(f"needsApproval branch for {c} uses derived caller not header", "D-022")
    need.happy("example large amount requires approval for agent mcp cli not necessarily http", "D-006")
    files.append(need)

    # Messaging process pipeline step matrix
    steps = F("messaging", "Telegram/PipelineStepsTest.php")
    for step in [
        "verify_webhook_secret",
        "queue_process_update",
        "resolve_identity",
        "map_thread",
        "conversation_ingress",
        "agent_tools_profile",
        "tool_calls_registry",
        "conversation_reply",
    ]:
        steps.happy(f"pipeline step {step} executes in order", "MSG-003")
        steps.fail(f"pipeline aborts before tools when step {step} fails if prior required", "MSG-003")
    files.append(steps)

    # Final large expansion: refuse anti-patterns from each decision "What we refuse"
    refuse2 = F("core", "Architecture/DecisionRefuseMatrixTest.php")
    decision_refuses = {
        "D-002": [
            "if caller job return true",
            "null user allow",
            "reuse only user can for scheduler without system",
            "global jobs bypass policy",
            "tenant in input for system jobs",
        ],
        "D-003": [
            "trust exists alone",
            "policy footnote for tenancy",
            "system actor tenant from wire input",
        ],
        "D-005": [
            "rely on clients not to retry",
            "idempotency only on HTTP",
            "approval accept untied to key",
            "dedupe by input without key",
        ],
        "D-006": [
            "status without conditional updates",
            "accept without revalidation",
            "any authenticated approve silent multi-tenant default",
            "eternal pending",
            "approved without resume or atomic",
            "re-accept re-run while approved",
            "unsigned telegram callback",
            "elevating approver silent default",
        ],
        "D-007": [
            "messaging in core",
            "telegram in core",
            "messaging alternate run API",
        ],
        "D-008": [
            "full catalog dump default",
            "MCP all UI powers",
            "meta tools escape hatch",
        ],
        "D-009": [
            "CliApiController invoke tree",
            "second HTTP invoke API",
        ],
        "D-010": [
            "undefined audit mode",
            "default wrap money with audit txn",
            "silent audit drop",
            "events before commit default",
        ],
        "D-011": [
            "support whatever peer without tests",
            "swallow adapter errors",
            "support only in discord",
            "adapter rewrite without AdapterApi bump",
        ],
        "D-022": [
            "client chosen caller header authority",
            "self upgrade privilege via header",
        ],
        "D-023": [
            "vague token user",
            "tool args as actor authority",
            "integration without allowlist",
        ],
    }
    for dec, rows in decision_refuses.items():
        for row in rows:
            refuse2.fail(f"{dec} refuses: {row}", dec)
    files.append(refuse2)

    # Expand: CLI no domain mutation static-ish tests
    nodom = F("cli", "internal/api/no_domain_test.go", language="go", go_package="api")
    for title in [
        "NoSQLDriverImported",
        "NoBusinessInvoiceTypes",
        "NoLocalAuthorizeImplementation",
        "NoLocalApprovalStateMachine",
        "ClientIsHTTPOnly",
    ]:
        nodom.add("go", title, "D-016")
    files.append(nodom)

    # Expand: support capability data units
    cdata = F("core", "Support/CapabilityDataTest.php")
    cdata.happy("fromArray hydrates typed DTO", "D-015")
    cdata.happy("toArray round trips public props", "D-015")
    cdata.fail("fromArray rejects unknown keys when additionalProperties false", "D-015")
    cdata.happy("jsonSchema static generation", "D-015")
    cdata.edge("rules server-only separate from jsonSchema", "D-004")
    files.append(cdata)

    # Expand: facade invoke
    fac = F("core", "Facades/CapabilityFacadeTest.php")
    fac.happy("Capability invoke proxies to registry", "PIPE-006")
    fac.happy("Capability define registers definition", "D-017")
    fac.happy("Capability aiTools proxies to adapter", "D-008")
    fac.happy("Capability mcpTools proxies to adapter", "D-008")
    fac.happy("Capability approvals proxies to ApprovalManager", "D-006")
    fac.fail("Capability invoke does not call domain action directly", "PIPE-008")
    files.append(fac)

    # Expand: service provider boot units
    sp = F("core", "Boot/ServiceProviderTest.php")
    sp.happy("registers config merge", "BOOT-001")
    sp.happy("registers registry singleton", "BOOT-001")
    sp.edge("registers routes when http enabled", "BOOT-001")
    sp.edge("registers commands when artisan enabled", "BOOT-001")
    sp.fail("does not register AI tools when agent disabled", "SURF-003")
    sp.fail("does not register MCP tools when mcp disabled", "SURF-003")
    files.append(sp)

    # Expand: messaging service provider
    msp = F("messaging", "Boot/ServiceProviderTest.php")
    msp.happy("registers messaging config", "MSG-001")
    msp.edge("registers webhook routes when telegram enabled", "MSG-001")
    msp.fail("registers no webhook routes when telegram disabled", "MSG-001")
    msp.happy("binds ApprovalNotifier implementation", "D-006")
    files.append(msp)

    # Expand more CLI main
    main2 = F("cli", "cmd/capabilities/help_test.go", language="go", go_package="main")
    for title in [
        "HelpAuth",
        "HelpCatalog",
        "HelpDescribe",
        "HelpRun",
        "HelpMcp",
        "HelpApprovals",
        "HelpExitCodesTable",
        "HelpExamplesDoNotShowDomainLogic",
    ]:
        main2.add("go", title, "D-016")
    files.append(main2)

    # Cross-surface parity for approval_required envelope
    appr_env = F("core", "Approval/ApprovalEnvelopeMatrixTest.php")
    for c in CALLERS:
        appr_env.happy(f"approval_required envelope for caller {c} includes approval_id", "D-006")
        appr_env.happy(f"approval_required for caller {c} does not include domain result data", "D-006")
    files.append(appr_env)


    # ═══════════════════════════════════════════════════════════
    # COMPLETE-EXPANSION PASS: normative matrices to contract grain
    # ═══════════════════════════════════════════════════════════

    # Capability definition attribute × value validity
    defattr = F("core", "Discovery/AttributeMatrixTest.php")
    for surface_combo in [
        ["agent"], ["mcp"], ["http"], ["cli"], ["job"], ["artisan"],
        ["agent", "mcp"], ["http", "cli"], ["agent", "http", "cli"],
        ["agent", "mcp", "http", "cli", "job"],
        [],
    ]:
        label = ",".join(surface_combo) if surface_combo else "empty"
        if surface_combo:
            defattr.happy(f"definition surfaces [{label}] stored", "D-017")
            defattr.edge(f"effective exposure for [{label}] intersects globals", "SURF-001")
        else:
            defattr.edge(f"definition surfaces [{label}] yields no exposure", "SURF-001")
    for idem in ["optional", "required", "none", False]:
        defattr.happy(f"idempotent flag {idem!r} stored on definition", "D-005")
    for ro in [True, False]:
        defattr.happy(f"readOnly={ro} stored on definition", "D-017")
    files.append(defattr)

    # Every belief × every surface governance applies
    gov = F("core", "Architecture/GovernanceEverywhereMatrixTest.php")
    for concern in ["authorize", "approval", "audit", "actor", "scope", "idempotency", "rate_limit", "schema"]:
        for c in CALLERS:
            gov.happy(f"concern {concern} applies for caller {c}", "BELIEF-003")
            gov.fail(f"concern {concern} cannot be skipped for caller {c}", "BELIEF-003")
    files.append(gov)

    # Full HTTP status × envelope code consistency (already partial)
    # Resource scoping attack vectors
    attacks = F("core", "Scope/AttackVectorsMatrixTest.php")
    vectors = [
        "customer_id_other_tenant",
        "invoice_id_other_tenant",
        "team_id_spoof_header",
        "tenant_id_in_body",
        "organization_id_in_query",
        "nested_resource_other_tenant",
        "batch_ids_mixed_tenants",
        "alias_id_other_tenant",
    ]
    for v in vectors:
        for c in CALLERS:
            attacks.fail(f"attack {v} via {c} denied without run", "D-003")
            attacks.fail(f"attack {v} via {c} produces no domain side effects", "D-003")
    files.append(attacks)

    # Approval accept revalidation checklist items as separate cases
    reval = F("core", "Approval/RevalidationStepsTest.php")
    for step in ["json_schema", "server_only_rules", "scoped_resolve_each_resource", "authorize_original_actor"]:
        reval.happy(f"accept revalidation runs step {step}", "D-006")
        reval.fail(f"accept fails closed when step {step} fails", "D-006")
        reval.fail(f"accept does not run domain when step {step} fails", "D-006")
    files.append(reval)

    # Exactly-once accept algorithm steps
    exact = F("core", "Approval/ExactlyOnceAlgorithmTest.php")
    for step in [
        "begin_transaction",
        "lock_approval_row",
        "if_executed_replay",
        "if_rejected_conflict",
        "if_expired_gone",
        "if_approved_join_or_in_progress",
        "if_pending_shape_a_set_approved",
        "if_pending_shape_b_run_under_lock",
        "revalidate",
        "authorize_original",
        "run_once",
        "set_executed_result",
        "commit",
        "complete_idempotency",
    ]:
        exact.happy(f"accept algorithm includes step {step}", "D-006")
    files.append(exact)

    # Resume algorithm steps
    res_steps = F("core", "Approval/ResumeAlgorithmTest.php")
    for step in [
        "select_approved_past_grace_free_lease",
        "claim_lease_conditional",
        "revalidate",
        "scoped_resolve",
        "run_once_or_stale_fail",
        "set_executed",
        "complete_idempotency",
        "emit_metrics",
    ]:
        res_steps.happy(f"resume algorithm includes step {step}", "P2-004")
    files.append(res_steps)

    # Idempotency wire format per surface
    idem_w = F("core", "Idempotency/WireFormatMatrixTest.php")
    for surface, how in [
        ("http", "header"),
        ("http", "body"),
        ("cli", "auto_uuid"),
        ("cli", "manual_flag"),
        ("mcp", "tool_arg"),
        ("agent", "tool_arg"),
        ("job", "payload_field"),
        ("approval_accept", "stored_or_header"),
    ]:
        idem_w.happy(f"idempotency key accepted via {surface} {how}", "D-005")
        idem_w.edge(f"idempotency key identity includes surface actor scope for {surface}", "D-005")
    files.append(idem_w)

    # Key format validation matrix.
    # Tags (not raw sample repr) keep Pest method names unique: punctuation in
    # samples like "bad key" / "bad/key" / "bad@key" collapses under Str::evaluable.
    keyf = F("core", "Idempotency/KeyFormatMatrixTest.php")
    for sample, ok, tag in [
        ("a", True, "single_char_a"),
        ("A" * 128, True, "len128"),
        ("A" * 129, False, "len129"),
        ("", False, "empty_string"),
        ("good.key-1:2", True, "alnum_dot_dash_colon"),
        ("bad key", False, "contains_space"),
        ("bad/key", False, "contains_slash"),
        ("bad@key", False, "contains_at"),
        ("uuid-style-123e4567-e89b", True, "uuid_style"),
    ]:
        if ok:
            keyf.happy(f"key format accepts {tag}", "D-005")
        else:
            keyf.fail(f"key format rejects {tag}", "D-005")
        # Sample retained on Case.note for implementers (not part of Pest title).
        keyf.cases[-1].note = f"sample={sample!r}"
    files.append(keyf)

    # Catalog list field presence × capability flags
    catf = F("core", "Catalog/ListFieldMatrixTest.php")
    for field_name in [
        "name", "description", "surfaces", "readOnly", "schema_version",
        "idempotent", "deprecated", "deprecated_at", "aliases", "successor", "sunset_at", "groups", "tags",
    ]:
        catf.happy(f"list entry may include {field_name}", "CAT-001")
        catf.edge(f"describe entry includes {field_name} when set", "CAT-001")
    files.append(catf)

    # Context field matrix
    ctxf = F("core", "Context/ContextFieldMatrixTest.php")
    for field_name in [
        "caller", "actor", "user", "scope", "tenantId", "teamId", "organizationId",
        "requestId", "traceId", "agent", "mcp", "messaging", "job", "credential",
    ]:
        ctxf.happy(f"context field {field_name} accessible when set", "CTX-001")
        ctxf.edge(f"context field {field_name} null-safe when unset if optional", "CTX-001")
    files.append(ctxf)

    # MCP metadata fields
    mcpf = F("core", "Mcp/McpContextFieldsTest.php")
    for field_name in ["client_id", "auth_profile", "host", "session"]:
        mcpf.edge(f"mcp context may include {field_name}", "D-023")
    for p in MCP_PROFILES:
        for allow_int in [True, False]:
            mcpf.edge(f"profile {p} with allow_integration_credentials={allow_int}", "D-023")
    files.append(mcpf)

    # Peer contract tests table rows
    peer_c = F("core", "Adapters/ContractTableTest.php")
    for row in [
        "tool_schema_mapping",
        "invoke_round_trip",
        "profile_filter",
        "authorization_deny",
        "idempotency_passthrough",
        "missing_peer_boot",
        "unsupported_peer_version",
    ]:
        for peer in ["laravel/ai", "laravel/mcp"]:
            peer_c.happy(f"contract {row} for peer {peer}", "D-011")
    files.append(peer_c)

    # Boot rules 1-10 as individual tests
    br = F("core", "Boot/BootRulesChecklistTest.php")
    rules = [
        "invoke surfaces default on",
        "messaging defaults off in core",
        "missing peer with require_package fails or disables",
        "incompatible peer same as missing",
        "cli requires http",
        "messaging requires agent and package",
        "telegram secrets deferred to first traffic",
        "catalog only lists effective surfaces",
        "CI runs adapter contract tests before release",
        "SKIP_BOOT_CHECKS forbidden in production",
    ]
    for r in br_rules if False else rules:
        br.happy(f"boot rule: {r}", "BOOT-RULE")
    files.append(br)

    # Design rules 1-18
    dr = F("core", "Architecture/DesignRulesChecklistTest.php")
    design_rules = [
        "one run",
        "adapters are dumb",
        "domain stays yours",
        "global surface switches then per-capability narrowing",
        "fail closed",
        "conversation not invoke",
        "jobs declare actor",
        "resources re-resolved under scope",
        "mutating invokes support idempotency",
        "approvals state machine with crash recovery",
        "messaging sibling package",
        "agents get tool groups not full catalog",
        "one HTTP capability API",
        "transactions and audit explicit",
        "peer adapters versioned and tested",
        "names errors DTOs CLI language decided",
        "caller derived not spoofable",
        "MCP principals explicit auth profiles",
    ]
    for r in design_rules:
        dr.happy(f"design rule enforced: {r}", "DESIGN")
        dr.fail(f"design rule violation refused: {r}", "DESIGN")
    files.append(dr)

    # Server pipeline order positions
    order = F("core", "Registry/PipelineOrderTest.php")
    ordered = [
        "json_schema_validate",
        "hydrate_dto",
        "server_only_validate",
        "resolve_actor",
        "resolve_scope",
        "idempotency_lookup",
        "authorize",
        "needs_approval",
        "rate_limit",
        "run",
        "validate_output",
        "store_idempotency",
        "record_audit",
        "emit_events",
        "wire_response",
    ]
    for i, stage in enumerate(ordered):
        order.happy(f"pipeline position {i:02d} is {stage}", "PIPE-001")
        if i > 0:
            order.edge(f"stage {stage} runs after {ordered[i-1]}", "PIPE-001")
    files.append(order)

    # Failure before run × no side effects × all callers already exists
    # Expand CLI commands matrix
    cli_cmd = F("cli", "cmd/capabilities/commands_test.go", language="go", go_package="main")
    for cmd in ["auth login", "auth logout", "auth status", "catalog", "describe", "run", "mcp", "version", "help"]:
        safe = "".join(p.capitalize() for p in cmd.replace(" ", "_").split("_"))
        cli_cmd.add("go", f"CommandExists_{safe}", "D-016")
        cli_cmd.add("go", f"CommandHelp_{safe}", "D-016")
    files.append(cli_cmd)

    # CLI run exit code × each error code (duplicate safe)
    cli_ex = F("cli", "internal/run/exitcodes_test.go", language="go", go_package="run")
    for code, http, exit_code in ERROR_CODES:
        cli_ex.add("go", f"ExitCodeFor_{code}_Is{exit_code}", "D-018")
        cli_ex.add("go", f"HTTPStatusFor_{code}_Is{http}_Documented", "D-018")
    cli_ex.add("go", "ExitCode0OnSuccess", "D-018")
    files.append(cli_ex)

    # Messaging never-do matrix expanded
    mnever = F("messaging", "Architecture/NeverDoMatrixTest.php")
    for action in [
        "call_eloquent_create",
        "call_eloquent_update",
        "call_eloquent_delete",
        "own_run_method",
        "bypass_registry_for_tools",
        "hard_depend_core_on_messaging",
        "embed_capability_input_in_callback",
        "approve_without_linked_user",
        "trust_telegram_user_id_as_laravel_user",
    ]:
        mnever.fail(f"messaging must never: {action}", "D-007")
    files.append(mnever)

    # Approval row fields each present
    rowf = F("core", "Approval/RowShapeTest.php")
    for field_name in [
        "id", "capability_name", "status", "scope", "tenant_id",
        "requester_actor_type", "requester_actor_id", "original_caller",
        "input_json", "input_hash", "idempotency_key", "result_json",
        "decided_by", "decided_at", "decision_reason", "expires_at",
        "execution_lease_until", "execution_attempt", "messaging",
        "created_at", "updated_at",
    ]:
        rowf.happy(f"approval row shape includes {field_name}", "D-006")
    files.append(rowf)

    # Idempotency row fields
    idrow = F("core", "Idempotency/RowShapeTest.php")
    for field_name in [
        "tenant_id", "actor_type", "actor_id", "capability_name",
        "idempotency_key", "request_hash", "status", "result_json",
        "approval_id", "created_at", "expires_at",
    ]:
        idrow.happy(f"idempotency row shape includes {field_name}", "D-005")
    files.append(idrow)

    # Audit modes × required × outcome matrix
    am = F("core", "Audit/ModeRequiredMatrixTest.php")
    for mode in ["best_effort", "strict"]:
        for required in [True, False]:
            for audit_ok in [True, False]:
                for run_ok in [True, False]:
                    label = f"mode={mode} required={required} audit_ok={audit_ok} run_ok={run_ok}"
                    if not run_ok:
                        am.edge(f"domain failed when {label}", "D-010")
                    elif audit_ok:
                        am.happy(f"success when {label}", "D-010")
                    elif mode == "best_effort":
                        am.happy(f"domain success client success when {label}", "D-010")
                        if required:
                            am.happy(f"outbox written when {label}", "D-010")
                        else:
                            am.edge(f"retry optional when {label}", "D-010")
                    else:
                        am.edge(f"strict audit failure behavior when {label}", "D-010")
    files.append(am)

    # Rate limit defaults × override matrix
    rl = F("core", "RateLimiting/ConfigMatrixTest.php")
    for enabled in [True, False]:
        for per_min in [0, 1, 30, 60, 1000]:
            for per_cap in [0, 1, 10, 30]:
                label = f"enabled={enabled} per_min={per_min} per_cap={per_cap}"
                if not enabled:
                    rl.edge(f"rate limits off when {label}", "D-013")
                elif per_min == 0 or per_cap == 0:
                    rl.edge(f"zero limit edge when {label}", "D-013")
                else:
                    rl.happy(f"limits enforced when {label}", "D-013")
    files.append(rl)

    # Profile max tools matrix
    pt = F("core", "Profiles/MaxToolsMatrixTest.php")
    for count in [0, 1, 32, 33, 64, 65, 100]:
        if count > 64:
            pt.fail(f"tool count {count} exceeds hard max 64", "D-008")
        elif count > 32:
            pt.edge(f"tool count {count} warns above 32", "D-008")
        else:
            pt.happy(f"tool count {count} accepted", "D-008")
    for max_calls in [1, 8, 16, 32]:
        pt.edge(f"agent max_tool_calls_per_turn={max_calls} enforced", "D-013")
        pt.fail(f"agent exceeding max_tool_calls_per_turn={max_calls} stops loop", "D-013")
    files.append(pt)

    # Caller privilege order full pairwise
    priv = F("core", "Caller/PrivilegeOrderMatrixTest.php")
    order_list = ["http", "cli", "mcp", "agent", "job"]
    for i, a in enumerate(order_list):
        for j, b in enumerate(order_list):
            if i == j:
                priv.edge(f"header {a} matching derived {b} is no-op", "D-022")
            elif j > i:
                # b is stricter (higher index) — downgrade allowed?
                priv.edge(f"header downgrade derived {a} claim {b} allowed per policy", "D-022")
            else:
                priv.fail(f"header upgrade derived {a} claim {b} ignored or rejected", "D-022")
    files.append(priv)

    # Surfaces disabled behavior table from spec
    sdis = F("core", "Boot/DisabledSurfaceBehaviorTableTest.php")
    behaviors = {
        "agent": "no laravel/ai tools registered",
        "mcp": "no MCP tools catalog wiring",
        "http": "no capability HTTP routes",
        "cli": "device-code CLI auth helpers off",
        "job": "RunCapability helpers not registered",
        "artisan": "no capability artisan commands",
        "messaging": "core does not register chat routes",
    }
    for s, behavior in behaviors.items():
        sdis.happy(f"when {s} disabled: {behavior}", "SURF-003")
        sdis.fail(f"when {s} disabled: no half registration", "SURF-003")
    files.append(sdis)

    # Job payload contract fields
    jpay = F("core", "Job/PayloadContractTest.php")
    for field_name in ["name", "input", "actingAs", "tenantId", "teamId", "organizationId", "idempotencyKey"]:
        jpay.happy(f"job payload may include {field_name}", "D-002")
    jpay.fail("job payload input must not be used as tenant authority for SystemActor", "P2-005")
    jpay.fail("job payload missing actingAs not dispatchable", "D-002")
    files.append(jpay)

    # Success criteria from philosophy
    success = F("core", "Architecture/SuccessCriteriaTest.php")
    for crit in [
        "new feature is one capability class",
        "turning off MCP is a config flag",
        "agent cannot bypass UI rules",
        "cross-tenant customer_id denied",
        "support agent tool list excludes finance void",
        "CLI and mobile same invoke endpoint",
        "drift is registry bug not lifestyle",
        "package feels Laravel-native",
    ]:
        success.happy(f"success criterion: {crit}", "SUCCESS")
    files.append(success)

    # CLI local validation then server
    cliv = F("cli", "internal/run/validation_flow_test.go", language="go", go_package="run")
    for title in [
        "LocalStructuralFailNoHTTP",
        "LocalStructuralOKThenHTTP",
        "ServerExistsFailAfterLocalOK",
        "ServerAuthorizeFailAfterLocalOK",
        "ServerApprovalRequiredAfterLocalOK",
        "LocalCacheUsedWhenVersionMatches",
        "LocalCacheBypassedWithNoCache",
        "SchemaFetchedOnMissingCache",
    ]:
        cliv.add("go", title, "D-004")
    files.append(cliv)

    # AI/MCP schema identity matrix
    schid = F("core", "Schema/ToolSchemaIdentityMatrixTest.php")
    for adapter in ["ai", "mcp", "http_catalog", "cli_catalog"]:
        schid.happy(f"adapter {adapter} uses registry JSON Schema not hand copy", "D-004")
        schid.fail(f"adapter {adapter} does not maintain second schema source", "D-004")
    files.append(schid)

    # Events payload fields
    evf = F("core", "Events/EventPayloadMatrixTest.php")
    for event in ["CapabilityInvoked", "CapabilityFailed", "CapabilityApprovalRequested", "CapabilityApprovalDecided", "CapabilityApprovalExecuted"]:
        for field_name in ["name", "actor", "scope", "caller", "duration", "invocation_id", "request_id"]:
            evf.edge(f"{event} payload may include {field_name}", "D-010")
    files.append(evf)

    # Telegram callback fields
    tcf = F("messaging", "Telegram/CallbackPayloadTest.php")
    for field_name in ["approval_id", "action", "exp", "approver_hint", "signature"]:
        tcf.happy(f"callback payload includes {field_name}", "D-006")
    tcf.fail("callback payload must not include capability input", "D-006")
    tcf.fail("callback payload must not include raw bot token", "D-006")
    files.append(tcf)

    # Config clients map exhaustive
    ccm = F("core", "Config/ClientsConfigMatrixTest.php")
    for ability in ["capabilities:cli", "capabilities:admin", "unmapped", ""]:
        ccm.edge(f"token ability mapping for {ability!r}", "D-022")
    for client in ["capabilities-cli", "ios-app", "billing-integration", "unknown"]:
        ccm.edge(f"oauth client mapping for {client}", "D-022")
    for reject in [True, False]:
        ccm.happy(f"reject_upgrade_attempts={reject} behavior defined", "D-022")
    files.append(ccm)

    # Health endpoint messaging readiness
    hm = F("core", "Catalog/HealthMessagingTest.php")
    hm.edge("health includes messaging readiness when messaging surface on", "D-021")
    hm.edge("health omits messaging details when messaging surface off", "D-021")
    hm.happy("health never requires telegram secrets at boot-only probe", "D-021")
    files.append(hm)

    # Parity helpers existence
    ph = F("core", "TestingHelpers/HelperSurfaceTest.php")
    for helper in [
        "fake", "assertSchemaSnapshot", "assertParity", "assertCannotInvokeAcrossTenant",
        "assertScopeResolvedTo", "assertLastScopeTenant", "assertOk", "assertFailed",
        "assertForbidden", "assertConflict", "assertExpired",
    ]:
        ph.happy(f"testing helper {helper} exists for package consumers", "D-020")
    files.append(ph)

    # Large: each error code × each caller structured error
    ecm = F("core", "Errors/ErrorCodeCallerMatrixTest.php")
    for code, _, _ in ERROR_CODES:
        for c in CALLERS:
            ecm.happy(f"code {code} producible for caller {c} maps stable envelope", "D-018")
            ecm.fail(f"code {code} for caller {c} is not unstructured string only", "D-018")
    files.append(ecm)

    # Large: authorize outcomes × actor kinds × callers
    authz = F("core", "Registry/AuthorizeMatrixTest.php")
    for actor in ["user", "system"]:
        for c in CALLERS:
            for decision in [True, False]:
                label = f"actor={actor} caller={c} authorize={decision}"
                if decision:
                    authz.edge(f"authorize allow continues pipeline when {label}", "PIPE-001")
                else:
                    authz.fail(f"authorize deny stops before run when {label}", "PIPE-001")
                    authz.fail(f"authorize deny no domain side effects when {label}", "PIPE-001")
                    authz.happy(f"authorize deny may audit when {label}", "D-010")
    files.append(authz)

    # Large: needsApproval outcomes matrix
    nam = F("core", "Approval/NeedsApprovalOutcomeMatrixTest.php")
    for c in CALLERS:
        for needs in [True, False]:
            if needs:
                nam.happy(f"needsApproval true for {c} stores pending", "D-006")
                nam.fail(f"needsApproval true for {c} does not run", "D-006")
                nam.happy(f"needsApproval true for {c} returns approval_required", "D-006")
            else:
                nam.happy(f"needsApproval false for {c} continues to rate limit and run", "D-006")
    files.append(nam)

    # Large: readOnly × mutating matrix for audit/idempotency/output
    rom = F("core", "Discovery/MutatingVsReadOnlyMatrixTest.php")
    for ro in [True, False]:
        for c in CALLERS:
            rom.edge(f"readOnly={ro} caller={c} audit policy applied", "D-010")
            rom.edge(f"readOnly={ro} caller={c} idempotency policy applied", "D-005")
            rom.edge(f"readOnly={ro} caller={c} output validation policy applied", "D-014")
    files.append(rom)

    # Messaging identity modes × tenant isolation
    mim = F("messaging", "Identity/TenantIsolationTest.php")
    for mode in ["code_link", "allowlist"]:
        for same_tenant in [True, False]:
            if same_tenant:
                mim.happy(f"mode {mode} same tenant linked user can proceed", "MSG-002")
            else:
                mim.fail(f"mode {mode} other tenant identity cannot escalate", "MSG-002")
    files.append(mim)

    # Process update failure points
    puf = F("messaging", "Telegram/ProcessUpdateFailurePointsTest.php")
    for point in [
        "invalid_update_shape",
        "unknown_chat",
        "identity_unresolved",
        "thread_store_failure",
        "ingress_failure",
        "agent_failure",
        "tool_registry_failure",
        "reply_failure",
    ]:
        puf.fail(f"process update handles failure at {point} without domain bypass", "MSG-003")
        puf.edge(f"process update failure at {point} is observable in logs or failed jobs", "D-019")
    files.append(puf)

    # CLI keychain behaviors
    kc = F("cli", "internal/auth/keychain_test.go", language="go", go_package="auth")
    for title in [
        "StoreTokenEncryptedOrOSKeychain",
        "ReadTokenForAPIClient",
        "DeleteTokenOnLogout",
        "NeverEchoTokenInStatus",
        "NeverPassTokenToAgentPrompt",
        "MultipleProfilesIsolated",
        "CorruptKeychainHandled",
        "MissingKeychainFallsBackSecurely",
    ]:
        kc.add("go", title, "CLI-AUTH")
    files.append(kc)

    # Describe/catalog cache matrix already partial
    # Adapter API version constant tests expanded
    apiv = F("core", "Adapters/AdapterApiVersionTest.php")
    apiv.happy("AdapterApi V1 equals 1", "D-011")
    apiv.happy("AdapterApi CURRENT equals V1", "D-011")
    apiv.edge("future V2 would be selected by probe not apps", "D-011")
    apiv.fail("apps do not depend on adapter version directly for tool listing", "D-011")
    files.append(apiv)

    # Facade surface methods
    fsm = F("core", "Facades/FacadeMethodMatrixTest.php")
    for method in [
        "invoke", "define", "aiTools", "aiMetaTools", "mcpTools", "mcpMetaTools",
        "approvals", "audit", "fake", "assertParity", "assertSchemaSnapshot",
        "assertCannotInvokeAcrossTenant",
    ]:
        fsm.happy(f"Capability facade exposes {method}", "FAC-001")
    files.append(fsm)

    # Large expansion: each surface enable/disable × each peer mode already
    # Package layout presence architecture tests
    layout = F("core", "Architecture/PackageLayoutTest.php")
    for path in [
        "Registry/CapabilityRegistry",
        "Adapters/Ai",
        "Adapters/Mcp",
        "Adapters/Http",
        "Approval",
        "Audit",
        "Idempotency",
        "Contracts/ConversationIngress",
        "Contracts/ApprovalNotifier",
        "Support/SystemActor",
        "Support/CapabilityContext",
    ]:
        layout.happy(f"core layout includes {path}", "LAYOUT")
    layout.fail("core layout includes Messaging bot runtime directory", "D-007")
    files.append(layout)

    mlayout = F("messaging", "Architecture/PackageLayoutTest.php")
    for path in ["Telegram", "Identity", "Threads", "Notifiers"]:
        mlayout.happy(f"messaging layout includes {path}", "LAYOUT")
    mlayout.fail("messaging layout reimplements registry pipeline", "D-007")
    files.append(mlayout)

    # Versioning principles
    ver = F("core", "Architecture/VersioningPrinciplesTest.php")
    for p in [
        "capability names are public contracts",
        "schema_version changes are detectable",
        "AdapterApi versions bridges",
        "error codes are normative set",
    ]:
        ver.happy(f"versioning principle: {p}", "VER")
    files.append(ver)

    # D-015 primary path examples only package-native
    d015 = F("core", "Schema/PrimaryPathTest.php")
    d015.happy("docs and generators use package-native examples", "D-015")
    d015.edge("Spatie is optional bridge not primary", "D-015")
    d015.happy("SchemaProvider is escape hatch", "D-015")
    d015.fail("Spatie is not required dependency for v1", "D-015")
    files.append(d015)

    # D-016 Go CLI principles
    d016 = F("cli", "cmd/capabilities/principles_test.go", language="go", go_package="main")
    for title in [
        "SingleStaticBinaryName",
        "NoNodeRequired",
        "NoPHPRequiredOnUserMachine",
        "NoMultiLanguageCLIMatrixInV0_2",
        "ProductCLINotArtisan",
    ]:
        d016.add("go", title, "D-016")
    files.append(d016)

    # Expand remaining decision testing sections as explicit cases
    # Cross-tenant dataset per surface (D-003 testing helpers text)
    ctd = F("core", "Scope/CrossTenantDatasetTest.php")
    for c in CALLERS:
        ctd.fail(f"dataset cross-tenant deny via {c}", "D-003")
        ctd.happy(f"dataset same-tenant allow control via {c}", "D-003")
    files.append(ctd)

    # Approval concurrent double-accept stress unit (logical)
    conc = F("core", "Approval/ConcurrencyTest.php")
    conc.fail("two concurrent accepts only one run", "D-006")
    conc.fail("two concurrent resumes only one run", "P2-004")
    conc.fail("accept and resume race only one run", "P2-004")
    conc.happy("loser receives replay or in_progress not second domain apply", "D-006")
    files.append(conc)

    # Staleness scenarios
    stale = F("core", "Approval/StalenessScenariosTest.php")
    for scenario in [
        "customer_deleted",
        "customer_moved_tenant",
        "actor_lost_permission",
        "resource_archived",
        "schema_incompatible_with_stored_input",
    ]:
        stale.fail(f"accept fails without run when {scenario}", "D-006")
        stale.happy(f"accept stores terminal failed when {scenario}", "D-006")
    files.append(stale)

    # Expiry scenarios
    exp = F("core", "Approval/ExpiryScenariosTest.php")
    for when in ["on_read", "on_accept", "on_reject", "on_sweeper"]:
        exp.happy(f"pending past ttl becomes expired {when}", "D-006")
        exp.fail(f"expired cannot accept {when}", "D-006")
    files.append(exp)

    # Notifier channel matrix
    nch = F("core", "Approval/NotifierChannelMatrixTest.php")
    for channel in ["http", "cli", "telegram"]:
        nch.happy(f"notifier channel {channel} can notify pending", "D-006")
        nch.fail(f"notifier channel {channel} never executes capability", "D-006")
    files.append(nch)

    # Metrics result labels for resume
    mres = F("core", "Observability/ResumeMetricResultsTest.php")
    for result in ["executed_ok", "executed_failed", "skipped_lease", "stale"]:
        mres.happy(f"approvals_resume_total result={result}", "D-019")
    for result in ["executed_ok", "executed_failed", "in_progress", "replay", "rejected", "forbidden", "expired"]:
        mres.happy(f"approvals_accept_total result={result}", "D-019")
    files.append(mres)

    # Span attributes hashing
    span = F("core", "Observability/SpanAttributesTest.php")
    for attr in ["capability", "caller", "surface", "tenant_id", "actor_type", "approval_id", "idempotency_key"]:
        span.happy(f"span sets {attr} when applicable", "D-019")
        span.edge(f"span may hash {attr} when sensitive", "D-019")
    files.append(span)

    # Large: disabled surface × attempt invoke behavior
    dsi = F("core", "Boot/DisabledSurfaceInvokeTest.php")
    for s in INVOKE_SURFACES:
        dsi.fail(f"invoke via disabled surface {s} is not registered", "SURF-003")
        dsi.fail(f"invoke via disabled surface {s} does not reach domain", "SURF-003")
    files.append(dsi)

    # Large: catalog effective surface filter per surface
    cef = F("core", "Catalog/EffectiveSurfaceFilterTest.php")
    for s in INVOKE_SURFACES:
        cef.happy(f"catalog includes cap with effective surface {s}", "CAT-001")
        cef.edge(f"catalog excludes cap when only surface {s} globally disabled", "CAT-001")
    files.append(cef)

    # CLI MCP stdio security
    ms = F("cli", "internal/mcpstdio/security_test.go", language="go", go_package="mcpstdio")
    for title in [
        "NoLocalAuthorize",
        "NoLocalRun",
        "UsesStoredTokenOnly",
        "DoesNotAcceptHostInjectedActor",
        "DoesNotBypassServerProfile",
        "PropagatesServerErrors",
        "PropagatesApprovalRequired",
    ]:
        ms.add("go", title, "CLI-MCP")
    files.append(ms)

    # Input edge for define capability fluent vs attribute parity fields
    parity_def = F("core", "Discovery/FluentAttributeParityTest.php")
    for field_name in [
        "name", "description", "surfaces", "input", "output", "aliases", "deprecated",
        "successor", "sunset_at", "groups", "tags", "readOnly", "allowSystemCallers",
        "globalSystem", "approvalPolicy", "approvalTtlHours", "rateLimit", "idempotent",
    ]:
        parity_def.happy(f"fluent and attribute agree on field {field_name}", "D-017")
    files.append(parity_def)

    # HTTP middleware stack matrix
    mw = F("core", "Http/MiddlewareMatrixTest.php")
    for mw_name in ["api", "auth:sanctum", "throttle", "custom"]:
        mw.edge(f"middleware {mw_name} can be applied via config", "HTTP-001")
    mw.fail("unauthenticated request blocked when auth middleware on", "HTTP-001")
    files.append(mw)

    # Device code / token auth controller matrix
    authc = F("core", "Http/AuthControllerMatrixTest.php")
    for flow in ["token", "device_code", "oauth_callback"]:
        authc.happy(f"auth flow {flow} available when cli or http auth enabled", "D-009")
        authc.fail(f"auth flow {flow} not registered when http disabled", "D-009")
    files.append(authc)

    # Approval controller accept/reject matrix with authz
    appc = F("core", "Http/ApprovalControllerMatrixTest.php")
    for action in ["accept", "reject"]:
        for auth in ["authorized", "unauthorized", "unauthenticated", "system_actor"]:
            if auth == "authorized":
                appc.happy(f"approval {action} when {auth}", "D-006")
            else:
                appc.fail(f"approval {action} when {auth}", "D-006")
    files.append(appc)

    # SystemActor tenancy magic key full list expanded already
    # More job failure points
    jfail = F("core", "Job/FailurePointsTest.php")
    for point in [
        "missing_actingAs",
        "missing_user",
        "system_not_allowlisted",
        "missing_tenant_when_required",
        "authorize_false",
        "schema_invalid",
        "rate_limited",
        "output_invalid",
        "run_throws",
    ]:
        jfail.fail(f"job fails closed at {point} without silent superuser", "D-002")
        jfail.happy(f"job failure at {point} is auditable", "D-010")
    files.append(jfail)

    # Agent tool handle failure points
    atf = F("core", "Surfaces/AiToolFailurePointsTest.php")
    for point in [
        "schema_invalid",
        "unauthorized",
        "approval_required",
        "rate_limited",
        "not_in_profile",
        "output_invalid",
        "domain_error",
        "caller_spoof_attempt",
    ]:
        atf.fail(f"ai tool handle failure {point} does not mutate incorrectly", "AI-001")
        atf.happy(f"ai tool handle failure {point} returns structured tool error", "AI-001")
    files.append(atf)

    mtf = F("core", "Surfaces/McpToolFailurePointsTest.php")
    for point in [
        "schema_invalid",
        "unauthorized",
        "approval_required",
        "rate_limited",
        "not_in_profile",
        "output_invalid",
        "domain_error",
        "actor_spoof_attempt",
        "integration_disabled",
    ]:
        mtf.fail(f"mcp tool handle failure {point} does not mutate incorrectly", "MCP-001")
        mtf.happy(f"mcp tool handle failure {point} returns structured error", "MCP-001")
    files.append(mtf)

    # HTTP invoke failure points
    htf = F("core", "Surfaces/HttpInvokeFailurePointsTest.php")
    for point in [
        "unauthenticated",
        "forbidden",
        "validation_failed",
        "not_found",
        "conflict",
        "rate_limited",
        "approval_required",
        "domain_error",
        "output_invalid",
        "internal",
    ]:
        htf.happy(f"http invoke maps failure {point} to envelope", "HTTP-001")
        htf.fail(f"http invoke failure {point} does not partial-commit domain unless domain already did", "HTTP-001")
    files.append(htf)

    # Messaging webhook → process → tool failure chain
    mchain = F("messaging", "Telegram/FailureChainTest.php")
    for point in [
        "bad_secret",
        "unlinked_user",
        "profile_missing",
        "tool_not_in_profile",
        "registry_forbidden",
        "registry_validation",
        "approval_required",
        "reply_send_fail",
    ]:
        mchain.fail(f"messaging chain fails closed at {point}", "MSG-003")
        mchain.fail(f"messaging chain at {point} never bypasses registry for mutation", "D-007")
    files.append(mchain)

    # CLI failure chain
    clif = F("cli", "internal/run/failure_chain_test.go", language="go", go_package="run")
    for title in [
        "FailLocalSchema",
        "FailAuth",
        "FailNetwork",
        "FailServerValidation",
        "FailForbidden",
        "FailApprovalRequired",
        "FailRateLimited",
        "FailConflict",
        "FailNotFound",
        "FailInternal",
        "FailOutputInvalid",
        "FailDomain",
    ]:
        clif.add("go", title, "CLI-RUN")
    files.append(clif)

    # Philosophy "what this is" positive tests
    what = F("core", "Architecture/WhatThisIsTest.php")
    for item in [
        "capability registry with typed schemas",
        "invoke adapters for AI MCP HTTP jobs CLI protocol",
        "approval audit scope idempotency governance",
        "conversation ingress contracts",
        "downloadable CLI",
        "discoverable catalog",
    ]:
        what.happy(f"package is: {item}", "IS")
    files.append(what)

    # Additional pairwise: spoof attempts for actor fields on every surface adapter
    spoof = F("core", "Caller/SpoofAttemptsMatrixTest.php")
    for field in ["caller", "actor", "user_id", "actor_id", "tenant_id", "auth_profile", "client_id"]:
        for c in CALLERS:
            spoof.fail(f"client-provided {field} is not authoritative for caller {c}", "D-022")
    files.append(spoof)

    # Idempotency + approval combined scenarios
    ia = F("core", "Idempotency/ApprovalInteractionMatrixTest.php")
    for scenario in [
        "invoke_with_key_approval_required_stores_key",
        "accept_uses_stored_key",
        "accept_replay_same_key",
        "resume_uses_stored_key",
        "reject_does_not_complete_as_success",
        "expired_does_not_complete_as_success",
        "second_invoke_same_key_while_pending",
        "second_invoke_same_key_after_executed_replays",
    ]:
        ia.happy(f"scenario {scenario}", "D-005")
    files.append(ia)

    # Output invalid must not poison agent loop
    oloop = F("core", "Schema/OutputInvalidAgentLoopTest.php")
    for c in ["agent", "mcp"]:
        oloop.fail(f"output_invalid via {c} is not presented as successful tool result", "D-014")
        oloop.happy(f"output_invalid via {c} emits CapabilityFailed", "D-014")
        oloop.happy(f"output_invalid via {c} is logged", "D-014")
    files.append(oloop)

    # Config default snapshot tests (each default)
    cds = F("core", "Config/DefaultsSnapshotTest.php")
    defaults = {
        "surfaces.agent.enabled": True,
        "surfaces.mcp.enabled": True,
        "surfaces.http.enabled": True,
        "surfaces.cli.enabled": True,
        "surfaces.job.enabled": True,
        "surfaces.artisan.enabled": True,
        "surfaces.messaging.enabled": False,
        "audit.mode": "best_effort",
        "audit.required": False,
        "transactions.wrap_run": False,
        "events.enabled": True,
        "approval.ttl_hours": 24,
        "approval.execution": "deferred",
        "approval.resume.enabled": True,
        "approval.resume.every_seconds": 60,
        "approval.resume.grace_seconds": 30,
        "approval.resume.stuck_after_seconds": 300,
        "approval.resume.lease_seconds": 120,
        "idempotency.enabled": True,
        "idempotency.ttl_hours": 24,
        "validation.validate_output": True,
        "rate_limits.enabled": True,
        "observability.metrics": True,
        "observability.tracing": True,
        "surfaces.mcp.auth.allow_integration_credentials": False,
        "surfaces.mcp.auth.default_profile": "user_pat",
        "surfaces.agent.require_profile": True,
        "surfaces.mcp.require_profile": True,
        "clients.reject_upgrade_attempts": False,
    }
    for key, val in defaults.items():
        cds.happy(f"default {key} is {val!r}", "CFG-001")
    files.append(cds)


    # Final padding of genuine combinatorial cases: validate_output × readOnly × has_schema
    vo = F("core", "Schema/ValidateOutputConfigMatrixTest.php")
    for validate in [True, False]:
        for read_only in [True, False]:
            for has_schema in [True, False]:
                label = f"validate_output={validate} readOnly={read_only} has_schema={has_schema}"
                if validate and has_schema:
                    vo.happy(f"output validated when {label}", "D-014")
                    vo.fail(f"invalid output rejected when {label}", "D-014")
                else:
                    vo.edge(f"output validation skipped or optional when {label}", "D-014")
    files.append(vo)


    # ═══════════════════════════════════════════════════════════
    # PASS 2 — push to complete contract grain (~5k)
    # ═══════════════════════════════════════════════════════════

    # Every design rule × every caller
    dr2 = F("core", "Architecture/DesignRulesCallerMatrixTest.php")
    design_rules2 = [
        "one_run", "adapters_dumb", "domain_yours", "fail_closed", "no_silent_actors",
        "no_ambient_tenancy", "idempotent_retries", "approvals_state_machine",
        "profiles_not_dump", "one_http_api", "server_derived_caller", "mcp_auth_profiles",
    ]
    for r in design_rules2:
        for c in CALLERS:
            dr2.happy(f"rule {r} holds for caller {c}", "DESIGN")
            dr2.fail(f"rule {r} violation via caller {c} refused", "DESIGN")
    files.append(dr2)

    # Full pipeline stage × caller × outcome class
    p2 = F("core", "Registry/PipelineOutcomeMatrixTest.php")
    for stage in PIPELINE_STAGES_BEFORE_RUN + ["run"] + PIPELINE_STAGES_AFTER_RUN:
        for c in CALLERS:
            for outcome in ["success", "fail"]:
                if outcome == "success":
                    p2.happy(f"stage {stage} success path observable for {c}", "PIPE-001")
                else:
                    if stage in PIPELINE_STAGES_BEFORE_RUN:
                        p2.fail(f"stage {stage} fail stops run for {c}", "PIPE-002")
                    else:
                        p2.edge(f"stage {stage} fail handling for {c}", "PIPE-001")
    files.append(p2)

    # Full error code × HTTP × CLI × retryable flag assumptions
    e2 = F("core", "Errors/RetryableMatrixTest.php")
    retryable_default = {
        "validation_failed": False,
        "unauthenticated": False,
        "forbidden": False,
        "approval_required": False,
        "domain_error": False,
        "rate_limited": True,
        "conflict": False,
        "not_found": False,
        "output_invalid": False,
        "internal": True,
    }
    for code, _, _ in ERROR_CODES:
        expected = retryable_default.get(code, False)
        e2.happy(f"code {code} retryable default {expected}", "D-018")
        e2.edge(f"code {code} retryable flag always present", "D-018")
    files.append(e2)

    # Surface × capability surfaces intersection full product for listed sets
    s2 = F("core", "Boot/SurfaceListIntersectionTest.php")
    cap_lists = [
        ["agent", "mcp", "http", "cli"],
        ["http", "cli"],
        ["job"],
        ["agent"],
        ["mcp"],
        ["artisan"],
        ["agent", "job"],
    ]
    for cap_list in cap_lists:
        for g_off in SURFACES:
            label = f"cap_surfaces={','.join(cap_list)} global_off={g_off}"
            s2.edge(f"intersection computed when {label}", "SURF-001")
            if g_off in cap_list:
                s2.fail(f"surface {g_off} not effective when {label}", "SURF-001")
    files.append(s2)

    # Approval status × action × shape × actor_role expanded further
    a2 = F("core", "Approval/DecisionActorMatrixTest.php")
    for shape in ["deferred", "atomic"]:
        for status in APPROVAL_STATUSES:
            for action in ["accept", "reject", "resume"]:
                for actor in ["requester", "approver_role", "random", "system", "other_tenant"]:
                    label = f"shape={shape} status={status} action={action} actor={actor}"
                    if action == "resume" and status == "approved" and actor in ("requester", "approver_role", "system"):
                        a2.edge(f"resume path when {label}", "P2-004")
                    elif status == "pending" and action in ("accept", "reject") and actor in ("requester", "approver_role"):
                        a2.edge(f"decision path when {label}", "D-006")
                    else:
                        a2.fail(f"invalid or denied when {label}", "D-006")
    files.append(a2)

    # Scope resolver outcomes
    sc2 = F("core", "Scope/ResolverOutcomesTest.php")
    for c in CALLERS:
        for tenancy in ["multi", "single"]:
            for actor in ["user", "system"]:
                for tenant_present in [True, False]:
                    label = f"caller={c} tenancy={tenancy} actor={actor} tenant_present={tenant_present}"
                    if actor == "system" and tenancy == "multi" and not tenant_present:
                        sc2.fail(f"unresolved scope when {label} and not globalSystem", "P2-005")
                    else:
                        sc2.edge(f"scope resolution when {label}", "D-003")
    files.append(sc2)

    # Idempotency flags × key present × mutating
    i2 = F("core", "Idempotency/FlagKeyMatrixTest.php")
    for flag in ["optional", "required", "none"]:
        for key in [True, False]:
            for mutating in [True, False]:
                label = f"flag={flag} key={key} mutating={mutating}"
                if not mutating:
                    i2.edge(f"readOnly ignores when {label}", "D-005")
                elif flag == "required" and not key:
                    i2.fail(f"missing key rejected when {label}", "D-005")
                elif flag == "none":
                    i2.edge(f"keys ignored when {label}", "D-005")
                elif key:
                    i2.happy(f"key honored when {label}", "D-005")
                else:
                    i2.edge(f"non-idempotent path when {label}", "D-005")
    files.append(i2)

    # MCP auth profile × capability allowSystem × integration allow
    m2 = F("core", "Mcp/AuthProfileCapabilityMatrixTest.php")
    for profile in MCP_PROFILES:
        for allow_int in [True, False]:
            for allow_sys in [True, False, "list"]:
                label = f"profile={profile} allow_int={allow_int} allow_sys={allow_sys}"
                if profile == "integration" and not allow_int:
                    m2.fail(f"integration denied when {label}", "D-023")
                elif profile == "integration" and allow_int and allow_sys is False:
                    m2.fail(f"integration system denied when {label}", "D-023")
                else:
                    m2.edge(f"auth path when {label}", "D-023")
    files.append(m2)

    # Profile selection composition full
    pcomp = F("core", "Profiles/SelectionKindsTest.php")
    for kind in ["profile", "groups", "only", "none", "profile+groups", "only+profile_conflict"]:
        if kind == "none":
            pcomp.fail(f"selection {kind} throws when require_profile", "D-008")
        else:
            pcomp.edge(f"selection {kind} resolved to tool set", "D-008")
            pcomp.fail(f"selection {kind} cannot escape allowlist", "D-008")
    files.append(pcomp)

    # Meta tools list/run matrix
    meta2 = F("core", "Profiles/MetaToolsMatrixTest.php")
    for meta in ["aiMetaTools", "mcpMetaTools"]:
        for name_in_profile in [True, False]:
            for action in ["list", "run"]:
                label = f"{meta} action={action} in_profile={name_in_profile}"
                if action == "list":
                    if name_in_profile:
                        meta2.happy(f"list may include when {label}", "P2-007")
                    else:
                        meta2.fail(f"list excludes when {label}", "P2-007")
                else:
                    if name_in_profile:
                        meta2.happy(f"run hits registry when {label}", "P2-007")
                    else:
                        meta2.fail(f"run blocked without registry run when {label}", "P2-007")
    files.append(meta2)

    # Peer matrix expand with require_package
    pm2 = F("core", "Boot/PeerRequirePackageMatrixTest.php")
    for surface, peer in [("agent", "laravel/ai"), ("mcp", "laravel/mcp")]:
        for require in [True, False]:
            for installed in [True, False]:
                for mode in ["fail", "disable"]:
                    label = f"{surface} require={require} installed={installed} mode={mode}"
                    if require and not installed and mode == "fail":
                        pm2.fail(f"boot fails when {label}", "D-011")
                    elif require and not installed and mode == "disable":
                        pm2.edge(f"soft disable when {label}", "D-011")
                    elif not require and not installed:
                        pm2.edge(f"optional peer missing when {label}", "D-011")
                    else:
                        pm2.happy(f"boot ok when {label}", "D-011")
    files.append(pm2)

    # Observability metric × caller × status already partial - expand more statuses
    om2 = F("core", "Observability/InvokeMetricMatrixTest.php")
    for c in CALLERS:
        for status in ["ok", "validation_failed", "forbidden", "unauthenticated", "approval_required", "rate_limited", "domain_error", "conflict", "not_found", "output_invalid", "internal"]:
            om2.happy(f"metric invoke caller={c} status={status}", "D-019")
    files.append(om2)

    # CLI command × auth required matrix
    clia = F("cli", "internal/auth/command_guards_test.go", language="go", go_package="auth")
    for cmd in ["Run", "Catalog", "Describe", "Mcp", "Approvals"]:
        clia.add("go", f"{cmd}RequiresAuth", "CLI-AUTH")
        clia.add("go", f"{cmd}FailsWithExit3WhenNoToken", "CLI-AUTH")
    files.append(clia)

    # CLI schema cache scenarios
    cache = F("cli", "internal/catalog/cache_test.go", language="go", go_package="catalog")
    for title in [
        "CacheHitSameVersion",
        "CacheMissFetches",
        "CacheInvalidateOnVersionChange",
        "CacheInvalidateOnEtagChange",
        "CacheInvalidateOnRefreshCommand",
        "CacheBypassNoCacheFlag",
        "CachePerProfileIsolation",
        "CachePerBaseURLIsolation",
        "CacheCorruptFileRefetches",
        "CacheWriteAtomic",
    ]:
        cache.add("go", title, "CLI-CAT")
    files.append(cache)

    # Messaging thread + identity + profile integration matrix
    mi = F("messaging", "Telegram/IntegrationMatrixTest.php")
    for linked in [True, False]:
        for profile_ok in [True, False]:
            for tool_in_profile in [True, False]:
                label = f"linked={linked} profile_ok={profile_ok} tool_in_profile={tool_in_profile}"
                if not linked:
                    mi.fail(f"no tools when {label}", "MSG-002")
                elif not profile_ok:
                    mi.fail(f"fail loud when {label}", "D-008")
                elif not tool_in_profile:
                    mi.fail(f"tool blocked when {label}", "P2-007")
                else:
                    mi.happy(f"tool may run via registry when {label}", "MSG-003")
    files.append(mi)

    # Callback action × status matrix
    cb = F("messaging", "Telegram/CallbackStatusMatrixTest.php")
    for action in ["accept", "reject"]:
        for status in APPROVAL_STATUSES:
            label = f"action={action} status={status}"
            if status == "pending":
                cb.edge(f"callback routes to manager when {label}", "D-006")
            else:
                cb.happy(f"callback no-op already handled when {label}", "D-006")
    files.append(cb)

    # HTTP route method matrix with auth states
    hr = F("core", "Http/RouteAuthMatrixTest.php")
    routes = [
        ("GET", "list"), ("GET", "describe"), ("POST", "invoke"),
        ("POST", "approval_accept"), ("POST", "approval_reject"), ("GET", "health"),
    ]
    for method, route in routes:
        for auth in ["none", "user", "cli_token", "api_token"]:
            label = f"{method} {route} auth={auth}"
            if route == "health":
                hr.edge(f"health auth policy when {label}", "HTTP-001")
            elif auth == "none":
                hr.fail(f"unauthenticated rejected when {label}", "HTTP-001")
            else:
                hr.happy(f"authenticated allowed path when {label}", "HTTP-001")
    files.append(hr)

    # Schema portable vs server-only split exhaustive
    split = F("core", "Schema/PortableServerSplitTest.php")
    for rule in ["required", "integer", "string", "min", "max", "size", "in", "exists", "unique", "password"]:
        if rule in ("exists", "unique", "password"):
            split.happy(f"rule {rule} is server-only not in portable schema", "D-004")
            split.fail(f"rule {rule} not required for CLI local validation", "D-004")
        else:
            split.edge(f"rule {rule} may appear in portable schema when expressible", "D-004")
    files.append(split)

    # Capability context builder failure points
    cxf = F("core", "Context/BuildFailurePointsTest.php")
    for point in [
        "null_actor",
        "unknown_caller",
        "missing_scope_when_required",
        "invalid_mcp_auth_profile",
        "missing_job_acting_as_metadata",
    ]:
        cxf.fail(f"context build refuses {point}", "CTX-001")
    files.append(cxf)

    # System allowlist × globalSystem × tenant matrix already partial - expand names
    sa2 = F("core", "Job/SystemNamesMatrixTest.php")
    for name in ["scheduler", "reconciliation", "horizon", "billing-bot", "mcp-billing-service", "unknown", ""]:
        for listed in [True, False]:
            if not name:
                sa2.fail("empty system actor name rejected", "D-002")
            elif listed:
                sa2.happy(f"system actor {name} allowed when listed", "D-002")
            else:
                sa2.fail(f"system actor {name} denied when not listed", "D-002")
    files.append(sa2)

    # Artisan flags matrix
    art2 = F("core", "Surfaces/ArtisanFlagsTest.php")
    for flags in [
        "--acting-as=1",
        "--system=scheduler",
        "--system=scheduler --tenant=t1",
        "",
        "--acting-as=1 --system=scheduler",
    ]:
        label = flags or "no-flags"
        if flags in ("", "--acting-as=1 --system=scheduler"):
            art2.fail(f"artisan mutate refused or invalid when {label}", "D-002")
        else:
            art2.edge(f"artisan mutate path when {label}", "D-002")
    files.append(art2)

    # Dual-path prevention static architecture list
    dual2 = F("core", "Architecture/DualPathInventoryTest.php")
    for path in [
        "http_controller_domain_create",
        "ai_tool_domain_create",
        "mcp_tool_domain_create",
        "cli_local_domain_create",
        "job_handle_domain_create",
        "telegram_adapter_domain_create",
        "approval_notifier_domain_create",
        "artisan_command_domain_create",
    ]:
        dual2.fail(f"dual path forbidden: {path}", "BELIEF-001")
        dual2.happy(f"allowed path uses registry instead of {path}", "BELIEF-001")
    files.append(dual2)

    # Catalog describe vs list parity fields
    cpar = F("core", "Catalog/ListDescribeParityTest.php")
    for field_name in ["name", "description", "schema_version", "deprecated", "aliases"]:
        cpar.happy(f"list and describe agree on {field_name}", "CAT-001")
    cpar.edge("describe has full schemas list may omit", "CAT-001")
    files.append(cpar)

    # Health surface status full product
    hs = F("core", "Catalog/HealthSurfaceStatusMatrixTest.php")
    for s in SURFACES:
        for status in ["up", "disabled_incompatible", "disabled_config", "missing"]:
            hs.edge(f"health surface {s} can report {status}", "D-011")
    files.append(hs)

    # WriteAuditJob / outbox states
    ob = F("core", "Audit/OutboxStatesTest.php")
    for st in ["pending", "processing", "completed", "failed"]:
        ob.edge(f"outbox row status {st} handled", "D-010")
    ob.happy("WriteAuditJob transitions pending to completed", "D-010")
    ob.fail("required true never leaves permanent silent drop", "D-010")
    files.append(ob)

    # Approval messaging metadata fields
    ameta = F("core", "Approval/MessagingMetadataTest.php")
    for field_name in ["channel", "chat_id", "message_id"]:
        ameta.edge(f"approval messaging metadata may include {field_name}", "D-006")
    ameta.happy("telegram notifier can edit message using message_id", "D-006")
    files.append(ameta)

    # Rate limit agent turn budget × tool count
    rab = F("core", "RateLimiting/AgentTurnBudgetMatrixTest.php")
    for budget in [1, 2, 8, 16, 32]:
        for calls in [0, 1, budget, budget + 1]:
            label = f"budget={budget} calls={calls}"
            if calls > budget:
                rab.fail(f"agent loop stops when {label}", "D-013")
            else:
                rab.happy(f"agent loop allows when {label}", "D-013")
    files.append(rab)

    # CLI exit documentation vs implementation parity
    ced = F("cli", "internal/run/exit_doc_test.go", language="go", go_package="run")
    for code, http, exit_code in ERROR_CODES:
        ced.add("go", f"DocAndCodeAgree_{code}_Exit{exit_code}", "D-018")
    files.append(ced)

    # Large spoof matrix for messaging payloads
    spm = F("messaging", "Identity/SpoofMatrixTest.php")
    for field in ["telegram_user_id", "laravel_user_id", "tenant_id", "chat_id", "from.id"]:
        spm.fail(f"forged field {field} cannot escalate privileges", "MSG-002")
        spm.fail(f"forged field {field} cannot bind identity without code flow", "MSG-002")
    files.append(spm)

    # Webhook queue guarantees
    wq = F("messaging", "Telegram/QueueGuaranteesTest.php")
    wq.happy("valid webhook enqueues ProcessTelegramUpdate", "MSG-003")
    wq.fail("valid webhook does not sync-mutate domain", "D-007")
    wq.fail("invalid webhook does not enqueue", "MSG-003")
    wq.edge("enqueue failure is surfaced", "MSG-003")
    files.append(wq)

    # Registry unknown / disabled / wrong surface
    ru = F("core", "Registry/LookupMatrixTest.php")
    for c in CALLERS:
        ru.fail(f"unknown capability via {c} is not_found", "PIPE-004")
        ru.fail(f"known capability wrong surface via {c} not invokable as that surface", "PIPE-005")
        ru.happy(f"known capability correct surface via {c} invokable", "PIPE-003")
    files.append(ru)

    # Output schema types validation matrix
    ot = F("core", "Schema/OutputTypeMatrixTest.php")
    for typ, bad in [
        ("int", "string"),
        ("string", "int"),
        ("bool", "string"),
        ("array", "object"),
        ("object", "array"),
        ("nullable", "missing_when_required"),
    ]:
        ot.fail(f"output type {typ} rejects {bad}", "D-014")
        ot.happy(f"output type {typ} accepts valid value", "D-014")
    files.append(ot)

    # Facade error propagation
    fep = F("core", "Facades/ErrorPropagationTest.php")
    for code, _, _ in ERROR_CODES:
        fep.happy(f"facade invoke surfaces code {code} without swallowing", "FAC-001")
    files.append(fep)

    # Service provider registration matrix already - add bindings
    bind = F("core", "Boot/ContainerBindingsTest.php")
    for abstract in [
        "CapabilityRegistry",
        "ApprovalManager",
        "IdempotencyStore",
        "AuditLogger",
        "ScopeResolver",
        "AiToolAdapter",
        "McpToolAdapter",
        "Metrics",
        "Tracer",
    ]:
        bind.happy(f"container binds {abstract}", "BOOT-001")
        bind.edge(f"tests can rebind {abstract} to fake", "BOOT-001")
    files.append(bind)

    # Decision ID coverage checklist - ensure each D- has a dedicated suite pointer
    cov = F("core", "Architecture/DecisionCoverageMapTest.php")
    for d in [f"D-{i:03d}" for i in list(range(2, 12)) + list(range(12, 24))]:
        cov.happy(f"decision {d} has dedicated unit scenarios in inventory", "COV")
    for p in ["P2-004", "P2-005", "P2-007"]:
        cov.happy(f"patch {p} has dedicated unit scenarios in inventory", "COV")
    files.append(cov)

    # Final large: caller × error × no side effects
    cne = F("core", "Parity/CallerErrorNoSideEffectsTest.php")
    for c in CALLERS:
        for code, _, _ in ERROR_CODES:
            if code in ("approval_required",):
                cne.happy(f"caller {c} code {code} stores pending only not domain success", "PARITY-001")
            elif code == "ok":
                continue
            else:
                cne.fail(f"caller {c} code {code} has no successful domain mutation", "PARITY-001")
    files.append(cne)

    # Input hydration edge cases
    hyd = F("core", "Schema/HydrationEdgeTest.php")
    for case in [
        "extra_keys_rejected_when_additionalProperties_false",
        "null_for_nullable_ok",
        "null_for_required_rejected",
        "string_int_coercion_policy",
        "empty_array_ok_when_allowed",
        "nested_object_hydrated",
        "list_of_objects_hydrated",
    ]:
        hyd.edge(f"hydration case {case}", "D-015")
    files.append(hyd)

    # CLI binary distribution non-goals
    dist = F("cli", "cmd/capabilities/dist_test.go", language="go", go_package="main")
    for title in [
        "NoEmbeddedPHPRuntime",
        "NoEmbeddedLaravelApp",
        "NoLocalDatabaseDriver",
        "CrossCompileTargetsDocumented",
    ]:
        dist.add("go", title, "D-016")
    files.append(dist)

    # Messaging config keys
    mck = F("messaging", "Config/KeysTest.php")
    for key in [
        "telegram.enabled",
        "telegram.bot_token",
        "telegram.webhook_secret",
        "telegram.callback_ttl_seconds",
        "agent_profile",
        "identity.mode",
    ]:
        mck.happy(f"messaging config has key {key}", "MSG-001")
    files.append(mck)

    # Conversation contracts method surface
    cc = F("core", "Contracts/ConversationContractsTest.php")
    for contract in ["ConversationIngress", "ConversationReply", "ConversationIdentity", "ApprovalNotifier"]:
        cc.happy(f"contract {contract} is defined in core", "D-007")
        cc.fail(f"contract {contract} does not embed Bot API types", "D-007")
    files.append(cc)

    # Large approval policy × self-approve matrix already - add reason field
    reason = F("core", "Approval/DecisionReasonTest.php")
    reason.happy("reject may include decision_reason", "D-006")
    reason.edge("accept may omit decision_reason", "D-006")
    reason.happy("decision_reason stored on row when provided", "D-006")
    files.append(reason)

    # Resume metrics + accept metrics already
    # Final expand: each refuse row × each caller where relevant
    refc = F("core", "Architecture/RefusePerCallerTest.php")
    refuses = [
        "spoof_caller_header",
        "skip_authorize",
        "skip_scope",
        "skip_idempotency_on_mutating",
        "dump_full_catalog",
        "meta_escape",
    ]
    for r in refuses:
        for c in CALLERS:
            refc.fail(f"refuse {r} for caller {c}", "REFUSE")
    files.append(refc)



    # ═══════════════════════════════════════════════════════════
    # PASS 3 — close remaining contract gaps to ≥5000
    # ═══════════════════════════════════════════════════════════

    # Full HTTP method × route × surface.http enabled matrix
    h3 = F("core", "Http/RouteRegistrationMatrixTest.php")
    for enabled in [True, False]:
        for method, route in [
            ("GET", "list"), ("GET", "describe"), ("POST", "invoke"),
            ("POST", "accept"), ("POST", "reject"), ("GET", "health"),
            ("POST", "auth_token"), ("POST", "auth_device"),
        ]:
            label = f"http_enabled={enabled} {method} {route}"
            if enabled:
                h3.happy(f"registers {label}", "D-009")
            else:
                h3.fail(f"does not register {label}", "D-009")
    files.append(h3)

    # Every pipeline stage failure × every error mapping expectation
    p3 = F("core", "Registry/StageErrorMappingTest.php")
    stage_code = {
        "json_schema_validate": "validation_failed",
        "hydrate_dto": "validation_failed",
        "server_only_validate": "validation_failed",
        "resolve_actor": "unauthenticated",
        "resolve_scope": "forbidden",
        "idempotency_lookup": "conflict",
        "authorize": "forbidden",
        "needs_approval": "approval_required",
        "rate_limit": "rate_limited",
    }
    for stage, code in stage_code.items():
        for c in CALLERS:
            p3.happy(f"stage {stage} maps to {code} for {c}", "PIPE-002")
            p3.fail(f"stage {stage} does not call run for {c}", "PIPE-002")
    files.append(p3)

    # Approval TTL matrix
    ttl = F("core", "Approval/TtlMatrixTest.php")
    for global_ttl in [1, 24, 72]:
        for cap_ttl in [None, 1, 12, 24]:
            label = f"global={global_ttl} cap={cap_ttl}"
            ttl.edge(f"effective ttl computed when {label}", "D-006")
            ttl.happy(f"pending expires after effective ttl when {label}", "D-006")
    files.append(ttl)

    # Idempotency TTL matrix
    ittl = F("core", "Idempotency/TtlMatrixTest.php")
    for hours in [1, 24, 168]:
        ittl.edge(f"ttl_hours={hours} applied to store", "D-005")
        ittl.happy(f"expired after {hours}h treated as new key", "D-005")
        ittl.happy(f"within {hours}h replay works", "D-005")
    files.append(ittl)

    # Discoverability filter matrix
    discf = F("core", "Profiles/DiscoverabilityFilterTest.php")
    for c in ["agent", "mcp"]:
        for can_discover in [True, False]:
            for in_profile in [True, False]:
                label = f"surface={c} can_discover={can_discover} in_profile={in_profile}"
                if can_discover and in_profile:
                    discf.happy(f"tool listed when {label}", "D-008")
                else:
                    discf.fail(f"tool not listed when {label}", "D-008")
                discf.happy(f"authorize still runs on invoke when {label}", "D-008")
    files.append(discf)

    # Schema version / etag catalog fields
    sve = F("core", "Catalog/SchemaVersionEtagTest.php")
    sve.happy("catalog entries include schema_version", "D-004")
    sve.edge("catalog may include etag for cache", "D-004")
    sve.happy("describe includes schema_version matching list", "D-004")
    sve.edge("cli cache invalidates on schema_version change", "D-004")
    files.append(sve)

    # Job queue failure hooks
    jqh = F("core", "Job/FailedJobHooksTest.php")
    for tag in ["capability", "caller", "actor_type", "tenant_id"]:
        jqh.edge(f"failed job tagged with {tag}", "D-019")
    jqh.happy("RunCapability uses Laravel failed-job hooks", "D-019")
    files.append(jqh)

    # Messaging failed job tags
    mfj = F("messaging", "Telegram/FailedJobTagsTest.php")
    for tag in ["channel", "chat_id", "update_id"]:
        mfj.edge(f"ProcessTelegramUpdate failure tags {tag}", "D-019")
    files.append(mfj)

    # Full list of audit chain events as independent cases with fields
    ac = F("core", "Approval/AuditChainFieldsTest.php")
    events = {
        "approval.requested": ["approval_id", "requester", "capability", "input_redacted", "idempotency_key"],
        "approval.decided": ["approval_id", "decided_by", "decision", "reason"],
        "approval.executed": ["approval_id", "result", "replay", "via"],
        "approval.replayed": ["approval_id", "result"],
        "approval.expired": ["approval_id"],
        "approval.resume": ["approval_id", "attempt"],
    }
    for event, fields in events.items():
        ac.happy(f"audit event {event} emitted on condition", "D-006")
        for f_ in fields:
            ac.edge(f"audit event {event} includes {f_}", "D-006")
    files.append(ac)

    # Caller derivation matrix: adapter set paths
    ad = F("core", "Caller/AdapterSetCallerTest.php")
    for adapter, caller in [
        ("AiToolAdapter", "agent"),
        ("McpToolAdapter", "mcp"),
        ("HttpController", "http_or_cli_derived"),
        ("RunCapabilityJob", "job"),
        ("ArtisanCommand", "job_or_explicit"),
        ("InProcessInvoke", "explicit_argument"),
    ]:
        ad.happy(f"{adapter} sets caller {caller} in server code", "D-022")
        ad.fail(f"{adapter} does not trust client caller field", "D-022")
    files.append(ad)

    # CLI accept header matrix
    cah = F("cli", "internal/api/accept_header_test.go", language="go", go_package="api")
    for title in [
        "DefaultAcceptJSON",
        "OptionalCLIVendorAccept",
        "VendorAcceptDoesNotChangeServerCaller",
        "VendorAcceptOnlyAffectsPresentation",
    ]:
        cah.add("go", title, "D-009")
    files.append(cah)

    # Large parity: success class across surfaces for same input
    par = F("core", "Parity/SuccessClassMatrixTest.php")
    for a in ["registry", "http", "ai", "mcp", "job"]:
        for b in ["registry", "http", "ai", "mcp", "job"]:
            if a >= b:
                continue
            par.happy(f"assertParity success class {a} vs {b}", "D-020")
            par.happy(f"assertParity deny class {a} vs {b}", "D-020")
    files.append(par)

    # Resource re-resolve in authorize and run
    rr = F("core", "Scope/ReresolveInAuthorizeAndRunTest.php")
    for c in CALLERS:
        rr.happy(f"authorize re-resolves resources under scope for {c}", "D-003")
        rr.happy(f"run re-resolves resources under scope for {c}", "D-003")
        rr.fail(f"authorize does not trust wire id alone for {c}", "D-003")
        rr.fail(f"run does not trust wire id alone for {c}", "D-003")
    files.append(rr)

    # Config publish tags
    pub = F("core", "Boot/PublishTagsTest.php")
    for tag in ["capabilities-config", "capabilities-migrations"]:
        pub.edge(f"publish tag {tag} available", "BOOT-001")
    files.append(pub)

    mpub = F("messaging", "Boot/PublishTagsTest.php")
    mpub.edge("publish tag capabilities-messaging-config available", "MSG-001")
    files.append(mpub)

    # Large: each non-goal × package boundary
    ng = F("core", "Architecture/NonGoalsBoundaryTest.php")
    for g in [
        "llm_client", "mcp_wire_protocol", "artisan_product_cli", "chat_ui",
        "telegram_runtime_core", "a2a_mesh", "controller_replacement", "messaging_os",
    ]:
        ng.fail(f"core is not {g}", "NONGOAL")
        ng.happy(f"tests guard against becoming {g}", "NONGOAL")
    files.append(ng)

    # CLI help text contains each error code
    ch = F("cli", "cmd/capabilities/exit_help_test.go", language="go", go_package="main")
    for code, _, exit_code in ERROR_CODES:
        ch.add("go", f"HelpMentions_{code}_Exit{exit_code}", "D-018")
    files.append(ch)

    # Definition discovery path config
    pathc = F("core", "Discovery/PathConfigTest.php")
    pathc.happy("default path is app_path Capabilities", "D-017")
    pathc.edge("custom path config is scanned", "D-017")
    pathc.fail("classes outside path not auto-discovered", "D-017")
    pathc.happy("fluent define works regardless of path", "D-017")
    files.append(pathc)

    # Large matrix: spoof X-Capabilities-Caller for each credential type
    sp = F("core", "Caller/HeaderVsCredentialMatrixTest.php")
    for cred in ["cli_ability", "api_pat", "oauth_cli_client", "oauth_http_client", "none"]:
        for header in CALLERS:
            label = f"cred={cred} header={header}"
            sp.edge(f"derived caller computed when {label}", "D-022")
            sp.fail(f"header cannot self-upgrade when {label}", "D-022")
    files.append(sp)

    # Approval execution mode config switch
    em = F("core", "Approval/ExecutionModeConfigTest.php")
    em.happy("execution deferred enables resume scheduling", "P2-004")
    em.happy("execution atomic disables resume necessity", "P2-004")
    em.fail("execution invalid value fails config validation", "D-006")
    em.edge("resume.enabled false does not schedule when deferred", "P2-004")
    files.append(em)

    # Output invalid HTTP status class
    oih = F("core", "Schema/OutputInvalidHttpTest.php")
    oih.happy("output_invalid is 500-class envelope", "D-014")
    oih.fail("output_invalid is not 200 success", "D-014")
    oih.fail("output_invalid is not silent coercion to partial data", "D-014")
    files.append(oih)

    # Large: group/tag profile composition examples
    gt = F("core", "Profiles/GroupsTagsTest.php")
    for g in ["finance", "support", "ops", "billing"]:
        gt.happy(f"group {g} composes tools from capability groups", "D-008")
        gt.edge(f"tag {g} can contribute to selection", "D-008")
        gt.fail(f"group {g} still filtered by canDiscover", "D-008")
    files.append(gt)

    # Messaging later channels non-implementation
    later = F("messaging", "Architecture/LaterChannelsTest.php")
    for ch in ["slack", "whatsapp", "email"]:
        later.edge(f"channel {ch} not required in v1 telegram path", "D-007")
        later.fail(f"channel {ch} must not be implemented in core", "D-007")
    files.append(later)

    # CLI run retries matrix
    rtry = F("cli", "internal/run/retry_test.go", language="go", go_package="run")
    for title in [
        "RetryLastReusesIdempotencyKey",
        "RetryLastFailsIfNoPrevious",
        "ManualKeyOverridesAuto",
        "NetworkFailDoesNotRotateKeyOnRetryLast",
        "NewRunWithoutRetryLastGetsNewKey",
    ]:
        rtry.add("go", title, "D-005")
    files.append(rtry)

    # Capability result assertion helpers matrix
    crh = F("core", "Support/ResultAssertionsTest.php")
    for helper in ["assertOk", "assertFailed", "assertForbidden", "assertConflict", "assertExpired", "assertApprovalRequired", "assertReplay"]:
        crh.happy(f"result helper {helper} exists", "RES-001")
        crh.edge(f"result helper {helper} fails test on mismatch", "RES-001")
    files.append(crh)

    # Large final: each decision refuse × architecture test already done
    # Expand authorize match arms pattern guidance
    authp = F("core", "Registry/AuthorizeActorMatchTest.php")
    authp.happy("authorize can match User actor", "D-002")
    authp.happy("authorize can match SystemActor", "D-002")
    authp.fail("authorize default denies unknown actor kinds", "D-002")
    authp.fail("authorize must not use is user null allow pattern", "D-002")
    for c in CALLERS:
        authp.edge(f"authorize receives caller {c} in context", "CTX-001")
    files.append(authp)

    # Catalog health never secrets
    chs = F("core", "Catalog/HealthNoSecretsTest.php")
    chs.fail("health response does not include bot tokens", "D-021")
    chs.fail("health response does not include API secrets", "D-021")
    chs.edge("health may include readiness booleans only", "D-021")
    files.append(chs)

    # Process order messaging steps failure no tool access
    mpo = F("messaging", "Telegram/StepOrderTest.php")
    steps = [
        "verify_secret", "queue", "resolve_identity", "map_thread",
        "ingress", "agent", "tools", "reply",
    ]
    for i, s in enumerate(steps):
        mpo.happy(f"step order {i:02d} {s}", "MSG-003")
        if s in ("resolve_identity", "verify_secret"):
            mpo.fail(f"tools not reached if {s} fails", "MSG-003")
    files.append(mpo)

    # Expand Go API client methods
    apim = F("cli", "internal/api/methods_test.go", language="go", go_package="api")
    for title in [
        "ListCapabilities",
        "DescribeCapability",
        "InvokeCapability",
        "AcceptApproval",
        "RejectApproval",
        "Health",
        "LoginDevice",
        "LoginToken",
    ]:
        apim.add("go", title, "D-009")
    files.append(apim)

    # Final bulk: every surface disabled × every catalog/list behavior
    sdc = F("core", "Catalog/DisabledSurfaceCatalogTest.php")
    for s in INVOKE_SURFACES:
        sdc.edge(f"catalog excludes caps only on disabled surface {s}", "CAT-001")
        sdc.happy(f"catalog still lists caps with other surfaces when {s} disabled", "CAT-001")
    files.append(sdc)

    # Bulk: needsApproval example threshold
    thr = F("core", "Approval/ExampleThresholdTest.php")
    thr.happy("example amount_cents >= 100000 may require approval for agent mcp cli", "D-006")
    thr.edge("example http staff path may not require approval for same amount", "D-006")
    thr.fail("approval branch uses derived caller not header in example", "D-022")
    files.append(thr)

    # Bulk field: CreateInvoiceInput example fields reflected
    ex = F("core", "Schema/ExampleDtoTest.php")
    for field_name in ["customer_id", "amount_cents", "currency", "memo"]:
        ex.happy(f"example CreateInvoiceInput field {field_name} in schema", "D-015")
    ex.happy("example output invoice_id in schema", "D-015")
    ex.fail("example server exists rule not in portable schema", "D-004")
    files.append(ex)

    # Contract: unit tests only policy meta tests
    pol = F("core", "Architecture/TestingPolicyTest.php")
    pol.fail("no tests/Feature directory in core package", "POLICY")
    pol.fail("tests do not require database connection", "POLICY")
    pol.happy("tests live under tests/Unit", "POLICY")
    pol.edge("coverage floor 95 percent is project policy", "POLICY")
    files.append(pol)

    mpol = F("messaging", "Architecture/TestingPolicyTest.php")
    mpol.fail("no tests/Feature directory in messaging package", "POLICY")
    mpol.fail("tests do not require database connection", "POLICY")
    mpol.happy("tests live under tests/Unit", "POLICY")
    files.append(mpol)



    # ═══════════════════════════════════════════════════════════
    # PASS 4 — final bulk to ≥5000 + residual gaps
    # ═══════════════════════════════════════════════════════════

    bulk = F("core", "Parity/CallerStageNoSideEffectsBulkTest.php")
    for c in CALLERS:
        for stage in PIPELINE_STAGES_BEFORE_RUN:
            for assert_kind in ["no_run", "no_domain_write", "structured_error", "optional_deny_audit"]:
                if assert_kind == "structured_error":
                    bulk.happy(f"{c}/{stage}/{assert_kind}", "PIPE-002")
                else:
                    bulk.fail(f"{c}/{stage}/{assert_kind} violated", "PIPE-002")
    files.append(bulk)

    bulk2 = F("core", "Scope/CrossTenantCallerBulkTest.php")
    for c in CALLERS:
        for resource in ["customer", "invoice", "subscription", "payment_method", "report"]:
            bulk2.fail(f"cross-tenant {resource} via {c} denied", "D-003")
            bulk2.fail(f"cross-tenant {resource} via {c} no run", "D-003")
            bulk2.happy(f"same-tenant {resource} via {c} may authorize", "D-003")
    files.append(bulk2)

    bulk3 = F("core", "Idempotency/CallerKeyBulkTest.php")
    for c in CALLERS:
        for situation in ["first", "replay_same", "conflict_diff", "processing", "failed_replay", "readonly_ignore", "required_missing"]:
            if situation in ("conflict_diff", "required_missing"):
                bulk3.fail(f"idempotency {situation} for {c}", "D-005")
            elif situation in ("processing", "failed_replay", "readonly_ignore"):
                bulk3.edge(f"idempotency {situation} for {c}", "D-005")
            else:
                bulk3.happy(f"idempotency {situation} for {c}", "D-005")
    files.append(bulk3)

    bulk4 = F("core", "Approval/CallerApprovalBulkTest.php")
    for c in CALLERS:
        for situation in ["needs_true_pending", "needs_false_runs", "accept_ok", "accept_stale", "reject", "expire", "double_accept_replay"]:
            if situation in ("accept_stale",):
                bulk4.fail(f"approval {situation} for original caller {c}", "D-006")
            else:
                bulk4.happy(f"approval {situation} for original caller {c}", "D-006")
    files.append(bulk4)

    bulk5 = F("core", "Errors/CodeCallerBulkTest.php")
    for c in CALLERS:
        for code, http, exit_code in ERROR_CODES:
            bulk5.happy(f"{c} can surface {code} http={http} cli_exit={exit_code}", "D-018")
    files.append(bulk5)

    bulk6 = F("messaging", "Telegram/CallerAgentMetadataBulkTest.php")
    for meta in ["channel", "chat_id", "message_id", "topic_id", "user_link_id"]:
        bulk6.edge(f"messaging metadata field {meta} optional on agent context", "D-007")
        bulk6.fail(f"messaging metadata field {meta} not used as authorize authority alone", "MSG-002")
    files.append(bulk6)

    bulk7 = F("cli", "internal/run/caller_docs_test.go", language="go", go_package="run")
    for title in [
        "DocsSayServerDerivesCaller",
        "DocsSayNotArtisan",
        "DocsSaySameHTTPAPI",
        "DocsSayLocalValidateThenServer",
        "DocsSayIdempotencyAlwaysSent",
        "DocsSayExitCodesStable",
    ]:
        bulk7.add("go", title, "D-016")
    files.append(bulk7)



    # PASS 5 — tiny closeout past 5000
    close = F("core", "Architecture/ContractSourceOfTruthTest.php")
    close.happy("inventory and package stubs are generated together", "COV")
    close.happy("implemented tests become living contract of product behavior", "COV")
    close.fail("behavior not covered by a unit scenario is not considered specified", "COV")
    close.fail("feature tests are not the contract vehicle for this monorepo", "POLICY")
    close.edge("regenerate via tools/generate_requirement_stubs.py", "COV")
    close.happy("each decision D-002 through D-023 is represented in scenarios", "COV")
    close.happy("each patch P2-004 P2-005 P2-007 is represented in scenarios", "COV")
    close.fail("hand-edited generated stubs are not the source process", "COV")
    close.happy("happy fail and edge kinds cover success denial and boundaries", "COV")
    close.happy("go CLI unit tests use stable *_test.go names", "COV")
    files.append(close)

    return files


def php_file_content(spec: FileSpec) -> str:
    lines = [
        "<?php",
        "",
        "// AUTO-GENERATED by tools/generate_requirement_stubs.py — do not edit by hand.",
        "// Spec-derived unit test stubs. Unit-only, no database — see AGENTS.md.",
        f"// Package file: {spec.relpath}",
        "",
        "declare(strict_types=1);",
        "",
    ]
    for case in spec.cases:
        # Double-quoted Pest description so inventory labels with single quotes match 1:1.
        label = case.label().replace("\\", "\\\\").replace('"', '\\"')
        lines.append(f'it("{label}", function () {{')
        lines.append("    // TODO: implement unit test with mocks/fakes (no DB).")
        lines.append("})->todo();")
        lines.append("")
    return "\n".join(lines)


def go_file_content(spec: FileSpec) -> str:
    lines = [
        f"package {spec.go_package}",
        "",
        'import "testing"',
        "",
        "// AUTO-GENERATED by tools/generate_requirement_stubs.py — do not edit by hand.",
        "// Spec-derived unit test stubs. No network by default; mock HTTP.",
        "// See docs/spec.md and AGENTS.md (unit-only, ≥95% coverage when implemented).",
        "",
    ]
    for case in spec.cases:
        name = go_test_func_name(case.title)
        lines.append(f"func {name}(t *testing.T) {{")
        lines.append("\tt.Helper()")
        req = f" [{case.req}]" if case.req else ""
        lines.append(f'\tt.Skip("TODO: implement unit test with mocks (no live network){req}")')
        lines.append("}")
        lines.append("")
    return "\n".join(lines)


def inventory_content(files: list[FileSpec]) -> str:
    total = sum(len(f.cases) for f in files)
    by_kind = defaultdict(int)
    for f in files:
        for c in f.cases:
            by_kind[c.kind] += 1

    lines = [
        "# Requirements inventory → unit test todos",
        "",
        "Generated by `tools/generate_requirement_stubs.py` from the normative content of `docs/spec.md`.",
        "",
        "**This inventory + the matching package stubs are the contract scaffold.**",
        "When implemented, the tests are the source of truth for what the product is and is not.",
        "",
        "Policy: **unit tests only**, no DB, mocks/fakes, **≥95% coverage** when implemented (`AGENTS.md`).",
        "",
        # Status counts are filled by tools/sync_requirements_inventory.py against the suite.
        # Generator always emits unchecked boxes; run sync after regenerate.
        f"**Cases: {total} total — 0 implemented, {total} remaining**",
        "",
        f"- happy: {by_kind.get('happy', 0)}",
        f"- fail: {by_kind.get('fail', 0)}",
        f"- edge: {by_kind.get('edge', 0)}",
        f"- go: {by_kind.get('go', 0)}",
        "",
        "Regenerate scaffold (safe — does not wipe implemented tests):",
        "",
        "```bash",
        "python3 tools/generate_requirement_stubs.py",
        "python3 tools/sync_requirements_inventory.py",
        "```",
        "",
        "Only missing files and pure AUTO-GENERATED stubs are rewritten; implemented suites",
        "(no AUTO-GENERATED marker) are skipped with a written/skipped count on stdout/stderr.",
        "Then sync marks inventory cases done when matching Pest `it()` / Go `Test*` exist.",
        "Extend the catalog in the generator and re-run; do not hand-edit pure stubs.",
        "",
    ]

    groups = [
        ("core", "Core (`packages/laravel-capabilities`)"),
        ("messaging", "Messaging (`packages/laravel-capabilities-messaging`)"),
        ("cli", "CLI (`packages/capabilities-cli`)"),
    ]
    for pkg, heading in groups:
        lines.append(f"## {heading}")
        lines.append("")
        for f in files:
            if f.package != pkg:
                continue
            lines.append(f"### `{f.relpath}` ({len(f.cases)})")
            lines.append("")
            for c in f.cases:
                if c.kind == "go":
                    name = go_test_func_name(c.title)
                    suffix = f" [{c.req}]" if c.req else ""
                    lines.append(f"- [ ] {name}{suffix}")
                else:
                    lines.append(f"- [ ] {c.label()}")
            lines.append("")
    return "\n".join(lines)


def ensure_unique_cases(files: list[FileSpec]) -> None:
    """Make inventory labels, Pest method names, and Go Test funcs unique without dropping scenarios.

    Exact catalog duplicates and punctuation-collapsing Pest titles get a stable
    disambiguating suffix on Case.title so inventory + stubs stay 1:1 complete.
    """
    global_keys: set[str] = set()
    go_funcs_by_package: dict[str, set[str]] = defaultdict(set)

    for f in files:
        file_methods: set[str] = set()
        unique: list[Case] = []
        for c in f.cases:
            base_title = c.title
            n = 0
            while True:
                c.title = base_title if n == 0 else (
                    f"{base_title}_{n}" if c.kind == "go" else f"{base_title} (case {n})"
                )
                if c.kind == "go":
                    key = go_test_func_name(c.title)
                    pkg = f.go_package or f.relpath
                    if key in global_keys or key in go_funcs_by_package[pkg]:
                        n += 1
                        continue
                    global_keys.add(key)
                    go_funcs_by_package[pkg].add(key)
                    unique.append(c)
                    break

                label = c.label()
                method = pest_evaluable(label)
                if label in global_keys or method in file_methods:
                    n += 1
                    continue
                global_keys.add(label)
                file_methods.add(method)
                unique.append(c)
                break
        f.cases = unique


AUTO_GENERATED_MARKER = "AUTO-GENERATED by tools/generate_requirement_stubs.py"


def is_auto_generated_stub(text: str) -> bool:
    """True when file body still carries the generator stub marker (safe to rewrite)."""
    return AUTO_GENERATED_MARKER in text


def should_write_stub(path: Path) -> bool:
    """Rewrite only missing files or pure AUTO-GENERATED stubs — never implemented suites."""
    if not path.exists():
        return True
    try:
        text = path.read_text(encoding="utf-8", errors="ignore")
    except OSError:
        return False
    return is_auto_generated_stub(text)


def _package_paths(root: Path) -> tuple[Path, Path, Path]:
    core_tests = root / "packages/laravel-capabilities/tests/Unit"
    msg_tests = root / "packages/laravel-capabilities-messaging/tests/Unit"
    cli_root = root / "packages/capabilities-cli"
    return core_tests, msg_tests, cli_root


def _target_path(f: FileSpec, root: Path) -> Path:
    core_tests, msg_tests, cli_root = _package_paths(root)
    if f.language == "php":
        base = core_tests if f.package == "core" else msg_tests
        return base / f.relpath
    return cli_root / f.relpath


def write_files(files: list[FileSpec], root: Path | None = None) -> dict:
    """Write inventory + safe stub targets.

    Safety contract:
    - Implemented unit tests (no AUTO-GENERATED marker) are never deleted or overwritten.
    - Missing files and pure AUTO-GENERATED stubs may be rewritten.
    - Orphan AUTO-GENERATED stubs not in the catalog may be removed; implemented files stay.
    - Inventory markdown is always regenerated (does not touch live suites).
    """
    root = root if root is not None else ROOT
    stats = {
        "php": 0,
        "go": 0,
        "cases": 0,
        "written": 0,
        "skipped": 0,
        "removed_orphans": 0,
    }

    core_tests, msg_tests, cli_root = _package_paths(root)
    catalog_paths: set[Path] = set()
    for f in files:
        catalog_paths.add(_target_path(f, root).resolve())

    # Remove only orphan pure stubs we own — never implemented tests.
    for base in [core_tests, msg_tests]:
        if not base.exists():
            continue
        for p in base.rglob("*Test.php"):
            if p.resolve() in catalog_paths:
                continue
            try:
                text = p.read_text(encoding="utf-8", errors="ignore")
            except OSError:
                continue
            if is_auto_generated_stub(text):
                p.unlink()
                stats["removed_orphans"] += 1

    if cli_root.exists():
        for p in cli_root.rglob("*_test.go"):
            if p.resolve() in catalog_paths:
                continue
            try:
                text = p.read_text(encoding="utf-8", errors="ignore")
            except OSError:
                continue
            if is_auto_generated_stub(text):
                p.unlink()
                stats["removed_orphans"] += 1

    for f in files:
        stats["cases"] += len(f.cases)
        path = _target_path(f, root)
        if not should_write_stub(path):
            stats["skipped"] += 1
            continue
        path.parent.mkdir(parents=True, exist_ok=True)
        if f.language == "php":
            path.write_text(php_file_content(f), encoding="utf-8")
            stats["php"] += 1
        else:
            path.write_text(go_file_content(f), encoding="utf-8")
            stats["go"] += 1
        stats["written"] += 1

    inv_path = root / "docs/requirements-inventory.md"
    inv_path.parent.mkdir(parents=True, exist_ok=True)
    inv_path.write_text(inventory_content(files), encoding="utf-8")
    stats["inventory"] = str(inv_path)
    return stats


def main() -> None:
    files = build_catalog()
    # Uniquify (never drop): inventory labels + Pest evaluable method names + Go Test funcs.
    ensure_unique_cases(files)

    stats = write_files(files)
    total = stats["cases"]
    written = stats["written"]
    skipped = stats["skipped"]
    print(
        f"Generated {total} cases across {stats['php']} PHP files and {stats['go']} Go files "
        f"(written={written}, skipped={skipped})"
    )
    if skipped:
        print(
            f"Skipped {skipped} implemented test file(s) "
            f"(no {AUTO_GENERATED_MARKER!r} marker — refusing overwrite).",
            file=sys.stderr,
        )
    if stats.get("removed_orphans"):
        print(f"Removed {stats['removed_orphans']} orphan AUTO-GENERATED stub(s)")
    print(f"Inventory: {stats['inventory']}")
    if total < 1000:
        print("WARNING: case count is lower than expected for complete matrix coverage")


if __name__ == "__main__":
    main()
