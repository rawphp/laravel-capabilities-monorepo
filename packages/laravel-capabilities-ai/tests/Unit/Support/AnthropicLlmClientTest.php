<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Http;
use Rawphp\CapabilitiesAi\Contracts\LlmClient;
use Rawphp\CapabilitiesAi\Support\AnthropicLlmClient;
use Rawphp\CapabilitiesAi\Support\FakeLlmClient;

it('AnthropicLlmClient implements LlmClient', function () {
    expect(new AnthropicLlmClient('test-key'))->toBeInstanceOf(LlmClient::class);
});

it('complete success path with Http::fake without network', function () {
    $app = new Container;
    Facade::setFacadeApplication($app);
    $app->singleton('http', fn () => new HttpFactory);
    Http::swap(new HttpFactory);

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [
                ['type' => 'text', 'text' => 'hello from fake'],
            ],
        ], 200),
    ]);

    $client = new AnthropicLlmClient('test-key');
    $out = $client->complete([
        ['role' => 'user', 'content' => 'hi'],
    ]);

    expect($out['content'])->toBe('hello from fake');
    Http::assertSentCount(1);
});

it('testing default FakeLlmClient does not hit network', function () {
    $fake = new FakeLlmClient([['content' => 'local']]);
    expect($fake->complete([['role' => 'user', 'content' => 'x']]))->toBe(['content' => 'local'])
        ->and($fake->callCount)->toBe(1);
});

it('fails closed when messages include role=tool', function () {
    $client = new AnthropicLlmClient(apiKey: 'test-key');
    expect(fn () => $client->complete([
        ['role' => 'user', 'content' => 'hi'],
        ['role' => 'tool', 'content' => 'result'],
    ]))->toThrow(RuntimeException::class, 'role=tool');
});

it('does not advertise multi-round tool support', function () {
    expect((new AnthropicLlmClient('k'))->supportsToolRounds())->toBeFalse();
});

it('FakeLlmClient advertises multi-round tool support', function () {
    expect((new FakeLlmClient)->supportsToolRounds())->toBeTrue();
});
