<?php

declare(strict_types=1);

/**
 * Worktree-safe bootstrap: prefer this package's src over shared vendor classmaps.
 *
 * Parallel worktrees share monorepo vendor; classmap entries may point at sibling
 * worktrees. Register a prepended autoloader *after* Composer so local files win.
 */

$packageRoot = dirname(__DIR__);
$packageSrc = $packageRoot.'/src';
$packageTests = $packageRoot.'/tests';
$monorepoRoot = dirname($packageRoot, 2);

require $monorepoRoot.'/vendor/autoload.php';

spl_autoload_register(static function (string $class) use ($packageSrc, $packageTests): void {
    $map = [
        'Rawphp\\Capabilities\\Tests\\' => $packageTests.'/',
        'Rawphp\\Capabilities\\' => $packageSrc.'/',
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
