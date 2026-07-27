# REQ-017: Messaging package Telegram bus

**UR:** UR-001
**Status:** backlog
**Created:** 2026-07-27
**Layer:** messaging
**Entry point:** Telegram webhook → identity → thread → agent tools / approval callbacks
**Terminal state:** composer test:messaging exits 0; messaging implements core contracts only; no domain run outside registry.
**Parent:** 
**Closure proof:**
**Criteria approved:** agent-drafted
**Priority:** 2
**Size:** L
**Files:** packages/laravel-capabilities-messaging packages/laravel-capabilities-messaging/src packages/laravel-capabilities-messaging/tests
**Depends on:** REQ-016

## Task

Implement laravel-capabilities-messaging: Telegram webhook security, process update pipeline, identity linker, threads, approval notifier, config/boot. Flesh all messaging Unit tests with mocked Bot API. Never call Eloquent domain run outside registry/agent tools (D-007). Messaging defaults off until package installed.

## Context

Sibling package only; core owns conversation ingress contracts.

Original brief: implement all package tests and business logic so all tests pass; tests are source of truth; on gaps/conflicts use docs/spec.md and update tests.

## Acceptance Criteria

- [ ] Telegram/* Identity/* Threads/* Notifiers/* Boot/* Config/* Architecture/* messaging tests pass
- [ ] No domain run outside registry path
- [ ] Mocks only — no real Telegram network
- [ ] Coverage ≥95% on packages/laravel-capabilities-messaging/src

- [ ] Unauthenticated or invalid Telegram webhook payloads are rejected without invoking registry/run

## Verification Steps

1. **test** `composer test:messaging 2>&1 | tail -40`
   - Expected: Exit 0 all passed
2. **test** `pest --configuration=packages/laravel-capabilities-messaging/phpunit.xml --coverage --min=95 2>&1 | tail -40`
   - Expected: Coverage ≥95%

## Integration

**Reachability:** MessagingServiceProvider + Telegram webhook routes when package installed

**Data dependencies:** Identity links + thread store fakes

**Service dependencies:** Core CapabilityRegistry / agent tools / approval notifier contracts

## Assets

- docs/spec.md — design bible / conflict oracle
- docs/requirements-inventory.md — scenario checklist
