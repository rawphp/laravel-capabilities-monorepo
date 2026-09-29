<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Rawphp\Capabilities\Observability\InMemoryMetrics;
use Rawphp\Capabilities\Observability\InMemoryTracer;
use Rawphp\CapabilitiesAi\Contracts\LlmClient;
use Rawphp\CapabilitiesAi\Package;
use Rawphp\CapabilitiesAi\Support\AnthropicLlmClient;
use Rawphp\CapabilitiesAi\Support\ContainerBindings;
use Rawphp\CapabilitiesAi\Support\FakeLlmClient;
use Rawphp\CapabilitiesAi\Support\LlmClientDefaults;
use Rawphp\CapabilitiesAi\Support\RetryableLlmException;

function bootAnthropicHttp(): void
{
    $app = new Container;
    Facade::setFacadeApplication($app);
    $app->singleton('http', fn () => new HttpFactory);
    Http::swap(new HttpFactory);
}

afterEach(fn () => Sleep::fake(false));

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

it('reports input/output token usage from the Anthropic response', function () {
    bootAnthropicHttp();

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'hi']],
            'usage' => ['input_tokens' => 42, 'output_tokens' => 9, 'cache_read_input_tokens' => 0],
        ], 200),
    ]);

    $out = (new AnthropicLlmClient('test-key'))->complete([['role' => 'user', 'content' => 'hi']]);

    expect($out['usage'] ?? null)->toBe(['input_tokens' => 42, 'output_tokens' => 9]);
});

it('omits usage when the Anthropic response carries none', function () {
    bootAnthropicHttp();

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'hi']],
        ], 200),
    ]);

    $out = (new AnthropicLlmClient('test-key'))->complete([['role' => 'user', 'content' => 'hi']]);

    expect($out)->not->toHaveKey('usage');
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

it('fails closed on HTTP error once 429 retries are exhausted', function () {
    bootAnthropicHttp();
    Sleep::fake();

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'error' => ['message' => 'rate limited'],
        ], 429),
    ]);

    $client = new AnthropicLlmClient('test-key');
    expect(fn () => $client->complete([
        ['role' => 'user', 'content' => 'hi'],
    ]))->toThrow(RuntimeException::class, 'Anthropic API error: 429 (rate limited)');

    Http::assertSentCount(3);
    Sleep::assertSleptTimes(2);
});

it('retries a 429 after the Retry-After seconds and returns the next success', function () {
    bootAnthropicHttp();
    Sleep::fake();

    Http::fake([
        'api.anthropic.com/*' => Http::sequence()
            ->push(['error' => ['message' => 'rate limited']], 429, ['retry-after' => '3'])
            ->push(['content' => [['type' => 'text', 'text' => 'after wait']]], 200),
    ]);

    $out = (new AnthropicLlmClient('test-key'))->complete([['role' => 'user', 'content' => 'hi']]);

    expect($out['content'])->toBe('after wait');
    Http::assertSentCount(2);
    Sleep::assertSequence([Sleep::for(3)->seconds()]);
});

it('backs off exponentially on 429 without a usable Retry-After', function () {
    bootAnthropicHttp();
    Sleep::fake();

    Http::fake([
        'api.anthropic.com/*' => Http::sequence()
            ->push(['error' => ['message' => 'rate limited']], 429)
            ->push(['error' => ['message' => 'rate limited']], 429, ['retry-after' => 'soon'])
            ->push(['content' => [['type' => 'text', 'text' => 'ok']]], 200),
    ]);

    $out = (new AnthropicLlmClient('test-key'))->complete([['role' => 'user', 'content' => 'hi']]);

    expect($out['content'])->toBe('ok');
    Sleep::assertSequence([
        Sleep::for(1)->seconds(),
        Sleep::for(2)->seconds(),
    ]);
});

it('caps a long Retry-After so one 429 cannot stall the turn', function () {
    bootAnthropicHttp();
    Sleep::fake();

    Http::fake([
        'api.anthropic.com/*' => Http::sequence()
            ->push(['error' => ['message' => 'rate limited']], 429, ['retry-after' => '3600'])
            ->push(['content' => [['type' => 'text', 'text' => 'ok']]], 200),
    ]);

    (new AnthropicLlmClient('test-key', timeoutSeconds: 30))->complete([['role' => 'user', 'content' => 'hi']]);

    Sleep::assertSequence([Sleep::for(60)->seconds()]);
});

