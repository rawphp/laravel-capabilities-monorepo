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
