<?php

namespace Rawphp\Capabilities\Pipeline;

use Rawphp\Capabilities\Registry\CapabilityDefinition;
use Rawphp\Capabilities\Support\CapabilityContext;
use Rawphp\Capabilities\Support\CapabilityResult;

/**
 * Mutable state carried through the registry invoke pipeline.
 */
final class InvokeState
{
    /** @var list<string> */
    public array $stages = [];

    public mixed $input = null;

    public mixed $output = null;

    public ?CapabilityResult $result = null;

    public ?CapabilityContext $context = null;

    public ?string $idempotencyKey = null;

    public ?string $requestHash = null;

    public bool $idempotentReplay = false;

    public bool $runCalled = false;

    public int $runCount = 0;

    public bool $domainSideEffect = false;

    public ?string $approvalId = null;

    /**
     * @param  array<string, mixed>  $rawInput
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        public readonly CapabilityDefinition $definition,
        public readonly array $rawInput,
        public readonly string $caller,
        public readonly array $options = [],
        public readonly ?string $requestId = null,
    ) {}

    public function mark(string $stage): void
    {
        $this->stages[] = $stage;
    }

    public function hasStage(string $stage): bool
    {
        return in_array($stage, $this->stages, true);
    }

    public function stageIndex(string $stage): ?int
    {
        $idx = array_search($stage, $this->stages, true);

        return $idx === false ? null : (int) $idx;
    }
}
