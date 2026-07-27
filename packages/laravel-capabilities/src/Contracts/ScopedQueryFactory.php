<?php

namespace Rawphp\Capabilities\Contracts;

use Rawphp\Capabilities\Support\CapabilityScope;

/**
 * Builds tenant-scoped queries for resource re-resolution (D-003).
 *
 * Production typically returns Eloquent Builders; unit tests inject fakes.
 */
interface ScopedQueryFactory
{
    /**
     * @param  class-string  $model
     * @return mixed  Eloquent Builder or test double
     */
    public function for(CapabilityScope $scope, string $model): mixed;
}
