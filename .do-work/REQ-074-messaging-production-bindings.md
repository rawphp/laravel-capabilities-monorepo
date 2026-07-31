# REQ-074: Messaging production bindings (no FakeQueue default)

**UR:** UR-012
**Status:** backlog
**Created:** 2026-07-31
**Layer:** messaging
**Entry point:**
**Terminal state:**
**Parent:**
**Closure proof:**
**Criteria approved:** agent-drafted
**Priority:** 3
**Size:** L
**Files:** packages/laravel-capabilities-messaging/src/MessagingServiceProvider.php, packages/laravel-capabilities-messaging/src/Boot/MessagingBindings.php, packages/laravel-capabilities-messaging/src/Telegram/**, packages/laravel-capabilities-messaging/src/Support/**, packages/laravel-capabilities-messaging/tests/Unit/**
**Depends on:**

## Task

MessagingServiceProvider must register production bindings when telegram surface enabled; TelegramWebhookController must not default to FakeQueue outside testing; provide real UpdateQueue adapter (Laravel bus/job) and HTTP bot client plan (L-004). Keep Fake* for unit tests. Defer durable IdentityLinker/ThreadStore DB (L-006) — document residual.

## Context

Critical: provider never binds services; webhook defaults FakeQueue; MessagingBindings builds FakeTelegramBotClient + FakeQueue as 'production plan'.

## Acceptance Criteria

- [ ] MessagingServiceProvider::register binds MessagingConfig, UpdateQueue, TelegramBotClient, ProcessTelegramUpdate (or documented production plan) when messaging/telegram enabled
- [ ] Webhook controller does not construct FakeQueue when container can resolve UpdateQueue
- [ ] FakeQueue / FakeTelegramBotClient only used when driver=fake or app testing (or explicit test injection)
- [ ] Unit tests cover production binding selection vs fake/testing
- [ ] README or code comment notes L-006 residual (memory identity/threads still not durable) — not silent
- [ ] No real Telegram network in tests

## Verification Steps

1. **test** `composer test:messaging`
   - Expected: messaging suite green
2. **runtime** `rg -n 'FakeQueue|register\(|UpdateQueue' packages/laravel-capabilities-messaging/src/MessagingServiceProvider.php packages/laravel-capabilities-messaging/src/Telegram/TelegramWebhookController.php packages/laravel-capabilities-messaging/src/Boot/MessagingBindings.php`
   - Expected: provider registers bindings; webhook does not hard-default FakeQueue for production path

## Integration

**Reachability:** MessagingServiceProvider boot → routes/messaging.php webhook
**Data dependencies:** messaging config, telegram secrets
**Service dependencies:** core conversation contracts; UpdateQueue; TelegramBotClient
