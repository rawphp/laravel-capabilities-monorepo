# REQ-030: Core persistence migrations


**UR:** UR-004
**Status:** done
**Created:** 2026-07-27
**Layer:** core
**Entry point:**
**Terminal state:**
**Parent:** REQ-029
**Closure proof:** checkpoint_log:passed commit:04ceea4 tests:MigrationCatalog+core
**Criteria approved:** agent-drafted
**Priority:** 3
**Size:** M
**Files:** packages/laravel-capabilities/database/migrations packages/laravel-capabilities/src/CapabilitiesServiceProvider.php packages/laravel-capabilities/tests/Unit/Persistence
**Depends on:**

## Task

Add package migrations for durable capability bus state: approvals, idempotency (and audit_outbox schema per spec package layout). Folder is currently empty while publish tag `capabilities-migrations` already exists. Migrations must support multi-tenant composite identity, TTL/expiry columns, and approval lease fields used by D-006.

## Context

Spec: `database/migrations/... # approvals, idempotency, audit_outbox — NOT telegram identities`. InMemory stores define required columns via contracts/phpstan types. Unit tests assert migration file presence and schema shape (Blueprint/string inspection or Schema builder fake) — do not require a live DB.

## Acceptance Criteria

- [x] Migration(s) create an approvals table with columns needed by `ApprovalStore` records (id, capability_name, status, tenant_id, actors, input/result JSON, idempotency_key, expires_at, execution_lease_until, execution_attempt, timestamps, etc.)
- [x] Migration(s) create an idempotency table with unique identity covering tenant/actor/capability/key (or equivalent unique index) plus request_hash, status, result, expires_at
- [x] Migration(s) create audit_outbox (or equivalent) table matching audit queue needs in spec / existing audit types — even if writer lands later, schema is present
- [x] No Telegram/messaging identity tables in core migrations
- [x] Provider still publishes `capabilities-migrations` pointing at these files
- [x] Unit tests prove migrations exist and define expected tables/indexes without running against MySQL/Postgres

## Verification Steps

1. **test** `composer test:core -- --filter=Migration 2>&1 | tail -40`
   - Expected: migration shape tests pass
2. **test** `test -n "$(ls packages/laravel-capabilities/database/migrations/* 2>/dev/null)" && echo HAS_MIGRATIONS`
   - Expected: HAS_MIGRATIONS

## Integration

**Reachability:** `php artisan vendor:publish --tag=capabilities-migrations` then host `migrate`

**Data dependencies:** contracts/phpstan types on ApprovalStore and IdempotencyStore records

**Service dependencies:** `CapabilitiesServiceProvider` publish tags

## Assets

- packages/laravel-capabilities/src/Contracts/ApprovalStore.php
- packages/laravel-capabilities/src/Contracts/IdempotencyStore.php

## Outputs

- packages/laravel-capabilities/database/migrations — approvals, idempotency, audit_outbox
- packages/laravel-capabilities/src/Persistence/MigrationCatalog.php — pure schema catalog
- packages/laravel-capabilities/tests/Unit/Persistence/MigrationCatalogTest.php — unit tests
