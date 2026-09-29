<?php

declare(strict_types=1);

// UnresolvedScopeException factories.

use Rawphp\Capabilities\Support\UnresolvedScopeException;

it('UnresolvedScopeException factories explain the missing tenant id and the unusable scope', function () {
    expect(UnresolvedScopeException::systemWithoutTenant()->getMessage())->toContain('tenantId')
        ->and(UnresolvedScopeException::unusable()->getMessage())->toContain('CapabilityScope');
});
