# Messaging package: rawphp/laravel-capabilities-messaging

> Ships with the **laravel-capabilities-messaging** package (this file is at `docs/user-guide.md` in the package repo). Package root: [README.md](../README.md).

Optional **sibling** package for conversation surfaces. Telegram first; other chat products may follow the same contracts.

**Namespace:** `Rawphp\CapabilitiesMessaging\`  
**Depends on:** `rawphp/laravel-capabilities`  
**Status:** 0.x pre-stable, path or package-repo VCS install (not Packagist-published)

Messaging implements core conversation contracts (`ConversationIngress` / identity / approval notify). It does **not** embed domain `run()`. Chat feeds the agent; **tools are the capability registry** (design D-007).

## Before you start

- Core package installed at the **same version** as this package (`self.version` lockstep), and at least one capability defined for the agent profile you will expose
- Core's messaging surface on (`CAPABILITIES_SURFACE_MESSAGING=true`): core refuses any invoke carrying messaging metadata while `surfaces.messaging` is off
- Optional but typical: `laravel/ai` available for agent turns behind ingress
- A Telegram bot token and webhook secret values for real traffic
- A queue worker (updates run on `ProcessTelegramUpdateJob`) and a persistent cache store (link codes, links, turn markers)
- Concepts (monorepo): [Messaging sibling](https://github.com/rawphp/laravel-capabilities-monorepo/blob/main/docs/concepts.md#messaging-sibling)

## Install

See [package README](../README.md#install) for VCS and path options. Minimal VCS:

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

```bash
composer update rawphp/laravel-capabilities-messaging
```

Auto-discovery loads `Rawphp\CapabilitiesMessaging\MessagingServiceProvider`.

Publish config when you need overrides:

```bash
php artisan vendor:publish --tag=capabilities-messaging-config
```

**Migrations:** the package still exposes publish tag `capabilities-messaging-migrations`, but the migrations directory is **empty** today (only a placeholder). Link codes and identity links live in your Laravel cache (see Identity below) and messaging keeps no thread history (L-006), so there is no package schema to migrate yet. Do not expect `php artisan migrate` to create messaging tables after publishing that tag.

## Configure

Config file: `config/capabilities-messaging.php` (merged from the package).

| Key | Role | Default / env |
|---|---|---|
| `telegram.enabled` | Load Telegram webhook routes when true; when false the approval notifier sends nothing (kill switch) | `CAPABILITIES_TELEGRAM` (scaffold default true in package config) |
| `telegram.bot_token` | Bot API token | `TELEGRAM_BOT_TOKEN` |
| `telegram.webhook_secret` | Webhook verification secret | `TELEGRAM_WEBHOOK_SECRET` |
| `telegram.callback_secret` | Callback signing secret | `TELEGRAM_CALLBACK_SECRET` or webhook secret |
| `telegram.callback_ttl_seconds` | Callback freshness | `TELEGRAM_CALLBACK_TTL_SECONDS` (900) |
| `telegram.turns_per_minute` | D-013 agent turns per chat per minute (core `RateLimiter`; `0` disables). Over the cap → `rate_limited`, no reply | `CAPABILITIES_MESSAGING_TURNS_PER_MINUTE` (20) |
| `queue_driver` | `auto` \| `laravel` \| `fake`; `auto` is `fake` when `APP_ENV=testing`, else the Laravel bus | `CAPABILITIES_MESSAGING_QUEUE_DRIVER` (`auto`) |
| `bot_driver` | `auto` \| `http` \| `fake`; `auto` is `fake` when `APP_ENV=testing`, else HTTP | `CAPABILITIES_MESSAGING_BOT_DRIVER` (`auto`) |
| `agent_profile` | D-008 profile for bot tool list — **never full catalog** | `CAPABILITIES_MESSAGING_AGENT_PROFILE` (default `support`) |
| `user_model` | Host user model linked chat users resolve to (the `actor` capabilities see); `null` falls back to `auth.providers.users.model` | `CAPABILITIES_MESSAGING_USER_MODEL` |
| `identity.mode` | `code_link` or `allowlist`; any other value fails `TelegramSetup::validate()` / `runOrFail()` | `CAPABILITIES_MESSAGING_IDENTITY_MODE` (`code_link`) |
| `identity.code_ttl_seconds` | Link code lifetime | `CAPABILITIES_MESSAGING_LINK_CODE_TTL` (600) |
| `identity.allowlist` | Static telegram ↔ Laravel user maps | `[]` |
| `skip_boot_checks` | Skip deferred secret checks in **non-production CI only** | `CAPABILITIES_SKIP_BOOT_CHECKS` — ignored / fails closed in production |

### Secrets are not validated at boot (D-021)

Artisan migrate and ordinary boot must work without Telegram env vars. Secrets are validated on **first webhook / setup / outbound notify**. Do not rely on boot failures to tell you the token is missing — configure before real traffic and exercise the webhook path.

## HTTP surface

When `telegram.enabled` is true, the package registers:

| Method | Path | Name |
|---|---|---|
| `POST` | `/capabilities/messaging/telegram/webhook` | `capabilities.messaging.telegram.webhook` |

Point Telegram’s webhook at your app URL for that path, and set its `secret_token` to `TELEGRAM_WEBHOOK_SECRET`: every request must carry it in the `X-Telegram-Bot-Api-Secret-Token` header. The controller is a thin edge over `TelegramWebhookController::handle`: `200` once the update is queued, `401` `invalid_webhook_secret`, `400` `invalid_body`, `503` when the bot token or webhook secret is not configured, `500` when queueing fails.

## Identity

Before agent tools may mutate as a user, messaging maps the chat principal to a product user.

A linked Telegram user resolves to an instance of your user model (`user_model`, else `auth.providers.users.model`) loaded with `Model::query()->find($id)`, so capability `authorize()` and policies receive the same type as on HTTP. If no model is configured, or the linked id no longer resolves, the message fails closed (no tools, no reply). This is checked on the first linked-user message, not at boot.

### `code_link` (default)

1. Your app issues a one-time code for a Laravel user (`IdentityLinker::issueLinkCode`).
2. The Telegram user sends `/start <code>` or `/link <code>` to the bot. A deep link `https://t.me/<bot>?start=<code>` sends `/start <code>` for them.
3. The update pipeline calls `bindWithCode` before identity resolution and replies with a fixed confirmation or refusal; no agent turn or tool runs. Expired, reused, or unknown codes are refused (`link_code_invalid`, logged as a warning).

