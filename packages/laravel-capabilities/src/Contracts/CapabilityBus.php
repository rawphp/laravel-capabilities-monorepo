<?php

namespace Rawphp\Capabilities\Contracts;

use Rawphp\Capabilities\Schema\CatalogPresenter;
use Rawphp\Capabilities\Support\CapabilityResult;

/**
 * Registry surface used by HTTP adapters — enables unit mocks without final-class issues.
 */
interface CapabilityBus
{
    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $options
     */
    public function invoke(string $nameOrAlias, array $input = [], array $options = []): CapabilityResult;

    public function catalog(): CatalogPresenter;
}
