<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Support;

/**
 * Extract a single ```proposal {…}``` fence from assistant markdown.
 * Presentation parsing lives here — not in TurnRunner's claim/LLM/tool loop.
 */
final class ProposalFenceExtractor
{
    /**
     * @return array<string, mixed>|null Decoded JSON object, or null if none/invalid
     */
    public function extract(string $content): ?array
    {
        if (! preg_match('/```proposal\s*(\{.*?\})\s*```/s', $content, $m)) {
            return null;
        }

        try {
            /** @var mixed $data */
            $data = json_decode($m[1], true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($data) ? $data : null;
    }
}
