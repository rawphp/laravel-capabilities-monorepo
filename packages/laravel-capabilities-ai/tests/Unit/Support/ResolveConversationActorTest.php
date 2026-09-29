<?php

declare(strict_types=1);

use Rawphp\CapabilitiesAi\Support\ResolveConversationActor;

it('builds bus invoke options with the server-chosen caller and the actor', function () {
    $actor = new stdClass;
    $actors = new ResolveConversationActor;

    expect($actors->invokeOptions($actor, ResolveConversationActor::CALLER_AGENT))
        ->toBe(['caller' => 'agent', 'actor' => $actor])
        ->and($actors->invokeOptions($actor, ResolveConversationActor::CALLER_JOB, ['idempotency_key' => 'k']))
        ->toBe(['caller' => 'job', 'actor' => $actor, 'idempotency_key' => 'k']);
});

it('never lets extra options override the caller or actor', function () {
    $actor = new stdClass;

    $options = (new ResolveConversationActor)->invokeOptions($actor, ResolveConversationActor::CALLER_AGENT, [
        'caller' => 'http',
        'actor' => new stdClass,
    ]);

    expect($options['caller'])->toBe('agent')
        ->and($options['actor'])->toBe($actor);
});
