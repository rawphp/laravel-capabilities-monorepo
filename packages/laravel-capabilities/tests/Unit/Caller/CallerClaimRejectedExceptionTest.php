<?php

declare(strict_types=1);

// CallerClaimRejectedException factory.

use Rawphp\Capabilities\Support\CallerClaimRejectedException;

it('CallerClaimRejectedException::upgrade names the derived and claimed callers', function () {
    $e = CallerClaimRejectedException::upgrade('http', 'cli');
    expect($e)->toBeInstanceOf(CallerClaimRejectedException::class)
        ->and($e->getMessage())->toContain('derived=http')
        ->and($e->getMessage())->toContain('claimed=cli');
});