it('stops retrying a 429 when the wait plus one more request would outlast the turn job', function () {
    bootAnthropicHttp();
    Sleep::fake();

    Http::fake([
        'api.anthropic.com/*' => Http::sequence()
            ->push(['error' => ['message' => 'rate limited']], 429, ['retry-after' => '30'])
            ->push(['content' => [['type' => 'text', 'text' => 'too late']]], 200),
    ]);

    try {
        (new AnthropicLlmClient('test-key', timeoutSeconds: 110, deadlineSeconds: 120))
            ->complete([['role' => 'user', 'content' => 'hi']]);
        $this->fail('expected RetryableLlmException');
    } catch (RetryableLlmException $e) {
        expect($e->status)->toBe(429)
            ->and($e->retryAfterSeconds)->toBe(30);
    }

    Http::assertSentCount(1);
    Sleep::assertNeverSlept();
});

it('counts the exponential backoff against the turn job deadline too', function () {
    bootAnthropicHttp();
    Sleep::fake();

    Http::fake([
        'api.anthropic.com/*' => Http::sequence()
            ->push(['error' => ['message' => 'rate limited']], 429)
            ->push(['error' => ['message' => 'rate limited']], 429)
            ->push(['content' => [['type' => 'text', 'text' => 'too late']]], 200),
    ]);

    // 1s + 10s fits 12s; the second wait (2s) + 10s does not.
    expect(fn () => (new AnthropicLlmClient('test-key', timeoutSeconds: 10, deadlineSeconds: 12))
        ->complete([['role' => 'user', 'content' => 'hi']]))
        ->toThrow(RetryableLlmException::class, 'Anthropic API error: 429');

    Http::assertSentCount(2);
    Sleep::assertSequence([Sleep::for(1)->seconds()]);
});

it('does not retry non-429 errors', function () {
    bootAnthropicHttp();
    Sleep::fake();

    Http::fake([
        'api.anthropic.com/*' => Http::response(['error' => ['message' => 'bad request']], 400),
    ]);

    expect(fn () => (new AnthropicLlmClient('test-key'))->complete([['role' => 'user', 'content' => 'hi']]))
        ->toThrow(RuntimeException::class, 'Anthropic API error: 400 (bad request)');

    Http::assertSentCount(1);
    Sleep::assertNeverSlept();
});

it('maxRetries 0 turns 429 retry off', function () {
    bootAnthropicHttp();
    Sleep::fake();

    Http::fake([
        'api.anthropic.com/*' => Http::response(['error' => ['message' => 'rate limited']], 429),
    ]);

    expect(fn () => (new AnthropicLlmClient('test-key', maxRetries: 0))->complete([['role' => 'user', 'content' => 'hi']]))
        ->toThrow(RuntimeException::class, 'Anthropic API error: 429');

    Http::assertSentCount(1);
    Sleep::assertNeverSlept();
});

it('throws RetryableLlmException for transient statuses with Retry-After seconds', function (int $status) {
    bootAnthropicHttp();

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'error' => ['message' => 'slow down'],
        ], $status, ['Retry-After' => '17']),
    ]);

    $client = new AnthropicLlmClient('test-key');
    try {
        $client->complete([['role' => 'user', 'content' => 'hi']]);
        $this->fail('expected RetryableLlmException');
    } catch (RetryableLlmException $e) {
        expect($e->getMessage())->toBe("Anthropic API error: {$status} (slow down)")
            ->and($e->status)->toBe($status)
            ->and($e->retryAfterSeconds)->toBe(17);
    }
})->with([408, 409, 429, 500, 503, 529]);

