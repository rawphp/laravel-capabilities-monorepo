<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Support;

use Rawphp\CapabilitiesAi\Contracts\LlmClient;

/**
 * Deterministic LLM for tests — records complete() call count.
 * Supports multi-round tools (accepts role=tool messages in the transcript).
 *
 * Each tool_calls[] entry is normalized to include a non-empty `id` (fixture
 * value preserved when present; otherwise a stable generated id) so TurnRunner
 * can attach matching tool_call_id on role=tool results.
 *
 * A scripted `raw_content` (provider blocks, e.g. an Anthropic tool search round) is
 * returned unchanged, so hosts can test that TurnRunner carries it into the next round.
 */
final class FakeLlmClient implements LlmClient
{
    public int $callCount = 0;

    private int $toolCallSeq = 0;

    /** @var list<array{content?: string, tool_calls?: list<array<string, mixed>>, raw_content?: list<mixed>}> */
    private array $responses;

    /**
     * @param  list<array{content?: string, tool_calls?: list<array<string, mixed>>, raw_content?: list<mixed>}>  $responses
     */
    public function __construct(array $responses = [])
    {
        $this->responses = $responses !== [] ? $responses : [['content' => 'ok']];
    }

    public function supportsToolRounds(): bool
    {
        return true;
    }

    public function complete(array $messages, array $tools = []): array
    {
        $this->callCount++;
        $index = min($this->callCount - 1, count($this->responses) - 1);
        $response = $this->responses[$index];

        if (isset($response['tool_calls']) && is_array($response['tool_calls'])) {
            $normalized = [];
            foreach ($response['tool_calls'] as $call) {
                if (! is_array($call)) {
                    continue;
                }
                $id = trim((string) ($call['id'] ?? ''));
                if ($id === '') {
                    $this->toolCallSeq++;
                    $id = 'fake_tool_'.$this->callCount.'_'.$this->toolCallSeq;
                }
                $call['id'] = $id;
                $normalized[] = $call;
            }
            $response['tool_calls'] = $normalized;
        }

        return $response;
    }
}
