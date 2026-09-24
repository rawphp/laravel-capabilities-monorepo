<?php

declare(strict_types=1);

/**
 * Guard the README quickstart: the package landing page shows the same
 * fluent define snippet as the user guide, near the top.
 * String presence only — unit-only.
 */
function readmeQuickstartFluentBlock(string $markdown): ?string
{
    $start = strpos($markdown, "```php\nuse Rawphp\\Capabilities\\Capability;");
    if ($start === false) {
        return null;
    }

    $end = strpos($markdown, "\n```", $start + 6);

    return $end === false ? null : substr($markdown, $start, $end - $start);
}

it('happy: README shows the fluent Capability::define quickstart before Scope', function () {
    $readme = file_get_contents(dirname(__DIR__, 3).'/README.md');
    $block = readmeQuickstartFluentBlock($readme);

    expect($block)->not->toBeNull()
        ->and($block)->toContain("Capability::define('create-invoice')")
        ->and($block)->toContain("->surfaces(['agent', 'mcp', 'http', 'cli', 'job'])")
        ->and(strpos($readme, $block))->toBeLessThan(strpos($readme, '## Scope (this package)'));
});

it('edge: README quickstart matches the user guide fluent snippet (no drift)', function () {
    $root = dirname(__DIR__, 3);
    $readme = readmeQuickstartFluentBlock(file_get_contents($root.'/README.md'));
    $guide = readmeQuickstartFluentBlock(file_get_contents($root.'/docs/user-guide.md'));

    expect($guide)->not->toBeNull()
        ->and($readme)->toBe($guide);
});