it('leaves retryAfterSeconds null when Retry-After is missing or not seconds', function (array $headers) {
    bootAnthropicHttp();

    Http::fake([
        'api.anthropic.com/*' => Http::response(['error' => ['message' => 'overloaded']], 529, $headers),
    ]);

    $client = new AnthropicLlmClient('test-key');
    try {
        $client->complete([['role' => 'user', 'content' => 'hi']]);
        $this->fail('expected RetryableLlmException');
    } catch (RetryableLlmException $e) {
        expect($e->retryAfterSeconds)->toBeNull();
    }
})->with([
    'missing' => [[]],
    'http-date' => [['Retry-After' => 'Wed, 21 Oct 2026 07:28:00 GMT']],
]);

it('keeps permanent HTTP errors as plain RuntimeException (not retryable)', function (int $status) {
    bootAnthropicHttp();

    Http::fake([
        'api.anthropic.com/*' => Http::response(['error' => ['message' => 'nope']], $status),
    ]);

    $client = new AnthropicLlmClient('test-key');
    try {
        $client->complete([['role' => 'user', 'content' => 'hi']]);
        $this->fail('expected RuntimeException');
    } catch (RuntimeException $e) {
        expect($e)->not->toBeInstanceOf(RetryableLlmException::class)
            ->and($e->getMessage())->toBe("Anthropic API error: {$status} (nope)");
    }
})->with([400, 401, 403, 404, 413]);

it('wraps connection failures as RetryableLlmException without a status', function () {
    bootAnthropicHttp();

    Http::fake(function (): never {
        throw new ConnectionException('cURL error 28: timed out');
    });

    $client = new AnthropicLlmClient('test-key');
    try {
        $client->complete([['role' => 'user', 'content' => 'hi']]);
        $this->fail('expected RetryableLlmException');
    } catch (RetryableLlmException $e) {
        expect($e->getMessage())->toBe('Anthropic API connection error: cURL error 28: timed out')
            ->and($e->status)->toBeNull()
            ->and($e->retryAfterSeconds)->toBeNull()
            ->and($e->getPrevious())->toBeInstanceOf(ConnectionException::class);
    }
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

it('encodes dotted capability names for Anthropic wire and decodes tool_use back', function () {
    bootAnthropicHttp();

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [
                [
                    'type' => 'tool_use',
                    'id' => 'toolu_pane',
                    // Wire name Anthropic accepts (no dots).
                    'name' => 'pane__list',
                    'input' => ['workspace_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV'],
                ],
            ],
        ], 200),
    ]);

    $client = new AnthropicLlmClient('test-key');
    $out = $client->complete(
        [['role' => 'user', 'content' => 'list panes']],
        [[
            'name' => 'pane.list',
            'description' => 'List panes',
            'parameters' => ['type' => 'object', 'properties' => []],
        ]],
    );

    // Package layer still sees capability name (bus invoke).
    expect($out['tool_calls'][0]['name'] ?? null)->toBe('pane.list')
        ->and(AnthropicLlmClient::encodeToolName('pane.list'))->toBe('pane__list')
        ->and(AnthropicLlmClient::decodeToolName('pane__list'))->toBe('pane.list')
        ->and(AnthropicLlmClient::encodeToolName('pane.list'))->toMatch('/^[a-zA-Z0-9_-]{1,128}$/');

    Http::assertSent(function ($request) {
        $tools = $request->data()['tools'] ?? [];
        $name = $tools[0]['name'] ?? null;

        // Must match Anthropic pattern (no dots).
        return $name === 'pane__list'
            && is_string($name)
            && (bool) preg_match('/^[a-zA-Z0-9_-]{1,128}$/', $name);
    });
});

it('round-trips capability names that contain single underscores', function (string $name) {
    $wire = AnthropicLlmClient::encodeToolName($name);

    expect($wire)->toMatch('/^[a-zA-Z0-9_-]{1,128}$/')
        ->and(AnthropicLlmClient::decodeToolName($wire))->toBe($name);
})->with([
    'invoice.void_all',
    'billing_admin.refund',
    'a.b_c.d_e',
    'snake_case_only',
    'kebab-case.void_all',
    // Shares wire name a___b with rejected a_.b; decode resolves to this one only.
    'a._b',
]);

