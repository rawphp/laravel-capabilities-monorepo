<?php

/*
|--------------------------------------------------------------------------
| Pest — rawphp/laravel-capabilities-ai
|--------------------------------------------------------------------------
|
| Package-local unit tests only (tests/Unit). See monorepo AGENTS.md.
|
*/

declare(strict_types=1);

$packageSrc = dirname(__DIR__).'/src';
$packageTests = __DIR__;

$loader = require dirname(__DIR__, 3).'/vendor/autoload.php';
if ($loader instanceof Composer\Autoload\ClassLoader) {
    $loader->setPsr4('Rawphp\\CapabilitiesAi\\', [$packageSrc]);
    $loader->setPsr4('Rawphp\\CapabilitiesAi\\Tests\\', [$packageTests]);
}
