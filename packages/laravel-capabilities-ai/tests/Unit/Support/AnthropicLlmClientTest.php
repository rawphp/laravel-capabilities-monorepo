<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Http;
use Rawphp\CapabilitiesAi\Contracts\LlmClient;
use Rawphp\CapabilitiesAi\Support\AnthropicLlmClient;
use Rawphp\CapabilitiesAi\Support\FakeLlmClient;
use Rawphp\CapabilitiesAi\Support\LlmClientDefaults;

function bootAnthropicHttp(): void
{
    $app = new Container;
    Facade::setFacadeApplication($app);
    $app->singleton('http', fn () => new HttpFactory);
    Http::swap(new HttpFactory);
}

it('AnthropicLlmClient implements LlmClient', function () {
    expect(new AnthropicLlmClient('test-key'))->toBeInstanceOf(LlmClient::class);
});

it('complete success path with Http::fake without network', function () {
    bootAnthropicHttp();

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

it('advertises multi-round tool support', function () {
    expect((new AnthropicLlmClient('k'))->supportsToolRounds())->toBeTrue()
        ->and(class_uses_recursive(AnthropicLlmClient::class))->toContain(
            LlmClientDefaults::class
        );
});

it('FakeLlmClient advertises multi-round tool support', function () {
    expect((new FakeLlmClient)->supportsToolRounds())->toBeTrue();
});

it('fails closed on empty API key', function () {
    $client = new AnthropicLlmClient(apiKey: '');
    expect(fn () => $client->complete([
        ['role' => 'user', 'content' => 'hi'],
    ]))->toThrow(RuntimeException::class, 'ANTHROPIC_API_KEY is empty');
});

it('fails closed on HTTP error', function () {
    bootAnthropicHttp();

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'error' => ['message' => 'rate limited'],
        ], 429),
    ]);

    $client = new AnthropicLlmClient('test-key');
    expect(fn () => $client->complete([
        ['role' => 'user', 'content' => 'hi'],
    ]))->toThrow(RuntimeException::class, 'Anthropic API error: 429');
});

it('maps package tool defs to Anthropic tools with input_schema and parses tool_use id', function () {
    bootAnthropicHttp();

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [
                [
                    'type' => 'tool_use',
                    'id' => 'toolu_01abc',
                    'name' => 'get_weather',
                    'input' => ['city' => 'SF'],
                ],
            ],
        ], 200),
    ]);

    $client = new AnthropicLlmClient('test-key');
    $out = $client->complete(
        [['role' => 'user', 'content' => 'weather?']],
        [[
            'name' => 'get_weather',
            'description' => 'Look up weather',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'city' => ['type' => 'string'],
                ],
            ],
        ]],
    );

    expect($out['tool_calls'] ?? null)->toBeArray()
        ->and($out['tool_calls'])->toHaveCount(1)
        ->and($out['tool_calls'][0]['id'] ?? null)->toBe('toolu_01abc')
        ->and($out['tool_calls'][0]['name'] ?? null)->toBe('get_weather')
        ->and($out['tool_calls'][0]['arguments'] ?? null)->toBe(['city' => 'SF']);

    Http::assertSent(function ($request) {
        $body = $request->data();
        $tools = $body['tools'] ?? null;
        if (! is_array($tools) || $tools === []) {
            return false;
        }
        $tool = $tools[0];

        return ($tool['name'] ?? null) === 'get_weather'
            && ($tool['description'] ?? null) === 'Look up weather'
            && isset($tool['input_schema'])
            && is_array($tool['input_schema'])
            && ($tool['input_schema']['type'] ?? null) === 'object'
            && ! array_key_exists('parameters', $tool);
    });
});

it('multi-round: tools advertised then tool_result then final text', function () {
    bootAnthropicHttp();

    $sequence = 0;
    Http::fake(function ($request) use (&$sequence) {
        $sequence++;
        $body = $request->data();

        if ($sequence === 1) {
            $tools = $body['tools'] ?? [];
            expect($tools)->not->toBeEmpty()
                ->and($tools[0]['name'] ?? null)->toBe('demo.tool')
                ->and($tools[0]['input_schema'] ?? null)->not->toBeNull();

            return Http::response([
                'content' => [
                    [
                        'type' => 'tool_use',
                        'id' => 'toolu_round1',
                        'name' => 'demo.tool',
                        'input' => ['x' => 1],
                    ],
                ],
            ], 200);
        }

        // Second request must include tool_result correlated by tool_use_id.
        $messages = $body['messages'] ?? [];
        $encoded = json_encode($messages);
        expect($encoded)->toContain('tool_result')
            ->and($encoded)->toContain('toolu_round1')
            ->and($encoded)->toContain('tool_use');

        $foundToolResult = false;
        foreach ($messages as $msg) {
            $content = $msg['content'] ?? null;
            if (! is_array($content)) {
                continue;
            }
            foreach ($content as $block) {
                if (! is_array($block)) {
                    continue;
                }
                if (($block['type'] ?? '') === 'tool_result'
                    && ($block['tool_use_id'] ?? '') === 'toolu_round1'
                ) {
                    $foundToolResult = true;
                    expect((string) ($block['content'] ?? ''))->toContain('ok');
                }
            }
        }
        expect($foundToolResult)->toBeTrue();

        return Http::response([
            'content' => [
                ['type' => 'text', 'text' => 'final after tool'],
            ],
        ], 200);
    });

    $client = new AnthropicLlmClient('test-key');
    $tools = [[
        'name' => 'demo.tool',
        'description' => 'Demo',
        'parameters' => ['type' => 'object', 'properties' => []],
    ]];

    $first = $client->complete(
        [['role' => 'user', 'content' => 'use the tool']],
        $tools,
    );

    expect($first['tool_calls'][0]['id'] ?? null)->toBe('toolu_round1');

    $second = $client->complete(
        [
            ['role' => 'user', 'content' => 'use the tool'],
            [
                'role' => 'assistant',
                'content' => '',
                'tool_calls' => [
                    [
                        'id' => 'toolu_round1',
                        'name' => 'demo.tool',
                        'arguments' => ['x' => 1],
                    ],
                ],
            ],
            [
                'role' => 'tool',
                'content' => '{"ok":true,"name":"demo.tool"}',
                'tool_call_id' => 'toolu_round1',
                'id' => 'toolu_round1',
            ],
        ],
        $tools,
    );

    expect($second['content'] ?? null)->toBe('final after tool')
        ->and($second)->not->toHaveKey('tool_calls');
    Http::assertSentCount(2);
});