it('rejects capability names whose wire encoding would decode to a different capability', function (string $name) {
    expect(fn () => AnthropicLlmClient::encodeToolName($name))
        ->toThrow(InvalidArgumentException::class, $name);
})->with([
    'double underscore decodes to dot' => 'pane__list',
    'trailing underscore before dot' => 'a_.b',
]);

it('fails closed before any request when an advertised tool name cannot round-trip', function () {
    bootAnthropicHttp();
    Http::fake();

    $client = new AnthropicLlmClient('test-key');

    expect(fn () => $client->complete(
        [['role' => 'user', 'content' => 'hi']],
        [
            ['name' => 'pane.list'],
            ['name' => 'pane__list'],
        ],
    ))->toThrow(InvalidArgumentException::class, 'pane__list');

    Http::assertNothingSent();
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
                // Dotted package names are encoded for Anthropic (no dots on wire).
                ->and($tools[0]['name'] ?? null)->toBe('demo__tool')
                ->and($tools[0]['input_schema'] ?? null)->not->toBeNull();

            return Http::response([
                'content' => [
                    [
                        'type' => 'tool_use',
                        'id' => 'toolu_round1',
                        'name' => 'demo__tool',
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

it('records latency, token usage and an ok span around a successful call', function () {
    bootAnthropicHttp();

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'ok']],
            'usage' => ['input_tokens' => 120, 'output_tokens' => 45],
        ], 200),
    ]);

    $metrics = new InMemoryMetrics;
    $tracer = new InMemoryTracer;
    $client = new AnthropicLlmClient('test-key', model: 'claude-test', metrics: $metrics, tracer: $tracer);
    $client->complete([['role' => 'user', 'content' => 'hi']]);

    $labels = ['provider' => 'anthropic', 'model' => 'claude-test'];
    $spans = $tracer->spans();

    expect($metrics->histogramSamples(AnthropicLlmClient::METRIC_LATENCY, $labels))->toHaveCount(1)
        ->and($metrics->histogramSamples(AnthropicLlmClient::METRIC_LATENCY, $labels)[0])->toBeGreaterThanOrEqual(0.0)
        ->and($metrics->get(AnthropicLlmClient::METRIC_TOKENS, $labels + ['type' => 'input']))->toBe(120)
        ->and($metrics->get(AnthropicLlmClient::METRIC_TOKENS, $labels + ['type' => 'output']))->toBe(45)
        ->and($metrics->get(AnthropicLlmClient::METRIC_FAILURES, $labels + ['reason' => 'http_429']))->toBe(0)
        ->and($spans)->toHaveCount(1)
        ->and($spans[0]['name'])->toBe(AnthropicLlmClient::SPAN_COMPLETE)
        ->and($spans[0]['status'])->toBe('ok')
        ->and($spans[0]['ended'])->toBeTrue()
        ->and($spans[0]['attributes'])->toMatchArray([
            'provider' => 'anthropic',
            'model' => 'claude-test',
            'input_tokens' => 120,
            'output_tokens' => 45,
        ]);
});

it('skips token counters when the response carries no usage block', function () {
    bootAnthropicHttp();

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'ok']],
        ], 200),
    ]);

    $metrics = new InMemoryMetrics;
    $client = new AnthropicLlmClient('test-key', model: 'claude-test', metrics: $metrics);
    $client->complete([['role' => 'user', 'content' => 'hi']]);

    $names = array_column($metrics->emissions(), 'name');

    expect($names)->not->toContain(AnthropicLlmClient::METRIC_TOKENS)
        ->and($metrics->histogramSamples(AnthropicLlmClient::METRIC_LATENCY, [
            'provider' => 'anthropic',
            'model' => 'claude-test',
        ]))->toHaveCount(1);
});

