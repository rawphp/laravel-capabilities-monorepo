# rawphp/laravel-capabilities

Core product capability bus for Laravel.

Define a capability once (schema, authorization, `run`, approval, audit) and expose it via agent, MCP, HTTP, product CLI, and jobs — same rules, one `run()`.

See monorepo [docs/spec.md](../../docs/spec.md).

## Peer support matrix (D-011)

Supported `laravel/ai` and `laravel/mcp` version constraints live in
[`PeerSupportMatrix`](src/Adapters/PeerSupportMatrix.php) (mirrored under
`config/capabilities.php` → `peers.support`). `PeerVersionProbe` defaults to
that matrix — not open-ended `*`. Incompatible peers fail boot or soft-disable
per surface `on_incompatible` (never half-register tools).
