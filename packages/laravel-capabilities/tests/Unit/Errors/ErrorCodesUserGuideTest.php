<?php

declare(strict_types=1);

use Rawphp\Capabilities\Adapters\StructuredToolResponse;
use Rawphp\Capabilities\Support\ErrorCodeMap;

/**
 * Guard user-guide "Error codes" table against ErrorCodeMap drift (D-018).
 * Reads the in-package doc only — survives the package split.
 */
function errorCodesUserGuideSection(): string
{
    $path = dirname(__DIR__, 3).'/docs/user-guide.md';

    expect(is_file($path))->toBeTrue("user guide missing at {$path}");

    $contents = (string) file_get_contents($path);
    $start = strpos($contents, "\n## Error codes\n");
    expect($start)->not->toBeFalse('user guide has no "## Error codes" section');

    $rest = substr($contents, $start + 1);
    $end = strpos($rest, "\n## ", 1);

    return $end === false ? $rest : substr($rest, 0, $end);
}

/**
 * @return array<string, array{http: int, cli_exit: int, retryable: bool, tool_code: string}>
 */
function errorCodesUserGuideRows(): array
{
    preg_match_all(
        '/^\| `([a-z_]+)` \| (\d{3}) \| (\d+) \| (yes|no) \| `([a-z_]+)` \|/m',
        errorCodesUserGuideSection(),
        $matches,
        PREG_SET_ORDER,
    );

    $rows = [];
    foreach ($matches as [, $code, $http, $exit, $retryable, $toolCode]) {
        $rows[$code] = [
            'http' => (int) $http,
            'cli_exit' => (int) $exit,
            'retryable' => $retryable === 'yes',
            'tool_code' => $toolCode,
        ];
    }

    return $rows;
}

it('happy: user guide lists exactly the ErrorCodeMap codes [D-018]', function () {
    $documented = array_keys(errorCodesUserGuideRows());
    $known = ErrorCodeMap::codes();

    sort($documented);
    sort($known);

    expect($documented)->toBe($known);
});

it('happy: user guide HTTP status, CLI exit and retryable match ErrorCodeMap [D-018]', function () {
    foreach (errorCodesUserGuideRows() as $code => $row) {
        expect($row['http'])->toBe(ErrorCodeMap::httpStatus($code), "http for {$code}")
            ->and($row['cli_exit'])->toBe(ErrorCodeMap::cliExit($code), "cli_exit for {$code}")
            ->and($row['retryable'])->toBe(ErrorCodeMap::retryableDefault($code), "retryable for {$code}");
    }
});

it('happy: user guide agent/MCP code matches structured tool normalisation [AI-001][MCP-001]', function () {
    foreach (errorCodesUserGuideRows() as $code => $row) {
        expect($row['tool_code'])->toBe(
            StructuredToolResponse::normalizeFailureCode($code),
            "agent/MCP code for {$code}",
        );
    }
});

it('edge: user guide error section links only in-package or absolute URLs [split]', function () {
    preg_match_all('/\]\(([^)]+)\)/', errorCodesUserGuideSection(), $links);

    foreach ($links[1] as $href) {
        expect($href)->not->toStartWith('../../');
    }
});
