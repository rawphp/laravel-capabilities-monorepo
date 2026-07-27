# Changelog

All notable changes to `rawphp/laravel-capabilities-messaging` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html)
with **0.x pre-stable** expectations (see [docs/versioning.md](../../docs/versioning.md)).

## [Unreleased]

### Added

- Sibling conversation package for the capabilities bus (Telegram-first): webhooks, identity links,
  threads, and chat-side approval notification — implements **core contracts only** (D-007).
- No second mutation path: domain `run()` stays behind the core registry / agent tools.

### Notes

- **Not published on Packagist.** Depends on `rawphp/laravel-capabilities` from path or VCS.
- Messaging surfaces default **off** in core until this package is installed and configured.
- Thin surface relative to core; entries stay high-level until a tagged release exists.

<!--
  First tagged 0.x.y scaffold (Keep a Changelog):
  When cutting monorepo git tag v0.1.0, promote Unreleased bullets into:

  ## [0.1.0] - YYYY-MM-DD

  Then leave [Unreleased] empty for the next cycle. Section title has no leading "v";
  git tag keeps the "v" prefix. See docs/versioning.md → Git tag naming.
-->

## [0.x] — pre-stable monorepo

Pre-1.0 development line. APIs may change without a major version bump while on 0.x.
This banner is **not** a substitute for a concrete dated `## [0.x.y]` section at first tag.

[Unreleased]: https://github.com/rawphp/laravel-capabilities
[0.x]: https://github.com/rawphp/laravel-capabilities
