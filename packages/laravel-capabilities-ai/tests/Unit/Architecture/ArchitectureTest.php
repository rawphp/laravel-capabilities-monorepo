<?php

declare(strict_types=1);

/**
 * Architecture guards for rawphp/laravel-capabilities-ai.
 *
 * Package stays host-agnostic: no App\, Gerber, PrimaryAim in src.
 * Core must not reverse-depend on the AI package.
 */
function aiPackageSrc(): string
{
    return dirname(__DIR__, 3).'/src';
}

function scanAiSrc(string $pattern): array
{
    $hits = [];
    $src = aiPackageSrc();
    if (! is_dir($src)) {
        return $hits;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src));
    foreach ($it as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $contents = file_get_contents($file->getPathname()) ?: '';
        if (preg_match($pattern, $contents)) {
            $hits[] = $file->getPathname();
        }
    }

    return $hits;
}

it('config defaults include table_prefix capabilities_ai_', function () {
    $config = require dirname(__DIR__, 3).'/config/capabilities-ai.php';
    expect($config)->toHaveKey('table_prefix')
        ->and($config['table_prefix'])->toBe('capabilities_ai_')
        ->and($config)->toHaveKey('routes')
        ->and($config)->toHaveKey('progress')
        ->and($config)->toHaveKey('llm')
        ->and($config)->toHaveKey('claim_ttl')
        ->and($config)->toHaveKey('max_tool_rounds');
});

it('ServiceProvider registers mergeConfig and publish tags', function () {
    $path = dirname(__DIR__, 3).'/src/CapabilitiesAiServiceProvider.php';
    expect(is_file($path))->toBeTrue();
    $src = file_get_contents($path) ?: '';
    expect($src)->toContain('mergeConfigFrom')
        ->and($src)->toContain('capabilities-ai-config')
        ->and($src)->toContain('capabilities-ai-migrations')
        ->and($src)->toContain('config/capabilities-ai.php');
});

it('package composer requires rawphp/laravel-capabilities core', function () {
    $composer = json_decode(
        file_get_contents(dirname(__DIR__, 3).'/composer.json') ?: '{}',
        true,
        512,
        JSON_THROW_ON_ERROR
    );
    expect($composer['name'] ?? null)->toBe('rawphp/laravel-capabilities-ai');
    expect($composer['require'] ?? [])->toHaveKey('rawphp/laravel-capabilities');
});

it('core composer does not require laravel-capabilities-ai', function () {
    $coreComposer = dirname(__DIR__, 4).'/laravel-capabilities/composer.json';
    expect(is_file($coreComposer))->toBeTrue();
    $composer = json_decode(file_get_contents($coreComposer) ?: '{}', true, 512, JSON_THROW_ON_ERROR);
    $require = $composer['require'] ?? [];
    $requireDev = $composer['require-dev'] ?? [];
    expect($require)->not->toHaveKey('rawphp/laravel-capabilities-ai');
    expect($requireDev)->not->toHaveKey('rawphp/laravel-capabilities-ai');
    $blob = json_encode($composer) ?: '';
    expect($blob)->not->toContain('laravel-capabilities-ai');
});

it('src has no App\\ host namespaces', function () {
    expect(scanAiSrc('/namespace\s+App\\\\/'))->toBeEmpty();
    expect(scanAiSrc('/use\s+App\\\\/'))->toBeEmpty();
});

it('src has no Gerber product references', function () {
    expect(scanAiSrc('/\bGerber\b/'))->toBeEmpty();
});

it('src has no PrimaryAim product references', function () {
    expect(scanAiSrc('/\bPrimaryAim\b/'))->toBeEmpty();
});

/**
 * @return list<string> "FQCN @ relativePath"
 */
function aiCoreUseImports(): array
{
    $hits = [];
    $src = aiPackageSrc();
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src));
    foreach ($it as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $contents = (string) file_get_contents($file->getPathname());
        if (preg_match_all('/^use\s+(Rawphp\\\\Capabilities\\\\[^;]+);/m', $contents, $m)) {
            foreach ($m[1] as $fqcn) {
                $hits[] = $fqcn.' @ '.str_replace($src.'/', '', $file->getPathname());
            }
        }
    }

    return $hits;
}

function aiCoreImportAllowed(string $fqcn): bool
{
    if (str_starts_with($fqcn, 'Rawphp\\Capabilities\\Contracts\\')) {
        return true;
    }

    return in_array($fqcn, [
        'Rawphp\\Capabilities\\Support\\CapabilityResult',
        'Rawphp\\Capabilities\\Support\\CapabilityContext',
        'Rawphp\\Capabilities\\Support\\CapabilityData',
    ], true);
}

it('happy: AI src core imports are only Contracts and public DTOs [D-007]', function () {
    $imports = aiCoreUseImports();
    expect($imports)->not->toBeEmpty();

    $denied = [];
    foreach ($imports as $entry) {
        [$fqcn] = explode(' @ ', $entry, 2);
        if (! aiCoreImportAllowed($fqcn)) {
            $denied[] = $entry;
        }
    }

    expect($denied)->toBeEmpty(
        'Forbidden core imports in AI src: '.implode('; ', $denied)
    );
});

it('fail: AI src must not import concrete Approval Pipeline Registry Persistence [D-007]', function () {
    $imports = aiCoreUseImports();
    foreach ($imports as $entry) {
        $fqcn = explode(' @ ', $entry, 2)[0];
        expect($fqcn)->not->toStartWith('Rawphp\\Capabilities\\Approval\\')
            ->and($fqcn)->not->toStartWith('Rawphp\\Capabilities\\Pipeline\\')
            ->and($fqcn)->not->toStartWith('Rawphp\\Capabilities\\Registry\\')
            ->and($fqcn)->not->toStartWith('Rawphp\\Capabilities\\Persistence\\')
            ->and($fqcn)->not->toStartWith('Rawphp\\Capabilities\\Http\\')
            ->and($fqcn)->not->toStartWith('Rawphp\\Capabilities\\Adapters\\');
    }
});
