<?php

namespace Rawphp\Capabilities\Support;

use InvalidArgumentException;

/**
 * Explicit principal for jobs / integrations — never implicit null (D-002).
 */
final class SystemActor
{
    public function __construct(
        public readonly string $name,
    ) {
        if (trim($name) === '') {
            throw new InvalidArgumentException('SystemActor name must not be empty.');
        }
    }

    public static function named(string $name): self
    {
        return new self($name);
    }

    /**
     * Name equality for allowSystemCallers checks (D-002).
     */
    public function equals(self $other): bool
    {
        return $this->name === $other->name;
    }
}
