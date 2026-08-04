<?php

declare(strict_types=1);

it('documents LlmClient without Conversation / MVS / FakeLlmClient', function () {
    $readme = file_get_contents(dirname(__DIR__, 3).'/README.md') ?: '';
    $docs = is_file(dirname(__DIR__, 3).'/docs/user-guide.md')
        ? (file_get_contents(dirname(__DIR__, 3).'/docs/user-guide.md') ?: '')
        : '';
    $blob = $readme."\n".$docs;
    expect($blob)->toContain('FakeLlmClient')
        ->and($blob)->toMatch('/MVS|without.*Conversation|without a Conversation/i');
});

it('documents supportsToolRounds host upgrade callouts', function () {
    $readme = file_get_contents(dirname(__DIR__, 3).'/README.md') ?: '';
    $docs = file_get_contents(dirname(__DIR__, 3).'/docs/user-guide.md') ?: '';
    $changelog = file_get_contents(dirname(__DIR__, 3).'/CHANGELOG.md') ?: '';

    expect($docs)->toContain('Upgrade for hosts (LlmClient / tool rounds)')
        ->and($docs)->toContain('supportsToolRounds')
        ->and($docs)->toContain('LlmClientDefaults')
        ->and($changelog)->toContain('supportsToolRounds()')
        ->and($changelog)->toMatch('/Breaking \(upgrade for hosts\)/i')
        ->and($readme)->toContain('upgrade-for-hosts-llmclient-tool-rounds');
});
