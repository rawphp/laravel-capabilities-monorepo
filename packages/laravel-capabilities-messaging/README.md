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
