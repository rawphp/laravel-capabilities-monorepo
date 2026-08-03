<?php

declare(strict_types=1);

/**
 * Worktree-safe bootstrap: prefer monorepo package src over shared vendor classmaps.
 *
 * Parallel worktrees share monorepo vendor; path package symlinks may point at sibling
 * worktrees. Prefer local monorepo packages/* so core APIs (e.g. CapabilityResult) match
 * this tip’s sources when AI tests run.
 */
$packageRoot = dirname(__DIR__);
$packageSrc = $packageRoot.'/src';
$packageTests = $packageRoot.'/tests';
$monorepoRoot = dirname($packageRoot, 2);
$coreSrc = $monorepoRoot.'/packages/laravel-capabilities/src';

require $monorepoRoot.'/vendor/autoload.php';

spl_autoload_register(static function (string $class) use ($packageSrc, $packageTests, $coreSrc): void {
    $map = [
        'Rawphp\\CapabilitiesAi\\Tests\\' => $packageTests.'/',
        'Rawphp\\CapabilitiesAi\\' => $packageSrc.'/',
        'Rawphp\\Capabilities\\' => $coreSrc.'/',
    ];

    foreach ($map as $prefix => $base) {
        if (! str_starts_with($class, $prefix)) {
            continue;
        }
        $relative = str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
        $file = $base.$relative;
        if (is_file($file)) {
            require $file;

            return;
        }
    }
}, true, true);
