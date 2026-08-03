<?php

/*
|--------------------------------------------------------------------------
| Pest — rawphp/laravel-capabilities
|--------------------------------------------------------------------------
|
| Package-local unit tests only (tests/Unit). No feature suite. No database.
| See monorepo AGENTS.md.
|
| Worktree note: monorepo vendor may be shared/symlinked. Prepend this package's
| src so in-progress worktree code wins over stale classmaps from sibling trees.
|
*/

declare(strict_types=1);
use Composer\Autoload\ClassLoader;

$packageSrc = dirname(__DIR__).'/src';
$packageTests = __DIR__;

$loader = require dirname(__DIR__, 3).'/vendor/autoload.php';
if ($loader instanceof ClassLoader) {
    $loader->setPsr4('Rawphp\\Capabilities\\', [$packageSrc]);
    $loader->setPsr4('Rawphp\\Capabilities\\Tests\\', [$packageTests]);
}
