# rawphp/laravel-capabilities-messaging

Optional sibling package for conversation surfaces (Telegram first).

Implements core `ConversationIngress` / `ApprovalNotifier` contracts. **Never** embeds domain `run()` — chat feeds the agent; tools are the capability registry (D-007).

See monorepo [docs/spec.md](../../docs/spec.md).
