<?php

declare(strict_types=1);

namespace Rawphp\Capabilities\Tests\Fixtures\MultiClassCapabilities;

use Rawphp\Capabilities\Attributes\Capability;
use Rawphp\Capabilities\Contracts\DefinesCapability;

/**
 * This class exists to trip regex discovery: the word class appears here first,
 * an abstract base class is declared next, and Foo::class is referenced below.
 */
abstract class DocblockCapabilityBase implements DefinesCapability
{
    protected function marker(): string
    {
        return self::class;
    }
}

#[Capability(name: 'docblock-and-base', description: 'Declared after an abstract base.', readOnly: true)]
final class DocblockAndBaseCapability extends DocblockCapabilityBase
{
    public function run(): array
    {
        $anonymous = new class {};

        return ['ok' => $anonymous::class !== ''];
    }
}
