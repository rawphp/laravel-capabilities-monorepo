<?php

namespace Rawphp\Capabilities\RateLimiting;

/**
 * AI / messaging agent-turn tool-call budget (D-013).
 *
 * Stops the tool loop when max_tool_calls is exceeded and returns a structured
 * message for the model (not a free-form string).
 */
final class AgentTurnBudget
{
    public const DEFAULT_MAX = 16;

    public function __construct(
        private readonly int $maxToolCalls = self::DEFAULT_MAX,
    ) {
        if ($this->maxToolCalls < 0) {
            throw new \InvalidArgumentException('max_tool_calls must be >= 0.');
        }
    }

    public function max(): int
    {
        return $this->maxToolCalls;
    }

    /**
     * Whether another tool call is allowed given calls already made this turn.
     */
    public function allows(int $callsSoFar): bool
    {
        return $callsSoFar <= $this->maxToolCalls;
    }

    /**
     * Whether the loop must stop (calls exceed budget).
     */
    public function exhausted(int $callsSoFar): bool
    {
        return $callsSoFar > $this->maxToolCalls;
    }

    /**
     * Structured stop payload for the model / agent loop.
     *
     * @return array{code: string, message: string, max_tool_calls: int, calls: int, retryable: bool}
     */
    public function stopMessage(int $callsSoFar): array
    {
        return [
            'code' => 'rate_limited',
            'message' => sprintf(
                'Agent turn budget exceeded: %d tool calls (max %d).',
                $callsSoFar,
                $this->maxToolCalls,
            ),
            'max_tool_calls' => $this->maxToolCalls,
            'calls' => $callsSoFar,
            'retryable' => false,
        ];
    }

    public static function fromConfig(array $agentTurn): self
    {
        $max = (int) ($agentTurn['max_tool_calls'] ?? self::DEFAULT_MAX);

        return new self($max);
    }
}
