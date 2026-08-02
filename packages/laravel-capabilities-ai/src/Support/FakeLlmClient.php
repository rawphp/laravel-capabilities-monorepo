<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Support;

use Rawphp\CapabilitiesAi\Contracts\LlmClient;

/**
 * Deterministic LLM for tests — records complete() call count.
 */
final class FakeLlmClient implements LlmClient
{
    public int $callCount = 0;

    /** @var list<array{content?: string, tool_calls?: list<array<string, mixed>>}> */
    private array $responses;

    /**
     * @param  list<array{content?: string, tool_calls?: list<array<string, mixed>>}>  $responses
     */
    public function __construct(array $responses = [])
    {
        $this->responses = $responses !== [] ? $responses : [['content' => 'ok']];
    }

    public function complete(array $messages, array $tools = []): array
    {
        $this->callCount++;
        $index = min($this->callCount - 1, count($this->responses) - 1);

        return $this->responses[$index];
    }
}