it('role=tool maps to tool_result without throwing', function () {
    bootAnthropicHttp();

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [
                ['type' => 'text', 'text' => 'ok'],
            ],
        ], 200),
    ]);

    $client = new AnthropicLlmClient('test-key');
    $out = $client->complete([
        ['role' => 'user', 'content' => 'hi'],
        [
            'role' => 'assistant',
            'content' => '',
            'tool_calls' => [
                ['id' => 'toolu_x', 'name' => 't', 'arguments' => []],
            ],
        ],
        [
            'role' => 'tool',
            'content' => 'result-body',
            'tool_call_id' => 'toolu_x',
            'id' => 'toolu_x',
        ],
    ]);

    expect($out['content'])->toBe('ok');

    Http::assertSent(function ($request) {
        $body = json_encode($request->data());

        return is_string($body)
            && str_contains($body, 'tool_result')
            && str_contains($body, 'toolu_x')
            && str_contains($body, 'result-body')
            && ! str_contains($body, '"role":"tool"');
    });
});

it('outbound payload uses max_tokens 64000 by default (host parity)', function () {
    bootAnthropicHttp();

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [
                ['type' => 'text', 'text' => 'ok'],
            ],
        ], 200),
    ]);

    $client = new AnthropicLlmClient('test-key');
    $client->complete([
        ['role' => 'user', 'content' => 'hi'],
    ]);

    Http::assertSent(function ($request) {
        $body = $request->data();

        return ($body['max_tokens'] ?? null) === 64000;
    });
});

it('constructor max_tokens override is sent in outbound payload', function () {
    bootAnthropicHttp();

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [
                ['type' => 'text', 'text' => 'ok'],
            ],
        ], 200),
    ]);

    $client = new AnthropicLlmClient(apiKey: 'test-key', maxTokens: 2048);
    $client->complete([
        ['role' => 'user', 'content' => 'hi'],
    ]);

    Http::assertSent(function ($request) {
        $body = $request->data();

        return ($body['max_tokens'] ?? null) === 2048;
    });
});

it('passes multimodal user content blocks through to Messages API (vision)', function () {
    bootAnthropicHttp();

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [
                ['type' => 'text', 'text' => 'I see a meal photo'],
            ],
        ], 200),
    ]);

    $imageBlocks = [
        ['type' => 'text', 'text' => 'What is this meal?'],
        [
            'type' => 'image',
            'source' => [
                'type' => 'base64',
                'media_type' => 'image/jpeg',
                'data' => 'ZmFrZS1pbWFnZS1ieXRlcw==',
            ],
        ],
    ];

    $client = new AnthropicLlmClient('test-key');
    $out = $client->complete([
        ['role' => 'user', 'content' => $imageBlocks],
    ]);

    expect($out['content'])->toBe('I see a meal photo');

    Http::assertSent(function ($request) use ($imageBlocks) {
        $body = $request->data();
        $messages = $body['messages'] ?? null;
        if (! is_array($messages) || $messages === []) {
            return false;
        }
        $content = $messages[0]['content'] ?? null;
        if (! is_array($content)) {
            return false;
        }
        // Must not be cast to a string placeholder.
        if (is_string($content)) {
            return false;
        }
        $hasText = false;
        $hasImage = false;
        foreach ($content as $block) {
            if (! is_array($block)) {
                continue;
            }
            if (($block['type'] ?? '') === 'text' && ($block['text'] ?? '') === 'What is this meal?') {
                $hasText = true;
            }
            if (($block['type'] ?? '') === 'image') {
                $source = $block['source'] ?? null;
                if (is_array($source)
                    && ($source['type'] ?? '') === 'base64'
                    && ($source['media_type'] ?? '') === 'image/jpeg'
                    && ($source['data'] ?? '') === 'ZmFrZS1pbWFnZS1ieXRlcw=='
                ) {
                    $hasImage = true;
                }
            }
        }

        return $hasText && $hasImage && $content === $imageBlocks;
    });
});

it('string-only user content is still sent as a string (no regression)', function () {
    bootAnthropicHttp();

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [
                ['type' => 'text', 'text' => 'ok'],
            ],
        ], 200),
    ]);

    $client = new AnthropicLlmClient('test-key');
    $client->complete([
        ['role' => 'user', 'content' => 'plain text turn'],
    ]);

    Http::assertSent(function ($request) {
        $body = $request->data();
        $content = $body['messages'][0]['content'] ?? null;

        return is_string($content) && $content === 'plain text turn';
    });
});
