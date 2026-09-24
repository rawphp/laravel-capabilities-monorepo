<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Support;

/**
 * Outcome of scanning assistant content for a ```proposal``` fence.
 *
 * Absent (no fence), invalid (fence present but not a decodable JSON object), or valid (`data` set).
 */
final class ProposalFence
{
    /**
     * @param  array<string, mixed>|null  $data
     */
    private function __construct(
        public readonly bool $present,
        public readonly ?array $data,
    ) {}

    public static function absent(): self
    {
        return new self(false, null);
    }

    public static function invalid(): self
    {
        return new self(true, null);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function valid(array $data): self
    {
        return new self(true, $data);
    }

    public function isInvalid(): bool
    {
        return $this->present && $this->data === null;
    }
}
