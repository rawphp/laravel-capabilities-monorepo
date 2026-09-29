<?php

declare(strict_types=1);

/**
 * Guard the user-guide MCP-only worked example + D-023 principal table.
 * String presence only — behaviour lives in AuthProfileCapabilityMatrixTest.
 */
function mcpPrincipalGuide(): string
{
    $path = dirname(__DIR__, 3).'/docs/user-guide.md';

    expect(is_file($path))->toBeTrue("user guide missing at {$path}");

    $contents = file_get_contents($path);
    expect($contents)->toBeString()->not->toBeEmpty();

    return $contents;
}

it('happy: user guide has an MCP-only worked example [D-023]', function () {
    $guide = mcpPrincipalGuide();

    expect($guide)->toContain('### Worked example: MCP-only capability and principals')
        ->and($guide)->toContain("->surfaces(['mcp'])")
        ->and($guide)->toContain("->allowSystemCallers(['billing-bot'])")
        ->and($guide)->toContain("Capability::mcpTools(profile: 'billing')");
});

it('happy: user guide tables all three D-023 principal profiles [D-023]', function () {
    $guide = mcpPrincipalGuide();

    expect($guide)->toMatch('/\|\s*`user_pat`\s*\|/')
        ->and($guide)->toMatch('/\|\s*`integration`\s*\|/')
        ->and($guide)->toMatch('/\|\s*`user_delegated`\s*\|/')
        ->and($guide)->toContain('allow_integration_credentials')
        ->and($guide)->toContain('integration_actors');
});

it('edge: user guide warns that integration principals have no user() [D-023]', function () {
    $guide = mcpPrincipalGuide();

    expect($guide)->toContain('`$ctx->user()` is `null`')
        ->and($guide)->toContain('AuthProfileCapabilityMatrixTest');
});
