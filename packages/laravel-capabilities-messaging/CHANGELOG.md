# Changelog

All notable changes to `rawphp/laravel-capabilities-messaging` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html)
with **0.x pre-stable** expectations (breaking changes allowed without a major bump while major is 0).

Monorepo packaging policy:  
https://github.com/rawphp/laravel-capabilities-monorepo/blob/main/docs/versioning.md

## [Unreleased]

### Security

- **CallbackHandler approver binding** — a non-empty signed `approver_hint` now binds the
  callback to that product principal id (the linked user's `id`, else `getAuthIdentifier()`).
  A different linked Telegram user clicking a forwarded/leaked button gets
  `status: forbidden`, `message: approver_mismatch` before the approval gateway is touched.
  Empty hint keeps the old behaviour (approval policy decides). **Consumer impact:** hosts
  that put a Telegram user id (or anything other than the product user id) in
  `approver_hint` must switch to the product user id or send an empty hint.

### Changed

- **Core constraint is lockstep:** `require.rawphp/laravel-capabilities` is now `self.version` instead of `*`. This package at tag `v0.Y.Z` installs only with core `v0.Y.Z` (and `dev-main` with core `dev-main`). **Hosts:** require the same version of core and this package.

### Added

- **Audited tool profile** — Telegram tool calls pass the configured `agent_profile` as the
  `tool_profile` invoke option, so core audit entries record which profile gated the call.
- **Allowlist user check in setup validation** — `TelegramSetup::validate()` / `runOrFail()`
  take an optional host user lookup `(laravelUserId, tenantId) => ?object`. Each
  `identity.allowlist` entry whose `laravel_user_id` does not resolve fails setup loudly
  (index, user id, telegram id in the message) instead of linking a user that does not exist.
  Entries missing `telegram_user_id` or `laravel_user_id` (previously skipped silently at
  runtime) now fail setup with or without a lookup.

## [0.5.0] - 2026-08-07

Cumulative: entries shipped in tags `v0.1.0` through `v0.5.0`. Those tags did not get
per-tag sections; this file's git history shows the tag each entry first shipped in.

### Breaking

- **CallbackHandler ApprovalGateway (consumer impact)** — constructor third arg type-hint
  is now `?ApprovalGateway` (was `?ApprovalManager`). Runtime still accepts `ApprovalManager`
  because it implements the gateway, but static analysis / manual constructors hard-coding
  `ApprovalManager` as the third parameter type need updating. Pre-accept/reject lookup uses
  `ApprovalGateway::find()` (lazy pending TTL expiry, aligned with HTTP accept) instead of
  `store()->find()`. **Consumer impact:** (1) type-hint: hosts/static analysis using
  `ApprovalManager` on the third arg must switch to `ApprovalGateway`; (2) lazy TTL:
  Telegram callback outcomes for TTL-stale *pending* rows can change (`already_handled` /
  expired path vs a prior accept/reject attempt that ignored lazy expiry); (3) exception
  string: missing-approvals `RuntimeException` message renamed
  `ApprovalManager is required…` → `ApprovalGateway is required…` (host tests asserting the
  old string break).

### Changed

- **Internal extract** — `TelegramUpdateParser` peels pure Update field extraction from
  `ProcessTelegramUpdate` (pipeline behaviour unchanged; MSG-003 handler remains the public entry).

### Added

- **Laravel 13 / illuminate 13 support** — all `illuminate/*` requirements allow `^11.0|^12.0|^13.0`.
- Sibling conversation package for the capabilities bus (Telegram-first): webhooks, identity links,
  threads, and chat-side approval notification — implements **core contracts only** (D-007).
- No second mutation path: domain `run()` stays behind the core registry / agent tools.

### Notes

- **Not published on Packagist.** Depends on `rawphp/laravel-capabilities` from path or package VCS.
- Messaging surfaces default **off** in core until this package is installed and configured.
- This package tree is mirrored from the monorepo to `github.com/rawphp/laravel-capabilities-messaging` on push.

## [0.x] — pre-stable

Pre-1.0 development line. APIs may change without a major version bump while on 0.x.
This banner is **not** a substitute for a concrete dated `## [0.x.y]` section at first tag.
Tags without their own section recorded no entries for this package.

[Unreleased]: https://github.com/rawphp/laravel-capabilities-messaging
[0.x]: https://github.com/rawphp/laravel-capabilities-messaging
