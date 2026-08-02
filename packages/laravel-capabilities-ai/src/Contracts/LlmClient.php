<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Contracts;

/**
 * Pluggable LLM client. Hosts may resolve without a Conversation (MVS jobs).
 */
interface LlmClient
{
    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @param  list<array<string, mixed>>  $tools
     * @return array{content?: string, tool_calls?: list<array<string, mixed>>}
     */
    public function complete(array $messages, array $tools = []): array;
}
