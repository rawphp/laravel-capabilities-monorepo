# Messaging package: rawphp/laravel-capabilities-messaging

> Ships with the **laravel-capabilities-messaging** package (this file is at `docs/user-guide.md` in the package repo). Package root: [README.md](../README.md).

Optional **sibling** package for conversation surfaces. Telegram first; other chat products may follow the same contracts.

**Namespace:** `Rawphp\CapabilitiesMessaging\`  
**Depends on:** `rawphp/laravel-capabilities`  
**Status:** 0.x pre-stable, path or package-repo VCS install (not Packagist-published)

Messaging implements core conversation contracts (`ConversationIngress` / identity / approval notify). It does **not** embed domain `run()`. Chat feeds the agent; **tools are the capability registry** (design D-007).

## Before you start

- Core package installed and at least one capability defined for the agent profile you will expose
- Optional but typical: `laravel/ai` available for agent turns behind ingress
- A Telegram bot token and webhook secret values for real traffic
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

Publish config and migrations when you need them:

```bash
php artisan vendor:publish --tag=capabilities-messaging-config
php artisan vendor:publish --tag=capabilities-messaging-migrations
```

## Configure

Config file: `config/capabilities-messaging.php` (merged from the package).

| Key | Role | Default / env |
|---|---|---|
| `telegram.enabled` | Load Telegram webhook routes when true | `CAPABILITIES_TELEGRAM` (scaffold default true in package config) |
| `telegram.bot_token` | Bot API token | `TELEGRAM_BOT_TOKEN` |
| `telegram.webhook_secret` | Webhook verification secret | `TELEGRAM_WEBHOOK_SECRET` |
| `telegram.callback_secret` | Callback signing secret | `TELEGRAM_CALLBACK_SECRET` or webhook secret |
| `telegram.callback_ttl_seconds` | Callback freshness | `TELEGRAM_CALLBACK_TTL_SECONDS` (900) |
| `agent_profile` | D-008 profile for bot tool list — **never full catalog** | `CAPABILITIES_MESSAGING_AGENT_PROFILE` (default `support`) |
| `identity.mode` | `code_link` or `allowlist` | `CAPABILITIES_MESSAGING_IDENTITY_MODE` |
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

Point Telegram’s webhook at your app URL for that path. The controller is a thin edge over `TelegramWebhookController::handle`.

## Identity

Before agent tools may mutate as a user, messaging maps the chat principal to a product user.

### `code_link` (default)

1. Your app issues a one-time code for a Laravel user (`IdentityLinker::issueLinkCode`).
2. The Telegram user presents the code.
3. `bindWithCode` binds telegram user id → Laravel user (rejects expired, reused, or unknown codes).

Codes expire per `identity.code_ttl_seconds`. Client-forged `laravel_user_id` values are never trusted.

### `allowlist`

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

## Agent profile

Set `agent_profile` to a profile name that exists in core agent surface config (or is otherwise resolvable by the profile selector). Default config value is `support`.

Without a tight profile, you either fail closed (require_profile) or risk exposing too many tools. Profiles **do not** replace capability `authorize()`.

## Approval notifications

Core owns approval state and HTTP accept/reject. Messaging supplies conversation-side notification behaviour implementing core’s `ApprovalNotifier` contract so humans can act from chat where wired. Domain execution still resumes through the core approval/registry path — not a second `run()` in messaging.

## What this package must not do

- Call Eloquent/domain `run()` outside the registry / agent tools
- Store Bot API logic inside `rawphp/laravel-capabilities` core
- Trust chat-supplied tenant or user ids without link/allowlist
- Dump the full capability catalog into the bot

## How you know it worked

- Provider boots; webhook route present when Telegram is enabled.
- First authorized webhook with valid secrets returns an `ok` JSON response shape from the route (`ok` / `error` / HTTP status from the controller result).
- Unlinked users cannot exercise mutating tools until identity bind succeeds.
- Bot tool list matches the configured agent profile, not the entire registry.

## If something goes wrong

Troubleshooting (monorepo): [Messaging / Telegram](https://github.com/rawphp/laravel-capabilities-monorepo/blob/main/docs/troubleshooting.md#messaging-telegram).

## Related

- [Package README](../README.md)
- [CHANGELOG](../CHANGELOG.md)
- Core package: [rawphp/laravel-capabilities](https://github.com/rawphp/laravel-capabilities) · [user guide](https://github.com/rawphp/laravel-capabilities/blob/main/docs/user-guide.md)
- Getting started (monorepo): [optional messaging](https://github.com/rawphp/laravel-capabilities-monorepo/blob/main/docs/getting-started.md#4-optional-messaging-telegram)
- Design (monorepo): [spec.md](https://github.com/rawphp/laravel-capabilities-monorepo/blob/main/docs/spec.md) (D-007, D-008, D-021)
