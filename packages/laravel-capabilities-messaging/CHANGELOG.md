# Changelog

All notable changes to `rawphp/laravel-capabilities-messaging` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html)
with **0.x pre-stable** expectations (breaking changes allowed without a major bump while major is 0).

Monorepo packaging policy:  
https://github.com/rawphp/laravel-capabilities-monorepo/blob/main/docs/versioning.md

## [Unreleased]

### Added

- Sibling conversation package for the capabilities bus (Telegram-first): webhooks, identity links,
  threads, and chat-side approval notification — implements **core contracts only** (D-007).
- No second mutation path: domain `run()` stays behind the core registry / agent tools.

### Notes

- **Not published on Packagist.** Depends on `rawphp/laravel-capabilities` from path or package VCS.
- Messaging surfaces default **off** in core until this package is installed and configured.
- This package tree is mirrored from the monorepo to `github.com/rawphp/laravel-capabilities-messaging` on push.

<!--
  First tagged 0.x.y scaffold (Keep a Changelog):
  When cutting monorepo git tag v0.1.0 (mirrored to this package remote), promote Unreleased bullets into:

  ## [0.1.0] - YYYY-MM-DD

  Then leave [Unreleased] empty for the next cycle. Section title has no leading "v";
  git tag keeps the "v" prefix.
-->

## [0.x] — pre-stable

Pre-1.0 development line. APIs may change without a major version bump while on 0.x.
This banner is **not** a substitute for a concrete dated `## [0.x.y]` section at first tag.

[Unreleased]: https://github.com/rawphp/laravel-capabilities-messaging
[0.x]: https://github.com/rawphp/laravel-capabilities-messaging
