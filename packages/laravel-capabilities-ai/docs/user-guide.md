# laravel-capabilities-ai user guide

## What it is

Package runtime for chat turns on top of the core capability bus:

1. Cheap create message → queued turn (no LLM)
2. Claim + TurnRunner (LLM + tools via bus only)
3. Proposal accept/reject via bus

## Install

Path package in monorepo. Host: require `rawphp/laravel-capabilities-ai`, publish config + migrations.

## Host bindings

- `ConversationContextProvider`
- `ToolCatalog`
- `LlmClient` (Fake in tests, Anthropic in prod)

## Progress

`ProgressStore` array or Redis — never product MySQL.

## Optional routes

Set `CAPABILITIES_AI_ROUTES_ENABLED=true`. Prefix default `capabilities-ai/chat`.