it('counts an HTTP error as a failure and ends the span with error before throwing', function () {
    bootAnthropicHttp();

    Http::fake([
        'api.anthropic.com/*' => Http::response(['error' => ['message' => 'rate limited']], 429),
    ]);

    $metrics = new InMemoryMetrics;
    $tracer = new InMemoryTracer;
    $client = new AnthropicLlmClient('test-key', model: 'claude-test', metrics: $metrics, tracer: $tracer);

    expect(fn () => $client->complete([['role' => 'user', 'content' => 'hi']]))
        ->toThrow(RuntimeException::class, 'Anthropic API error: 429');

    $labels = ['provider' => 'anthropic', 'model' => 'claude-test'];
    $spans = $tracer->spans();

    expect($metrics->get(AnthropicLlmClient::METRIC_FAILURES, $labels + ['reason' => 'http_429']))->toBe(1)
        ->and($metrics->histogramSamples(AnthropicLlmClient::METRIC_LATENCY, $labels))->toHaveCount(1)
        ->and($spans[0]['status'])->toBe('error')
        ->and($spans[0]['attributes'])->toMatchArray(['http_status' => 429]);
});

it('counts a transport failure and rethrows it as retryable', function () {
    bootAnthropicHttp();

    Http::fake(function () {
        throw new ConnectionException('connection refused');
    });

    $metrics = new InMemoryMetrics;
    $tracer = new InMemoryTracer;
    $client = new AnthropicLlmClient('test-key', model: 'claude-test', metrics: $metrics, tracer: $tracer);

    try {
        $client->complete([['role' => 'user', 'content' => 'hi']]);
        $this->fail('expected RetryableLlmException');
    } catch (RetryableLlmException $e) {
        expect($e->getPrevious())->toBeInstanceOf(ConnectionException::class)
            ->and($e->getPrevious()->getMessage())->toBe('connection refused');
    }

    expect($metrics->get(AnthropicLlmClient::METRIC_FAILURES, [
        'provider' => 'anthropic',
        'model' => 'claude-test',
        'reason' => 'transport',
    ]))->toBe(1)
        ->and($tracer->spans()[0]['status'])->toBe('error')
        ->and($tracer->spans()[0]['ended'])->toBeTrue();
});

it('does not record a failure for the empty API key guard (no outbound call)', function () {
    $metrics = new InMemoryMetrics;
    $tracer = new InMemoryTracer;
    $client = new AnthropicLlmClient(apiKey: '', metrics: $metrics, tracer: $tracer);

    expect(fn () => $client->complete([['role' => 'user', 'content' => 'hi']]))
        ->toThrow(RuntimeException::class, 'ANTHROPIC_API_KEY is empty');

    expect($metrics->emissions())->toBe([])
        ->and($tracer->spans())->toBe([]);
});

it('sends each request with the configured timeout instead of the 30s HTTP client default', function () {
    bootAnthropicHttp();
    $seen = [];
    Http::fake(function ($request, array $options) use (&$seen) {
        $seen[] = $options['timeout'] ?? null;

        return Http::response(['content' => [['type' => 'text', 'text' => 'ok']]], 200);
    });

    (new AnthropicLlmClient('test-key', timeoutSeconds: 95))->complete([['role' => 'user', 'content' => 'hi']]);
    (new AnthropicLlmClient('test-key'))->complete([['role' => 'user', 'content' => 'hi']]);

    expect($seen)->toBe([95, AnthropicLlmClient::DEFAULT_TIMEOUT_SECONDS])
        ->and(AnthropicLlmClient::DEFAULT_TIMEOUT_SECONDS)->toBeLessThan(Package::DEFAULT_CLAIM_TTL);
});

it('makeLlmClient passes llm.anthropic.timeout through to the request', function () {
    bootAnthropicHttp();
    $seen = [];
    Http::fake(function ($request, array $options) use (&$seen) {
        $seen[] = $options['timeout'] ?? null;

        return Http::response(['content' => [['type' => 'text', 'text' => 'ok']]], 200);
    });

    $client = ContainerBindings::makeLlmClient([
        'llm' => ['driver' => 'anthropic', 'anthropic' => ['api_key' => 'k', 'timeout' => 42]],
    ]);
    $client->complete([['role' => 'user', 'content' => 'hi']]);

    expect($seen)->toBe([42]);
});

