# rawphp/laravel-capabilities-messaging

Optional sibling package for conversation surfaces (Telegram first).

Implements core `ConversationIngress` / `ApprovalNotifier` contracts. **Never** embeds domain `run()` — chat feeds the agent; tools are the capability registry (D-007).

**Status:** 0.x pre-stable — **not Packagist-published**. Install via package VCS or monorepo path.

| Doc | Where |
|---|---|
| User guide | [docs/user-guide.md](docs/user-guide.md) |
| Changelog | [CHANGELOG.md](CHANGELOG.md) |
| Core package | [rawphp/laravel-capabilities](https://github.com/rawphp/laravel-capabilities) |
| Monorepo design | [laravel-capabilities-monorepo](https://github.com/rawphp/laravel-capabilities-monorepo) |

## Install

Requires `rawphp/laravel-capabilities`.

### VCS (package remotes)

This tree is published to [github.com/rawphp/laravel-capabilities-messaging](https://github.com/rawphp/laravel-capabilities-messaging) from the monorepo on every push to `main`.

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/rawphp/laravel-capabilities"
    },
    {
      "type": "vcs",
      "url": "https://github.com/rawphp/laravel-capabilities-messaging"
    }
  ],
  "require": {
    "rawphp/laravel-capabilities": "dev-main",
    "rawphp/laravel-capabilities-messaging": "dev-main"
  }
}
```

### Path (monorepo contributors)

Point path repos at `packages/laravel-capabilities` and `packages/laravel-capabilities-messaging` in a monorepo clone, require `*@dev`.

```bash
composer update rawphp/laravel-capabilities-messaging
php artisan vendor:publish --tag=capabilities-messaging-config
```

Install policy: monorepo [`docs/versioning.md`](https://github.com/rawphp/laravel-capabilities-monorepo/blob/main/docs/versioning.md). How-to: [docs/user-guide.md](docs/user-guide.md).

## Production bindings (L-004)

When Telegram is enabled, `MessagingServiceProvider::register` binds:

| Abstract | Production concrete | Testing / `driver=fake` |
|---|---|---|
| `MessagingConfig` | config repository | same |
| `UpdateQueue` | `LaravelUpdateQueue` → bus `ProcessTelegramUpdateJob` | `FakeQueue` |
| `TelegramBotClient` | `HttpTelegramBotClient` | `FakeTelegramBotClient` |
| `ProcessTelegramUpdate` | handler | same |
| `TelegramWebhookController` | injects bound `UpdateQueue` (no FakeQueue default) | inject `FakeQueue` in unit tests |

Drivers (`config/capabilities-messaging.php`):

- `queue_driver`: `auto` \| `laravel` \| `fake` — `auto` → fake when `APP_ENV=testing`, otherwise Laravel bus
- `bot_driver`: `auto` \| `http` \| `fake` — `auto` → fake when testing, otherwise HTTP

Unit tests never call the live Telegram network; inject a transport on `HttpTelegramBotClient` or use `bot_driver=fake`.

## Residual: durable identity / threads (L-006)

**Not silent:** `IdentityLinker` and `ThreadStore` remain **process-local in-memory** stores. They are **not durable** across processes or deploys. Durable DB-backed identity linking and thread history are **deferred (L-006)** — plan for host-level persistence or a future package revision before multi-instance production traffic depends on them.

