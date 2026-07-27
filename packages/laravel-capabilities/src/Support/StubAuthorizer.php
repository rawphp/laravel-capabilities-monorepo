<?php

namespace Rawphp\Capabilities\Support;

use Rawphp\Capabilities\Contracts\Authorizer;

/**
 * Fixed allow/deny authorizer for unit tests (no policies, no DB).
 */
final class StubAuthorizer implements Authorizer
{
    public function __construct(
        private readonly bool $allowed,
    ) {}

    public static function allow(): self
    {
        return new self(true);
    }

    public static function deny(): self
    {
        return new self(false);
    }

    public function authorize(string $capability, mixed $input, mixed $context): bool
    {
        return $this->allowed;
    }
}