it('makeLlmClient gives the client claim_ttl as its retry deadline', function () {
    bootAnthropicHttp();
    Sleep::fake();
    Http::fake([
        'api.anthropic.com/*' => Http::sequence()
            ->push(['error' => ['message' => 'rate limited']], 429, ['retry-after' => '30'])
            ->push(['content' => [['type' => 'text', 'text' => 'ok']]], 200),
    ]);

    // 30s wait + 110s timeout exceeds the 120s default but fits claim_ttl=300.
    $client = ContainerBindings::makeLlmClient([
        'claim_ttl' => 300,
        'llm' => ['driver' => 'anthropic', 'anthropic' => ['api_key' => 'k', 'timeout' => 110]],
    ]);

    expect($client->complete([['role' => 'user', 'content' => 'hi']])['content'])->toBe('ok');
    Sleep::assertSequence([Sleep::for(30)->seconds()]);
});

it('rejects a non-positive timeout', function () {
    expect(fn () => new AnthropicLlmClient('test-key', timeoutSeconds: 0))
        ->toThrow(InvalidArgumentException::class, 'timeout');
});

/**
 * Send one complete() through a faked Anthropic endpoint and return the outbound JSON body.
 *
 * @param  list<mixed>  $messages
 * @param  list<mixed>  $tools
 * @return array{0: array<string, mixed>, 1: string} decoded body + raw JSON (for {} vs [] checks)
 */
function anthropicOutbound(array $messages, array $tools = []): array
{
    bootAnthropicHttp();
    Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => 'ok']]], 200)]);

    (new AnthropicLlmClient('test-key'))->complete($messages, $tools);

    $raw = Http::recorded()[0][0]->body();

    return [json_decode($raw, true, 512, JSON_THROW_ON_ERROR), $raw];
}

it('lifts system messages into the top-level system prompt and skips empty and non-array rows', function () {
    [$body] = anthropicOutbound([
        ['role' => 'system', 'content' => 'You are helpful.'],
        'not-a-message',
        ['role' => 'system', 'content' => ''],
        ['role' => 'system', 'content' => 'Be brief.'],
        ['role' => 'user', 'content' => 'hi'],
    ]);

    expect($body['system'])->toBe("You are helpful.\n\nBe brief.")
        ->and($body['messages'])->toBe([['role' => 'user', 'content' => 'hi']]);
});

it('omits system and sends a placeholder user turn when only empty rows are given', function () {
    [$body] = anthropicOutbound([['role' => 'system', 'content' => '']]);

    expect($body)->not->toHaveKey('system')
        ->and($body['messages'])->toBe([['role' => 'user', 'content' => '(empty)']]);
});

it('merges consecutive same-role text rows so roles alternate', function () {
    [$body] = anthropicOutbound([
        ['role' => 'user', 'content' => "first  \n"],
        ['role' => 'user', 'content' => 'second'],
    ]);

    expect($body['messages'])->toBe([['role' => 'user', 'content' => "first\n\nsecond"]]);
});

it('merges a text row with a block row as one block list, dropping empty text', function () {
    $image = ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => 'image/png', 'data' => 'AAAA']];

    [$body] = anthropicOutbound([
        ['role' => 'user', 'content' => 'look at this'],
        ['role' => 'user', 'content' => [$image]],
        ['role' => 'user', 'content' => ''],
    ]);

    expect($body['messages'])->toHaveCount(1)
        ->and($body['messages'][0]['content'])->toBe([
            ['type' => 'text', 'text' => 'look at this'],
            $image,
        ]);
});

it('tool results without a correlation id fall back to tool_call_unknown and JSON-encode structured content', function () {
    [$body] = anthropicOutbound([
        ['role' => 'user', 'content' => 'go'],
        ['role' => 'tool', 'content' => ['ok' => true, 'id' => 7]],
    ]);

    expect($body['messages'][0]['content'])->toBe([
        ['type' => 'text', 'text' => 'go'],
        ['type' => 'tool_result', 'tool_use_id' => 'tool_call_unknown', 'content' => '{"ok":true,"id":7}'],
    ]);
});

