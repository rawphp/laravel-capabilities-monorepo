<?php

namespace Rawphp\Capabilities\Http;

use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Support\ErrorCodeMap;

/**
 * CLI --json prints the same envelope as HTTP (D-018).
 */
final class CliJsonEnvelope
{
    /**
     * @return array{ok: bool, data?: mixed, error?: array<string, mixed>, meta: array<string, mixed>}
     */
    public static function fromResult(CapabilityResult $result): array
    {
        return $result->toArray();
    }

    public static function exitCode(CapabilityResult $result): int
    {
        if ($result->isOk()) {
            return 0;
        }

        $code = $result->errorCode() ?? 'internal';

        return ErrorCodeMap::cliExit($code);
    }
}
