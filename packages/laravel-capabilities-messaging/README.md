# rawphp/laravel-capabilities-messaging

Optional sibling package for conversation surfaces (Telegram first).

Implements core `ConversationIngress` / `ApprovalNotifier` contracts. **Never** embeds domain `run()` — chat feeds the agent; tools are the capability registry (D-007).

**Status:** 0.x pre-stable — **not Packagist-published**. Install via package VCS or monorepo path.

## Scope (this package)

| | |
|---|---|
| **Is** | Chat **ingress** (Telegram first): webhooks, identity link/allowlist, threads, approval notifiers; routes messages into the host-bound agent turn (`Contracts\AgentTurn`) with a configured tool profile |
| **Is not** | Domain `run()` or any second write path; the capability registry / governance stack; product CLI; AI turn/proposal engine; thread-history store (no history kept — L-006); general-purpose notification platform |

Requires [rawphp/laravel-capabilities](https://github.com/rawphp/laravel-capabilities). Developed in the monorepo; consumers install **this package repo**.

### Upgrade notes (0.x)

0.6.0 (full list: [CHANGELOG.md](CHANGELOG.md) 0.6.0):

- **Bind `Contracts\AgentTurn`** (`toolNames()`, `respond()`, `respondWithResults()`) to get replies. There is no echo default: unbound, a linked user's message gets no reply and `agent_turn_unbound` is logged.
- **Chat users resolve to your user model** (`user_model`, else `auth.providers.users.model`), not a `LinkedUser` DTO.
- **Custom `TelegramBotClient`** implementations must add `answerCallbackQuery()`; `calls()` is no longer part of the interface.
- **Custom `Identity\LinkStore`** implementations must add `forgetLink()` and `findTelegramUserId()`, and `putLink()` must keep the reverse index.
- **`allowlist` mode ignores code-bound links**: list every chat user in `identity.allowlist`.
- **Core is lockstep**: require the same version of `rawphp/laravel-capabilities` as this package.
- **Production needs a queue worker**: updates run on `ProcessTelegramUpdateJob`.

0.5.0: `Telegram\CallbackHandler` third constructor argument is `?ApprovalGateway` (was `?ApprovalManager`). Runtime still accepts `ApprovalManager` because it implements the gateway; update static analysis / manual type-hints. Pre-accept/reject lookup uses gateway `find()` (lazy pending TTL expiry, aligned with HTTP accept) — not `store()->find()`. Missing gateway throws `ApprovalGateway is required…`. Full consumer impact: [CHANGELOG.md](CHANGELOG.md) `[0.5.0]` **Breaking**.

| Doc | Where |
|---|---|
| User guide | [docs/user-guide.md](docs/user-guide.md) |
| Changelog | [CHANGELOG.md](CHANGELOG.md) |
| Core package | [rawphp/laravel-capabilities](https://github.com/rawphp/laravel-capabilities) |
| Sibling AI | [rawphp/laravel-capabilities-ai](https://github.com/rawphp/laravel-capabilities-ai) |
| Monorepo design | [laravel-capabilities-monorepo](https://github.com/rawphp/laravel-capabilities-monorepo) |

## Install

Requires `rawphp/laravel-capabilities` at the **same version** (`self.version`): tag `v0.Y.Z` installs only with core `v0.Y.Z`, and `dev-main` with core `dev-main`.

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

Turn on the core messaging surface too (`CAPABILITIES_SURFACE_MESSAGING=true`). Chat tool calls reach the registry as `caller: agent`, and core refuses any invoke carrying messaging metadata while `surfaces.messaging` is off.

Install policy: monorepo [`docs/versioning.md`](https://github.com/rawphp/laravel-capabilities-monorepo/blob/main/docs/versioning.md). How-to: [docs/user-guide.md](docs/user-guide.md).

**Tests and contributions:** the unit suite, `phpunit.xml`, and dev tooling live only in the [monorepo](https://github.com/rawphp/laravel-capabilities-monorepo); this package remote is a read-only split and ships no tests. Open issues and PRs against the monorepo and run `composer test:messaging` there.

## Production bindings (L-004)

`MessagingServiceProvider::register` **always** binds drivers and services (not gated on `telegram.enabled`). Only **webhook routes** load when `telegram.enabled` is true.

| Abstract | Production concrete | Testing / `driver=fake` |
|---|---|---|
| `MessagingConfig` | config repository | same |
| `UpdateQueue` | `LaravelUpdateQueue` → bus `ProcessTelegramUpdateJob` | `FakeQueue` |
| `TelegramBotClient` | `HttpTelegramBotClient` | `FakeTelegramBotClient` |
| `ProcessTelegramUpdate` | handler | same |
| `Contracts\AgentTurn` | **host binds** (e.g. a `laravel/ai` agent) — unbound: no reply, error log | unit tests inject a handler |
| `TelegramWebhookController` | injects bound `UpdateQueue` (no FakeQueue default) | inject `FakeQueue` in unit tests |
| `TelegramApprovalNotifier` | `Rawphp\CapabilitiesMessaging\Notifiers\TelegramApprovalNotifier` (Bot API) | unit tests may inject fakes |

Drivers (`config/capabilities-messaging.php`):

- `queue_driver` (`CAPABILITIES_MESSAGING_QUEUE_DRIVER`, default `auto`): `auto` \| `laravel` \| `fake` — `auto` → fake when `APP_ENV=testing`, otherwise Laravel bus
- `bot_driver` (`CAPABILITIES_MESSAGING_BOT_DRIVER`, default `auto`): `auto` \| `http` \| `fake` — `auto` → fake when testing, otherwise HTTP

`ProcessTelegramUpdateJob` implements `ShouldQueue`: the webhook answers Telegram once the update is queued, and a queue worker runs the agent turn (3 tries, backoff 10s/60s). Transient Bot API failures sending the reply (429/5xx) keep the reply in your cache and throw `RetryableUpdateFailure`, so the job retries and lands in `failed_jobs`; a retry only re-sends that reply (no second agent turn or tool invoke). Everything else returns without retrying. Capability results (output, `approval_required`, refusals, retryable failures) go back to the agent through `AgentTurn::respondWithResults()`, whose text is the reply.

Fake\* classes bind only when the matching driver is `fake`, or `auto` with `APP_ENV=testing`. Unit tests never call the live Telegram network; inject a transport on `HttpTelegramBotClient` or use `bot_driver=fake`.

**Notifier FQCN:** production is messaging `…Notifiers\TelegramApprovalNotifier`. Core’s `RecordingTelegramApprovalNotifier` is the test recording double; core’s deprecated empty `…Approval\Notifiers\TelegramApprovalNotifier` is a soft-landing alias only — do not use it in hosts.

## Identity storage and the thread residual (L-006)

Link codes and code-bound identity links live in a `Identity\LinkStore`. The container binds `CacheLinkStore` on your default Laravel cache store (`Illuminate\Contracts\Cache\Repository`), so a code issued in a web request binds on the queue worker and links survive restarts. Codes expire with `identity.code_ttl_seconds` and are single-use across workers. Links have no expiry: use a persistent cache store (redis, database) that deploys do not flush. A lost link fails closed and the user links again. To use another store, bind `LinkStore` yourself, e.g. `new CacheLinkStore(Cache::store('redis'))`. Allowlist entries stay in config. Stored links resolve only in `code_link` mode: in `allowlist` mode only config entries resolve, so switching modes revokes code-bound users. To revoke one user, call `IdentityLinker::unlinkUser($userId, $tenantId)` (or `unlink($telegramUserId)`); linking a user from a second Telegram account revokes the first.

**Not silent (L-006):** messaging keeps **no thread history**, durable or otherwise. Each message gets a `thread_id` derived from chat + topic (`tg:<chat>:<topic>`) and passed to your `AgentTurn`; if the agent needs earlier turns, store them in your `AgentTurn` keyed by that id. The update pipeline writes nothing to `ThreadStore`, so a long-lived queue worker does not grow with chat traffic.

