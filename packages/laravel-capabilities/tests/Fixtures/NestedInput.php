<?php

declare(strict_types=1);

namespace Rawphp\Capabilities\Tests\Fixtures;

use Rawphp\Capabilities\Attributes\Field;
use Rawphp\Capabilities\Support\CapabilityData;

final class NestedInput extends CapabilityData
{
    public function __construct(
        public LineItemDto $item,
        #[Field(items: LineItemDto::class)]
        public array $items = [],
    ) {}
}
