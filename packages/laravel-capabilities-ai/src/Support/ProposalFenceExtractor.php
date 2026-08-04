<?php

declare(strict_types=1);

namespace Rawphp\CapabilitiesAi\Support;

/**
 * Extract a single ```proposal …``` fence from assistant markdown.
 * Presentation parsing lives here — not in TurnRunner's claim/LLM/tool loop.
 *
 * Nested JSON objects are supported via brace-balanced scan (not non-greedy regex).
 */
final class ProposalFenceExtractor
{
    /**
     * @return array<string, mixed>|null Decoded JSON object, or null if none/invalid
     */
    public function extract(string $content): ?array
    {
        if (! preg_match('/```proposal\s*(.*?)\s*```/s', $content, $m)) {
            return null;
        }

        $body = trim($m[1]);
        if ($body === '') {
            return null;
        }

        return $this->decodeJsonObject($body);
    }

    /**
     * Brace-balanced scan for the first JSON object in $body (handles nested objects).
     *
     * @return array<string, mixed>|null
     */
    private function decodeJsonObject(string $body): ?array
    {
        $start = strpos($body, '{');
        if ($start === false) {
            return null;
        }

        $depth = 0;
        $inString = false;
        $escape = false;
        $len = strlen($body);

        for ($i = $start; $i < $len; $i++) {
            $c = $body[$i];

            if ($inString) {
                if ($escape) {
                    $escape = false;

                    continue;
                }
                if ($c === '\\') {
                    $escape = true;

                    continue;
                }
                if ($c === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($c === '"') {
                $inString = true;

                continue;
            }

            if ($c === '{') {
                $depth++;

                continue;
            }

            if ($c === '}') {
                $depth--;
                if ($depth === 0) {
                    $slice = substr($body, $start, $i - $start + 1);

                    try {
                        /** @var mixed $data */
                        $data = json_decode($slice, true, 512, JSON_THROW_ON_ERROR);
                    } catch (\JsonException) {
                        return null;
                    }

                    return is_array($data) ? $data : null;
                }
            }
        }

        return null;
    }
}
