<?php

namespace Rawphp\Capabilities\Registry;

/**
 * Fluent alternate discovery path (D-017) — same {@see CapabilityDefinition} shape as attributes.
 */
final class CapabilityDefinitionBuilder
{
    private string $name;

    private string $description = '';

    /** @var list<string> */
    private array $surfaces = ['agent', 'mcp', 'http', 'cli', 'job', 'artisan'];

    private ?string $input = null;

    private ?string $output = null;

    /** @var list<string> */
    private array $aliases = [];

    private bool $deprecated = false;

    private ?string $deprecated_at = null;

    private ?string $successor = null;

    private ?string $sunset_at = null;

    private mixed $canDiscover = null;

    /** @var list<string> */
    private array $groups = [];

    /** @var list<string> */
    private array $tags = [];

    private bool $readOnly = false;

    /** @var bool|list<string> */
    private bool|array $allowSystemCallers = false;

    private bool $globalSystem = false;

    private ?string $approvalPolicy = null;

    private ?int $approvalTtlHours = null;

    /** @var array<string, mixed>|null */
    private ?array $rateLimit = null;

    private bool|string|null $idempotent = null;

    /** @var array<string, mixed>|bool|null */
    private array|bool|null $audit = null;

    private mixed $authorize = null;

    private mixed $run = null;

    private string $schemaVersion = '1';

    private function __construct(string $name)
    {
        $this->name = $name;
    }

    public static function make(string $name): self
    {
        return new self($name);
    }

    public function description(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @param  list<string>  $surfaces
     */
    public function surfaces(array $surfaces): self
    {
        $this->surfaces = array_values($surfaces);

        return $this;
    }

    /**
     * @param  class-string  $input
     */
    public function input(string $input): self
    {
        $this->input = $input;

        return $this;
    }

    /**
     * @param  class-string  $output
     */
    public function output(string $output): self
    {
        $this->output = $output;

        return $this;
    }

    /**
     * @param  list<string>  $aliases
     */
    public function aliases(array $aliases): self
    {
        $this->aliases = array_values($aliases);

        return $this;
    }

    public function deprecated(bool $deprecated = true): self
    {
        $this->deprecated = $deprecated;

        return $this;
    }

    public function deprecatedAt(?string $deprecatedAt): self
    {
        $this->deprecated_at = $deprecatedAt;

        return $this;
    }

    public function successor(?string $successor): self
    {
        $this->successor = $successor;

        return $this;
    }

    public function sunsetAt(?string $sunsetAt): self
    {
        $this->sunset_at = $sunsetAt;

        return $this;
    }

    /**
     * @param  bool|callable|null  $canDiscover
     */
    public function canDiscover(bool|callable|null $canDiscover): self
    {
        $this->canDiscover = $canDiscover;

        return $this;
    }

    /**
     * @param  list<string>  $groups
     */
    public function groups(array $groups): self
    {
        $this->groups = array_values($groups);

        return $this;
    }

    /**
     * @param  list<string>  $tags
     */
    public function tags(array $tags): self
    {
        $this->tags = array_values($tags);

        return $this;
    }

    public function readOnly(bool $readOnly = true): self
    {
        $this->readOnly = $readOnly;

        return $this;
    }

    /**
     * @param  bool|list<string>  $allow
     */
    public function allowSystemCallers(bool|array $allow): self
    {
        $this->allowSystemCallers = $allow;

        return $this;
    }

    public function globalSystem(bool $globalSystem = true): self
    {
        $this->globalSystem = $globalSystem;

        return $this;
    }

    public function approvalPolicy(?string $policy): self
    {
        $this->approvalPolicy = $policy;

        return $this;
    }

    public function approvalTtlHours(?int $hours): self
    {
        $this->approvalTtlHours = $hours;

        return $this;
    }

    /**
     * @param  array<string, mixed>|null  $rateLimit
     */
    public function rateLimit(?array $rateLimit): self
    {
        $this->rateLimit = $rateLimit;

        return $this;
    }

    public function idempotent(bool|string|null $idempotent): self
    {
        $this->idempotent = $idempotent;

        return $this;
    }

    /**
     * @param  array<string, mixed>|bool|null  $audit
     */
    public function audit(array|bool|null $audit): self
    {
        $this->audit = $audit;

        return $this;
    }

    public function authorize(callable $authorize): self
    {
        $this->authorize = $authorize;

        return $this;
    }

    public function run(callable $run): self
    {
        $this->run = $run;

        return $this;
    }

    public function schemaVersion(string $version): self
    {
        $this->schemaVersion = $version;

        return $this;
    }

    public function toDefinition(): CapabilityDefinition
    {
        return new CapabilityDefinition(
            name: $this->name,
            description: $this->description,
            surfaces: $this->surfaces,
            input: $this->input,
            output: $this->output,
            aliases: $this->aliases,
            deprecated: $this->deprecated,
            deprecated_at: $this->deprecated_at,
            successor: $this->successor,
            sunset_at: $this->sunset_at,
            groups: $this->groups,
            tags: $this->tags,
            readOnly: $this->readOnly,
            allowSystemCallers: $this->allowSystemCallers,
            globalSystem: $this->globalSystem,
            approvalPolicy: $this->approvalPolicy,
            approvalTtlHours: $this->approvalTtlHours,
            rateLimit: $this->rateLimit,
            idempotent: CapabilityDefinition::normalizeIdempotent($this->idempotent),
            audit: $this->audit,
            handlerClass: null,
            authorize: $this->authorize,
            run: $this->run,
            schemaVersion: $this->schemaVersion,
            source: 'fluent',
            canDiscover: $this->canDiscover,
        );
    }

    public function register(CapabilityRegistry $registry): CapabilityDefinition
    {
        $definition = $this->toDefinition();
        $registry->register($definition);

        return $definition;
    }
}
