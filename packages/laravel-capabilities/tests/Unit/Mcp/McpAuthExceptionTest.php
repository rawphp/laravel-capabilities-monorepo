<?php

declare(strict_types=1);

// McpAuthException factories.

use Rawphp\Capabilities\Adapters\Mcp\McpAuthException;

it('McpAuthException factories each return an McpAuthException with a message', function () {
    foreach ([
        McpAuthException::vagueTokenUser(),
        McpAuthException::integrationDisabled(),
        McpAuthException::missingUser(),
        McpAuthException::missingClientId('user_pat'),
        McpAuthException::unknownIntegrationClient('c1'),
        McpAuthException::unknownProfile('nope'),
    ] as $ex) {
        expect($ex)->toBeInstanceOf(McpAuthException::class)
            ->and($ex->getMessage())->not->toBe('');
    }
});
