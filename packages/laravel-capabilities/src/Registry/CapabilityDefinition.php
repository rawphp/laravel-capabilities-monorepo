<?php

namespace Rawphp\Capabilities\Registry;

use InvalidArgumentException;
use Rawphp\Capabilities\Contracts\SchemaProvider;
use Rawphp\Capabilities\Support\SystemActor;

/**
 * Immutable capability metadata shared by attribute and fluent discovery (D-017).
 */
final class CapabilityDefinition
{
    public const IDEMPOTENT_NONE = 'none';

    public const IDEMPOTENT_OPTIONAL = 'optional';

    public const IDEMPOTENT_REQUIRED = 'required';

    /**
     * @param  list<string>  $surfaces
     * @param  list<string>  $aliases
     * @param  list<string>  $groups
     * @param  list<string>  $tags
     * @param  bool|list<string>  $allowSystemCallers
     * @param  class-string|null  $input
     * @param  class-string|null  $output
     * @param  class-string|null  $handlerClass  Attributed class implementing DefinesCapability
     * @param  array<string, mixed>|null  $rateLimit
     * @param  array<string, mixed>|bool|null  $audit
     * @param  callable|null  $authorize
     * @param  callable|null  $run
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description = '',
        public readonly array $surfaces = ['agent', 'mcp', 'http', 'cli'],
        public readonly ?string $input = null,
        public readonly ?string $output = null,
        public readonly array $aliases = [],
        public readonly bool $deprecated = false,
        public readonly ?string $deprecated_at = null,
        public readonly ?string $successor = null,
        public readonly ?string $sunset_at = null,
        public readonly array $groups = [],
        public readonly array $tags = [],
        public readonly bool $readOnly = false,
        public readonly bool|array $allowSystemCallers = false,
        public readonly bool $globalSystem = false,
        public readonly ?string $approvalPolicy = null,
        public readonly ?int $approvalTtlHours = null,
        public readonly ?array $rateLimit = null,
        public readonly string $idempotent = self::IDEMPOTENT_OPTIONAL,
        public readonly array|bool|null $audit = null,
        public readonly ?string $handlerClass = null,
        public readonly mixed $authorize = null,
        public readonly mixed $run = null,
        public readonly string $schemaVersion = '1',
        public readonly string $source = 'attribute',
        public readonly mixed $canDiscover = null,
    ) {
        if (trim($name) === '') {
            throw new InvalidArgumentException('Capability definition name must not be empty.');
        }

        if (! $this->readOnly && ($this->input === null || $this->input === '')) {
            throw new InvalidArgumentException(sprintf(
                'Mutating capability "%s" requires an input type.',
                $name,
            ));
        }
    }

    public function isMutating(): bool
    {
        return ! $this->readOnly;
    }

    /**
     * @param  array<string, bool>  $globallyEnabled  surface => enabled
     * @return list<string>
     */
    public function effectiveSurfaces(array $globallyEnabled): array
    {
        $effective = [];
        foreach ($this->surfaces as $surface) {
            if (($globallyEnabled[$surface] ?? false) === true) {
                $effective[] = $surface;
            }
        }

        return $effective;
    }

    public function hasEffectiveExposure(array $globallyEnabled): bool
    {
        return $this->effectiveSurfaces($globallyEnabled) !== [];
    }

    public function allowsSystemCaller(SystemActor $actor): bool
    {
        if ($this->allowSystemCallers === true) {
            return true;
        }

        if ($this->allowSystemCallers === false || $this->allowSystemCallers === []) {
            return false;
        }

        if (is_array($this->allowSystemCallers)) {
            return in_array($actor->name, $this->allowSystemCallers, true);
        }

        return false;
    }

    /**
     * Read-only skips audit unless forced (D-010). Mutating defaults to audit.
     */
    public function shouldAudit(bool $force = false): bool
    {
        if ($force) {
            return true;
        }

        if (is_array($this->audit) && array_key_exists('force', $this->audit) && $this->audit['force'] === true) {
            return true;
        }

        if ($this->audit === false) {
            return false;
        }

        if ($this->readOnly) {
            return false;
        }

        return true;
    }

    /**
     * Read-only ignores idempotency keys (D-005).
     */
    public function shouldUseIdempotency(): bool
    {
        if ($this->readOnly) {
            return false;
        }

        return $this->idempotent !== self::IDEMPOTENT_NONE;
    }

    /**
     * Whether output validation runs after run() (D-014).
     */
    public function shouldValidateOutput(bool $validateOutputConfig = true): bool
    {
        if (! $validateOutputConfig) {
            return false;
        }

        if ($this->output !== null && $this->output !== '') {
            return true;
        }

        // readOnly without declared output schema may skip
        if ($this->readOnly) {
            return false;
        }

        return false;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function inputSchema(): ?array
    {
        if ($this->input === null || ! is_a($this->input, SchemaProvider::class, true)) {
            return null;
        }

        return $this->input::jsonSchema();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function outputSchema(): ?array
    {
        if ($this->output === null || ! is_a($this->output, SchemaProvider::class, true)) {
            return null;
        }

        return $this->output::jsonSchema();
    }

    /**
     * Whether this capability may appear in tool/catalog listings for the actor (D-008).
     */
    public function isDiscoverable(mixed $actor = null): bool
    {
        if ($this->canDiscover === null) {
            return true;
        }

        if (is_bool($this->canDiscover)) {
            return $this->canDiscover;
        }

        if (is_callable($this->canDiscover)) {
            return (bool) ($this->canDiscover)($actor);
        }

        return true;
    }

    /**
     * True when sunset_at is set and is at or before $now (D-012).
     */
    public function isSunset(?\DateTimeInterface $now = null): bool
    {
        if ($this->sunset_at === null || $this->sunset_at === '') {
            return false;
        }

        try {
            $sunset = new \DateTimeImmutable($this->sunset_at);
        } catch (\Exception) {
            return false;
        }

        $now = $now ?? new \DateTimeImmutable('now');

        return $now >= $sunset;
    }

    /**
     * Normalize idempotent flag from attribute/fluent forms.
     */
    public static function normalizeIdempotent(bool|string|null $value): string
    {
        if ($value === null || $value === true || $value === self::IDEMPOTENT_OPTIONAL) {
            return self::IDEMPOTENT_OPTIONAL;
        }

        if ($value === false || $value === self::IDEMPOTENT_NONE) {
            return self::IDEMPOTENT_NONE;
        }

        if ($value === self::IDEMPOTENT_REQUIRED) {
            return self::IDEMPOTENT_REQUIRED;
        }

        throw new InvalidArgumentException(sprintf('Invalid idempotent flag: %s', var_export($value, true)));
    }
}
