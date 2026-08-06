<?php

declare(strict_types=1);

it('route table file defines POST messages', function () {
    $path = dirname(__DIR__, 3).'/routes/capabilities-ai.php';
    expect(is_file($path))->toBeTrue();
    $src = file_get_contents($path) ?: '';
    expect($src)->toContain("Route::post('messages'")
        ->and($src)->toContain('storeMessage')
        ->and($src)->not->toContain('acceptProposal')
        ->and($src)->not->toContain('rejectProposal');
});

it('proposal routes live in dedicated file gated by proposals.enabled', function () {
    $path = dirname(__DIR__, 3).'/routes/capabilities-ai-proposals.php';
    expect(is_file($path))->toBeTrue();
    $src = file_get_contents($path) ?: '';
    expect($src)->toContain('acceptProposal')
        ->and($src)->toContain('rejectProposal');
});

it('provider only loads routes when enabled', function () {
    $path = dirname(__DIR__, 3).'/src/CapabilitiesAiServiceProvider.php';
    $src = file_get_contents($path) ?: '';
    expect($src)->toContain('bootRoutes')
        ->and($src)->toContain("routes['enabled']")
        ->and($src)->toContain('capabilities-ai/chat')
        ->and($src)->toContain('capabilities-ai-proposals.php');
});

it('controllers delegate to domain services (no domain writes in controller)', function () {
    $path = dirname(__DIR__, 3).'/src/Http/ChatController.php';
    $src = file_get_contents($path) ?: '';
    expect($src)->toContain('ConversationService')
        ->and($src)->toContain('ProposalService')
        ->and($src)->toContain('TurnService')
        ->and($src)->not->toContain('::query()->create')
        ->and($src)->not->toContain('::query()->update')
        ->and($src)->not->toContain('::query()->where');
});