The command is only recognised in `code_link` mode.

Codes and links are stored in your default Laravel cache store through `Identity\CacheLinkStore`, so a code issued in a web request binds when the queue worker handles the `/start` update, and links survive worker restarts. Codes expire per `identity.code_ttl_seconds` and bind at most once, even when two workers see the same code. Links are stored without expiry, so use a persistent cache store (redis, database) that your deploy does not flush; a lost link fails closed and the user links again. To pick another store, bind `Identity\LinkStore` in your app, e.g. `new CacheLinkStore(Cache::store('redis'))`. A custom `LinkStore` implements `putCode`, `takeCode` (returns a code at most once, even to concurrent workers), `putLink` (keeping the product user → Telegram user reverse index), `findLink`, `forgetLink` and `findTelegramUserId`.

A product user has at most one linked Telegram account per tenant: binding a code from another account revokes the earlier link. To revoke a link yourself (a "disconnect Telegram" button, offboarding, a lost or hijacked Telegram account), call `IdentityLinker::unlinkUser($userId, $tenantId)`, or `IdentityLinker::unlink($telegramUserId)` when you know the Telegram id. Both work in any identity mode and leave other users' links alone. Links stored before `unlinkUser()` existed have no reverse index, so `unlinkUser()` finds them only after the user links again; `unlink()` works on them.

