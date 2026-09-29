<?php

declare(strict_types=1);

use Rawphp\CapabilitiesAi\Support\ProposalFenceExtractor;

it('extracts JSON from proposal fence', function () {
    $content = "Hello\n```proposal\n{\"type\":\"action\",\"target_capability\":\"x.y\",\"payload\":{\"a\":1}}\n```\n";
    $data = (new ProposalFenceExtractor)->extract($content);
    expect($data)->toBeArray()
        ->and($data['target_capability'] ?? null)->toBe('x.y')
        ->and($data['payload']['a'] ?? null)->toBe(1);
});

it('returns null when fence missing or invalid JSON', function () {
    $ex = new ProposalFenceExtractor;
    expect($ex->extract('no fence'))->toBeNull()
        ->and($ex->extract("```proposal\n{not-json}\n```"))->toBeNull();
});

it('extracts nested JSON objects inside proposal fence', function () {
    $content = <<<'MD'
See proposal:
```proposal
{"type":"action","target_capability":"x.y","payload":{"a":1,"nested":{"b":2}}}
```
MD;
    $data = (new ProposalFenceExtractor)->extract($content);
    expect($data)->toBeArray()
        ->and($data['payload']['a'] ?? null)->toBe(1)
        ->and($data['payload']['nested']['b'] ?? null)->toBe(2);
});

it('parse reports absent when no proposal fence is present', function () {
    $fence = (new ProposalFenceExtractor)->parse("plain answer\n```json\n{\"a\":1}\n```");
    expect($fence->present)->toBeFalse()
        ->and($fence->data)->toBeNull()
        ->and($fence->isInvalid())->toBeFalse();
});

it('parse reports invalid when a proposal fence is present but not a decodable JSON object', function (string $content) {
    $fence = (new ProposalFenceExtractor)->parse($content);
    expect($fence->present)->toBeTrue()
        ->and($fence->data)->toBeNull()
        ->and($fence->isInvalid())->toBeTrue();
})->with([
    'broken json' => "```proposal\n{not-json}\n```",
    'empty body' => "```proposal\n\n```",
    'no object' => "```proposal\n[1,2]\n```",
    'unbalanced braces' => "```proposal\n{\"a\":{\"b\":1}\n```",
]);

it('parse reports a valid fence with decoded data', function () {
    $fence = (new ProposalFenceExtractor)->parse("```proposal\n{\"type\":\"action\"}\n```");
    expect($fence->present)->toBeTrue()
        ->and($fence->data)->toBe(['type' => 'action'])
        ->and($fence->isInvalid())->toBeFalse();
});
