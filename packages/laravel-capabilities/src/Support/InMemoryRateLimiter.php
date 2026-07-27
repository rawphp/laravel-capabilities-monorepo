<?php

namespace Rawphp\Capabilities\Support;

use Rawphp\Capabilities\Contracts\RateLimiter;

/**
 * Pure in-memory rate limiter for unit tests (no Redis / Laravel facade).
 *
 * Decay is approximate (hit timestamps kept in process memory). Suitable for
 * asserting tooManyAttempts / remaining behaviour without external services.
 */
final class InMemoryRateLimiter implements RateLimiter
{
    /**
     * @var array<string, list<float>>
     */
    private array $hits = [];

    private ?string $lastKey = null;

    public function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        return $this->attemptCount($key) >= $maxAttempts;
    }

    public function hit(string $key, int $decaySeconds = 60): int
    {
        $now = microtime(true);
        $this->prune($key, $now, $decaySeconds);
        $this->hits[$key][] = $now;
        $this->lastKey = $key;

        return count($this->hits[$key]);
    }

    public function remaining(string $key, int $maxAttempts): int
    {
        return max(0, $maxAttempts - $this->attemptCount($key));
    }

    public function clear(string $key): void
    {
        unset($this->hits[$key]);
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->hits);
    }

    public function attemptCount(string $key): int
    {
        return count($this->hits[$key] ?? []);
    }

    /**
     * Last key that received a hit (unit tests).
     */
    public function lastKey(): ?string
    {
        return $this->lastKey;
    }

    private function prune(string $key, float $now, int $decaySeconds): void
    {
        if (! isset($this->hits[$key])) {
            $this->hits[$key] = [];

            return;
        }

        $cutoff = $now - $decaySeconds;
        $this->hits[$key] = array_values(array_filter(
            $this->hits[$key],
            static fn (float $ts): bool => $ts >= $cutoff,
        ));
    }
}