Client-forged `laravel_user_id` values are never trusted.

### `allowlist`

Only static entries may bind. `bindWithCode` returns `null` in this mode (and under any unrecognized mode), so a code issued elsewhere cannot bypass the allowlist.

Only static entries resolve, too. Links bound earlier in `code_link` mode stay in the cache but are ignored, so switching to `allowlist` revokes every code-bound user at once (switching back to `code_link` restores them; `unlink()` / `unlinkUser()` drop one for good). `IdentityLinker::link()` throws in this mode.

Static entries:

```php
'allowlist' => [
    [
        'telegram_user_id' => '123456789',
        'laravel_user_id' => '42',
        'tenant_id' => 'optional-tenant',
    ],
],
```

Allowlist ids are not checked at boot. Check them in your deploy or setup step with a lookup against your user model, so a stale or mistyped `laravel_user_id` fails before a chat message arrives:

```php
use Rawphp\CapabilitiesMessaging\Boot\TelegramSetup;
use Rawphp\CapabilitiesMessaging\MessagingConfig;

TelegramSetup::runOrFail(app(MessagingConfig::class), fn (string $id) => User::find($id));
```

`runOrFail` throws naming each bad entry (`identity.allowlist[1]: laravel_user_id "999" …`). Entries missing either id fail even without a lookup. The same call also fails on a missing bot token or webhook secret, an empty `agent_profile`, an unknown `identity.mode`, or a `telegram_user_id` listed twice. A duplicated `telegram_user_id` also makes `IdentityLinker` throw when it is first resolved.

## Agent turn (required for replies)

Messaging does not build the agent. Bind `Rawphp\CapabilitiesMessaging\Contracts\AgentTurn` in your app, usually around a `laravel/ai` agent:

```php
use Rawphp\CapabilitiesMessaging\Contracts\AgentTurn;

$this->app->singleton(AgentTurn::class, SupportChatAgentTurn::class);
```

- `toolNames(string $profile): list<string>` — capability names the profile exposes (e.g. the names from `Capability::aiTools($profile)`). Tool calls outside this list are refused.
- `respond(array $message): array{text, tool_calls?}` — run one turn. `$message` carries `channel`, `chat_id`, `text`, the linked `user`, `thread_id`, `profile`, `tools` and `messaging` metadata. `thread_id` is stable per chat + topic; messaging keeps no history, so store earlier turns yourself (keyed by `thread_id`) if the agent needs them. Return tool calls as `['name' => …, 'input' => […]]`. With no tool calls, `text` is the reply.
- `respondWithResults(array $message, array $toolResults): array{text}` — answer the tool results. Messaging invokes the tool calls in order through the capability bus as `caller: agent`, with per-update idempotency keys, and stops at the first result that is not ok. Each entry is `['name' => …, 'input' => […], 'result' => CapabilityResult]`: output (`isOk()`, `data`), approval pending (`isApprovalRequired()`, `approvalId()`), or a refusal or transient failure (`errorCode()`, `isRetryable()`). Messaging does not retry a transient failure, so tell the user to try again. The returned `text` is the reply; tool calls in it are ignored (one tool round per message).

Replies go into the same forum topic when the message came from one. A reply over Telegram's 4096-character limit is sent as consecutive messages, split at paragraph, then line, then word breaks; if a transient failure interrupts it, the queue retry sends only the parts not yet delivered. An answer with no text is replied to with `Done.`, or `That did not go through (<error code>).` when the last tool call failed. An unlinked user writing in a private chat, in `code_link` mode, gets a fixed reply telling them to link from the app; groups and `allowlist` mode stay silent.

With no `AgentTurn` bound, a linked user's message gets **no reply** and an `agent_turn_unbound` error is logged; the profile exposes no tools.

## Agent profile

