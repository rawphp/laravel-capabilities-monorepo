<?php

// C2: messaging production src may only import allowlisted core surface.

declare(strict_types=1);

use Rawphp\CapabilitiesMessaging\Tests\Fixtures\MessagingHelpers as H;

/**
 * @return list<string> fully-qualified class names from use Rawphp\Capabilities\...
 */
function messagingCoreUseImports(): array
{
    $hits = [];
    $src = H::MSG_SRC;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src));
    foreach ($it as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $contents = (string) file_get_contents($file->getPathname());
        if (preg_match_all('/^use\s+(Rawphp\\\\Capabilities\\\\[^;]+);/m', $contents, $m)) {
            foreach ($m[1] as $fqcn) {
                $hits[] = $fqcn.' @ '.str_replace(H::MSG_SRC.'/', '', $file->getPathname());
            }
        }
    }

    return $hits;
}

function messagingCoreImportAllowed(string $fqcn): bool
{
    if (str_starts_with($fqcn, 'Rawphp\\Capabilities\\Contracts\\')) {
        return true;
    }

    $publicDtos = [
        'Rawphp\\Capabilities\\Support\\CapabilityResult',
        'Rawphp\\Capabilities\\Support\\CapabilityContext',
        'Rawphp\\Capabilities\\Support\\CapabilityData',
    ];

    return in_array($fqcn, $publicDtos, true);
}

it('happy: messaging src core imports are only Contracts and public DTOs [D-007]', function () {
    $imports = messagingCoreUseImports();
    expect($imports)->not->toBeEmpty();

    $denied = [];
    foreach ($imports as $entry) {
        [$fqcn] = explode(' @ ', $entry, 2);
        if (! messagingCoreImportAllowed($fqcn)) {
            $denied[] = $entry;
        }
    }

    expect($denied)->toBeEmpty(
        'Forbidden core imports in messaging src (use Contracts/* or public DTOs only): '.implode('; ', $denied)
    );
});

it('fail: messaging src must not import concrete Approval or Pipeline types [D-007]', function () {
    $imports = messagingCoreUseImports();
    $fqcnList = array_map(fn (string $e) => explode(' @ ', $e, 2)[0], $imports);

    foreach ($fqcnList as $fqcn) {
        expect($fqcn)->not->toStartWith('Rawphp\\Capabilities\\Approval\\')
            ->and($fqcn)->not->toStartWith('Rawphp\\Capabilities\\Pipeline\\')
            ->and($fqcn)->not->toStartWith('Rawphp\\Capabilities\\Registry\\')
            ->and($fqcn)->not->toStartWith('Rawphp\\Capabilities\\Persistence\\')
            ->and($fqcn)->not->toStartWith('Rawphp\\Capabilities\\Http\\')
            ->and($fqcn)->not->toStartWith('Rawphp\\Capabilities\\Adapters\\');
    }
});

it('happy: messaging depends on ApprovalGateway not ApprovalManager in src [D-006]', function () {
    $blob = '';
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(H::MSG_SRC));
    foreach ($it as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $blob .= (string) file_get_contents($file->getPathname());
        }
    }

    expect($blob)->toContain('ApprovalGateway')
        ->and($blob)->not->toContain('use Rawphp\\Capabilities\\Approval\\ApprovalManager');
});
