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