Set `agent_profile` to a profile name that exists in core agent surface config (or is otherwise resolvable by the profile selector). Default config value is `support`. Messaging passes it to `AgentTurn::toolNames()`, in the `respond()` message, and on every tool invoke as the `tool_profile` option, so core audit entries record which profile gated the call. An empty value fails every chat message (`capabilities-messaging.agent_profile is required…`).

Without a tight profile, you either fail closed (require_profile) or risk exposing too many tools. Profiles **do not** replace capability `authorize()`.

## Approval notifications

Core owns approval state and HTTP accept/reject. Messaging supplies conversation-side notification behaviour implementing core’s `ApprovalNotifier` contract so humans can act from chat where wired. Domain execution still resumes through the core approval/registry path — not a second `run()` in messaging.

**How notifications reach chat:** the provider binds the notifier to core's `ApprovalNotifier` contract and tags it with `ApprovalNotifier::CONTAINER_TAG`; core attaches every such notifier to its single `ApprovalManager`, so each `approval_required` calls `notifyPending()`. Only approval rows that came from a chat carry `messaging.chat_id` (core records the originating invoke's messaging meta on the row); requests made over HTTP, the CLI or a job have no chat target and are skipped silently.

**What the approver sees:** the message is built from the approval row only: `Approval required: <capability>`, the row's `summary` when one is set, then the stored input with sensitive keys redacted (core `Redactor`: keys containing password, secret, token, apikey or authorization), one `key: <JSON value>` line per field, cut to Telegram's 4096-character limit with an ellipsis. Tap Accept on what the input says, not on the agent's chat reply describing it. The buttons land in the forum topic the request came from (`messaging.topic_id` → `message_thread_id`). If the Bot API call fails, the notifier writes an `approval.notify_failed` audit entry (when core's `AuditWriter` is bound) and rethrows; core reports the failure and the approval stays pending.

**Production notifier FQCN:** `Rawphp\CapabilitiesMessaging\Notifiers\TelegramApprovalNotifier` (Bot API). Core’s `RecordingTelegramApprovalNotifier` is a unit-test recording double; core’s deprecated empty `Rawphp\Capabilities\Approval\Notifiers\TelegramApprovalNotifier` is soft-landing only — do not bind it in hosts.

**Callback handler:** `Telegram\CallbackHandler` is container-bound and invoked by the default webhook → `ProcessTelegramUpdate` path: a `callback_query` update (a tapped Accept / Reject button) is decoded with `TelegramCallbackSigner::decode()` and routed to accept / reject through core's `ApprovalGateway` (`find` / `accept` / `reject`) — never the concrete `ApprovalManager`, never raw `store()->find()`, and never the host `AgentTurn`. The tap is acknowledged with `answerCallbackQuery` (`Approved.`, `Rejected.`, `This approval was already decided.`, `Unknown approval.`, `You are not allowed to decide this approval.`, `Approved, but the action did not complete.`, `This button is no longer valid.`). Core records the decision with `decided_via` (`channel: telegram`, the tapper's Telegram id). The toast follows core's result, not the button: a tapper the approval policy refuses is told so (`forbidden`, the approval stays pending), a lost race or expired row reads as already decided, and an accepted approval whose run fails reports `failed`; any of these ends the update with `ok=false` and the core error code, logged as a warning. Without a callback secret, taps are answered `Approvals cannot be decided from chat right now.` and logged as an error (`callback_handler_unavailable`). The handler needs core's `ApprovalGateway`, which core's provider always binds; if it is missing, the tap fails with `ApprovalGateway is required…`, logged as an error, and gets no toast. Lazy pending TTL runs on gateway `find()` (same mechanism as HTTP). After expiry the handler reports `already_handled`; HTTP accept maps the same expired row to `expired` / HTTP 410 — shared lookup, not identical response shapes. A non-empty signed `approver_hint` must equal the tapping user's linked product user id (`id`, else `getAuthIdentifier()`); otherwise the handler returns `forbidden` / `approver_mismatch` without calling the gateway. The notifier signs the `approver_hint` of the approval array it is given; core's approval rows carry none, so with default wiring the hint is empty and the approval policy decides alone.

