# Security Policy

## Supported Versions

`rawphp/laravel-capabilities` is pre-stable (**0.x**). Security fixes are applied on a best-effort basis to the latest `main` / newest published 0.x only. There is no long-term support promise for older 0.x tags until a stable 1.x release ships with an explicit support window.

| Version | Supported          |
| ------- | ------------------ |
| 0.x     | :white_check_mark: (latest main / newest published 0.x only) |
| < 0.x   | :x:                |

## Reporting a Vulnerability

**Do not open a public GitHub issue for security vulnerabilities.**

Please report privately using one of:

1. **GitHub Security Advisories** on [rawphp/laravel-capabilities](https://github.com/rawphp/laravel-capabilities) (preferred when available): use **Report a vulnerability** on the repository Security tab.
2. **Private contact:** open a draft security advisory against this repository under the [rawphp](https://github.com/rawphp) organization.

Include:

- Package name and version (or commit SHA)
- Description of the issue and impact
- Steps to reproduce or a minimal proof of concept
- Any known mitigations

### What to expect

- Acknowledgement when maintainers see the report (best-effort on 0.x)
- Assessment of severity and whether a fix will ship in 0.x
- Coordinated disclosure preference: please allow time for a fix before public discussion

### Scope notes

This package is the capability bus (authorization, approval, audit, HTTP/API surfaces, tokens). Reports involving authz bypass, privilege escalation, token leakage, or injection into the capability pipeline are especially welcome.

Thank you for helping keep consumers safe.