it('replays assistant tool calls with text, a fallback id and {} input, skipping malformed calls', function () {
    [$body, $raw] = anthropicOutbound([
        ['role' => 'user', 'content' => 'go'],
        [
            'role' => 'assistant',
            'content' => 'calling',
            'tool_calls' => [
                'garbage',
                ['name' => 'invoices.create', 'arguments' => 'not-an-object'],
            ],
        ],
    ]);

    expect($body['messages'][1])->toBe([
        'role' => 'assistant',
        'content' => [
            ['type' => 'text', 'text' => 'calling'],
            ['type' => 'tool_use', 'id' => 'tool_call_unknown', 'name' => AnthropicLlmClient::encodeToolName('invoices.create'), 'input' => []],
        ],
    ])->and($raw)->toContain('"input":{}');
});

it('sends an empty text block for an assistant row with neither text nor tool calls', function () {
    [$body] = anthropicOutbound([
        ['role' => 'user', 'content' => 'go'],
        ['role' => 'assistant', 'content' => ''],
    ]);

    expect($body['messages'][1])->toBe(['role' => 'assistant', 'content' => [['type' => 'text', 'text' => '']]]);
});

it('normalizes tool schemas to Anthropic objects and skips unnamed or malformed tool defs', function () {
    [$body, $raw] = anthropicOutbound(
        [['role' => 'user', 'content' => 'go']],
        [
            'not-a-tool',
            ['description' => 'nameless'],
            ['name' => 'no_schema'],
            ['name' => 'bad_schema', 'parameters' => 'string-schema'],
            ['name' => 'untyped', 'input_schema' => ['properties' => ['a' => ['type' => 'string']]]],
            ['name' => 'empty_props', 'parameters' => ['type' => 'object', 'properties' => []]],
        ],
    );

    $byName = array_column($body['tools'], null, 'name');

    expect(array_keys($byName))->toBe(['no_schema', 'bad_schema', 'untyped', 'empty_props'])
        ->and($byName['no_schema']['input_schema'])->toBe(['type' => 'object', 'properties' => []])
        ->and($byName['no_schema']['description'])->toBe('')
        ->and($byName['bad_schema']['input_schema'])->toBe(['type' => 'object', 'properties' => []])
        ->and($byName['untyped']['input_schema'])->toBe(['properties' => ['a' => ['type' => 'string']], 'type' => 'object'])
        ->and($byName['empty_props']['input_schema'])->toBe(['type' => 'object', 'properties' => []])
        ->and(substr_count($raw, '"properties":{}'))->toBe(3);
});

it('ignores non-array content blocks in the Anthropic response', function () {
    bootAnthropicHttp();
    Http::fake(['api.anthropic.com/*' => Http::response([
        'content' => ['stray', ['type' => 'text', 'text' => 'kept']],
    ], 200)]);

    $out = (new AnthropicLlmClient('test-key'))->complete([['role' => 'user', 'content' => 'hi']]);

    expect($out)->toBe(['content' => 'kept']);
});

it('LlmClientDefaults keeps host clients off multi-round tools unless they opt in', function () {
    $host = new class implements LlmClient
    {
        use LlmClientDefaults;

        public function complete(array $messages, array $tools = []): array
        {
            return ['content' => ''];
        }
    };

    expect($host->supportsToolRounds())->toBeFalse();
});

it('makeLlmClient refuses an anthropic timeout that does not fit inside claim_ttl', function (int $timeout, int $claimTtl) {
    expect(fn () => ContainerBindings::makeLlmClient([
        'claim_ttl' => $claimTtl,
        'llm' => ['driver' => 'anthropic', 'anthropic' => ['api_key' => 'k', 'timeout' => $timeout]],
    ]))->toThrow(InvalidArgumentException::class, "llm.anthropic.timeout ({$timeout}s) must be below claim_ttl ({$claimTtl}s)");
})->with([
    'equal' => [120, 120],
    'above' => [300, 120],
    'above a raised ttl' => [601, 600],
]);

it('makeLlmClient checks the timeout against the package default claim_ttl when unset', function () {
    expect(fn () => ContainerBindings::makeLlmClient([
        'llm' => ['driver' => 'anthropic', 'anthropic' => ['api_key' => 'k', 'timeout' => Package::DEFAULT_CLAIM_TTL]],
    ]))->toThrow(InvalidArgumentException::class, 'must be below claim_ttl');
});