Button `callback_data` is a compact token that fits Telegram's 64-byte limit: `{a|r}.{approval_id}.{exp}.{sig}` (sig is a 96-bit truncated HMAC). The approver hint is signed but not transmitted. If you route taps yourself, pass the raw token to `CallbackHandler::handleCallbackData($callbackQuery['data'], $callbackQuery['from'])` (or decode it with `TelegramCallbackSigner::decode()` and call `handle()`): a token bound to a different user than the one who tapped reads as `invalid` / `invalid_signature_or_expired`. Approval ids longer than 38 bytes do not fit and make `notifyPending()` throw rather than send a button Telegram would reject. See package [CHANGELOG](../CHANGELOG.md) `[0.5.0]` **Breaking** for the `ApprovalGateway` consumer impact list (type-hint, TTL outcome, exception text).

## What this package must not do

- Call Eloquent/domain `run()` outside the registry / agent tools
- Store Bot API logic inside `rawphp/laravel-capabilities` core
- Trust chat-supplied tenant or user ids without link/allowlist
- Dump the full capability catalog into the bot

## How you know it worked

- Provider boots; webhook route present when Telegram is enabled.
- First authorized webhook with valid secrets returns an `ok` JSON response shape from the route (`ok` / `error` / HTTP status from the controller result).
- Unlinked users cannot exercise mutating tools until identity bind succeeds.
- A linked user's message gets your `AgentTurn` reply (not an echo of their text).
- Bot tool list matches the configured agent profile, not the entire registry.

## If something goes wrong

Webhook rejections are written to your app logger with a `phase` (`secrets`, `verify_webhook_secret`, `body`, `queue`): a bad secret or empty body logs as `warning`, missing secrets or a queue failure as `error`. Update-processing failures carry `tags` `channel`, `chat_id` and `update_id`; unlinked users, rate-limited chats, refused link codes, tool calls that are not ok and approval taps that were not applied log as `warning`, anything else as `error`. Refused identity binds (forged bind, cross-tenant resolve) also count `messaging_identity_bind_denied_total` on core's `Metrics` when it is bound. Updates run on your queue: a transient Telegram failure (429/5xx) sending the reply keeps the reply in your default cache store for an hour and fails the job so it retries and ends in `failed_jobs`. The retry only re-sends that reply; the agent turn and its tool calls run once per update. Each update also claims a marker in the same cache store before its turn, so a redelivery after a worker timeout or crash (or Telegram resending the webhook) ends with `turn_already_started`, logged as a warning, instead of calling the agent again; that update gets no reply. `ProcessTelegramUpdateJob` sets `$timeout = 120` seconds for two agent calls, the tool invokes and the reply: keep your queue connection's `retry_after` above it. Without a cache store (container-free `MessagingBindings::build()`) there is no marker: a redelivered update runs a new turn, and its tool calls replay only when the new answer repeats the same call at the same position (idempotency key `telegram:<chat>:<update_id>:<index>`). Capability results, retryable or not, go back to your `AgentTurn` and never retry the update.

Troubleshooting (monorepo): [Messaging / Telegram](https://github.com/rawphp/laravel-capabilities-monorepo/blob/main/docs/troubleshooting.md#messaging-telegram).

## Related

- [Package README](../README.md)
- [CHANGELOG](../CHANGELOG.md)
- Core package: [rawphp/laravel-capabilities](https://github.com/rawphp/laravel-capabilities) · [user guide](https://github.com/rawphp/laravel-capabilities/blob/main/docs/user-guide.md)
- Getting started (monorepo): [optional messaging](https://github.com/rawphp/laravel-capabilities-monorepo/blob/main/docs/getting-started.md#4-optional-messaging-telegram)
- Design (monorepo): [spec.md](https://github.com/rawphp/laravel-capabilities-monorepo/blob/main/docs/spec.md) (D-007, D-008, D-021)
