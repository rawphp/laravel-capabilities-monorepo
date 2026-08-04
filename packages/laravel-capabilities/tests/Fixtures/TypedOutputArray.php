<?php

declare(strict_types=1);

namespace Rawphp\Capabilities\Tests\Fixtures;

use Rawphp\Capabilities\Support\CapabilityData;

final class TypedOutputArray extends CapabilityData
{
    public function __construct(public array $value) {}
}
