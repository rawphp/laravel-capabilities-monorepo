<?php

// ORI-846: integration-health MCP tool count must be read-only (no adapter register).
// Unit-only; fake app + registry — no live laravel/mcp.

declare(strict_types=1);

use Rawphp\Capabilities\Adapters\Artisan\IntegrationHealthCommand;
use Rawphp\Capabilities\Adapters\Mcp\McpCredential;
use Rawphp\Capabilities\Adapters\Mcp\McpToolAdapter;
use Rawphp\Capabilities\Adapters\ToolSelection;
use Rawphp\Capabilities\Contracts\AuditWriter;
use Rawphp\Capabilities\Contracts\Authorizer;
use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Support\IntegrationHealthChecker;
use Rawphp\Capabilities\Tests\Fixtures\AdapterHelpers;
use Rawphp\Capabilities\Tests\Fixtures\ArtisanCommandHarness;

/**
 * Minimal container for IntegrationHealthCommand::mcpToolCountCallback.
 *
 * @param  array<string, mixed>  $bindings
 * @param  array<string, mixed>  $configTree  root config keys (e.g. capabilities)
 */
function ihCommandApp(array $bindings = [], array $configTree = []): object
{
    return new class($bindings, $configTree)
    {
        /**
         * @param  array<string, mixed>  $bindings
         * @param  array<string, mixed>  $configTree
         */
        public function __construct(
            private array $bindings,
            private array $configTree,
        ) {}

        public function bound(string $abstract): bool
        {
            return array_key_exists($abstract, $this->bindings);
        }

        public function make(string $abstract): mixed
        {
            if ($abstract === 'config') {
                $tree = $this->configTree;

                return new class($tree)
                {
                    /** @param  array<string, mixed>  $tree */
                    public function __construct(private array $tree) {}

                    public function get(string $key, mixed $default = null): mixed
                    {
                        $parts = explode('.', $key);
                        $v = $this->tree;
                        foreach ($parts as $part) {
                            if (! is_array($v) || ! array_key_exists($part, $v)) {
                                return $default;
                            }
                            $v = $v[$part];
                        }

                        return $v;
                    }

                    public function has(string $key): bool
                    {
                        return $this->get($key, new stdClass) !== new stdClass
                            || array_key_exists(explode('.', $key)[0], $this->tree);
                    }
                };
            }

            if (! array_key_exists($abstract, $this->bindings)) {
                throw new RuntimeException("Binding not found: {$abstract}");
            }

            return $this->bindings[$abstract];
        }
    };
}

/**
 * Invoke private mcpToolCountCallback against a fake app.
 */
function ihInvokeToolCount(object $app): int
{
    $cmd = new IntegrationHealthCommand;
    $ref = new ReflectionClass($cmd);
    $laravel = $ref->getProperty('laravel');
    $laravel->setValue($cmd, $app);
    $method = $ref->getMethod('mcpToolCountCallback');
    $cb = $method->invoke($cmd);
    expect($cb)->toBeCallable();

    return (int) $cb();
}

/**
 * Spy McpToolAdapter that records register() calls.
 */
function ihSpyMcpAdapter(): object
{
    return new class implements McpToolAdapter
    {
        public int $registerCalls = 0;

        public function supportsInstalledPeer(): bool
        {
            return true;
        }

        public function adapterApiVersion(): int
        {
            return 1;
        }

        public function register(ToolSelection|string|array $selection): array
        {
            $this->registerCalls++;

            return [['name' => 'should-not-appear']];
        }

        public function handle(
            string $name,
            array $input,
            McpCredential $credential,
            array $options = [],
        ): CapabilityResult {
            return CapabilityResult::failure(code: 'unused', message: 'spy');
        }

        public function handleStructured(
            string $name,
            array $input,
            McpCredential $credential,
            array $options = [],
        ): array {
            return [];
        }
    };
}

/**
 * @return array<string, mixed>
 */
function ihMcpSurfacesConfig(array $mcpOverrides = []): array
{
    return [
        'capabilities' => [
            'surfaces' => [
                'mcp' => array_merge([
                    'enabled' => true,
                    'auto_register' => true,
                    'require_profile' => true,
                    'require_package' => true,
                    'on_incompatible' => 'fail',
                    'path_prefix' => '/mcp',
                    'profiles' => [
                        'billing' => ['create-invoice', 'void-invoice', 'list-invoices'],
                    ],
                    'servers' => [],
                ], $mcpOverrides),
            ],
        ],
    ];
}

it('mcp tool count does not call McpToolAdapter::register [ORI-846]', function () {
    $h = AdapterHelpers::harness();
    $spy = ihSpyMcpAdapter();

    $count = ihInvokeToolCount(ihCommandApp(
        [
            CapabilityRegistry::class => $h['registry'],
            McpToolAdapter::class => $spy,
        ],
        ihMcpSurfacesConfig(),
    ));

    expect($spy->registerCalls)->toBe(0)
        ->and($count)->toBeGreaterThan(0);
});

it('mcp tool count reflects planned profile tools via registry [ORI-846]', function () {
    $h = AdapterHelpers::harness();
    $expected = count($h['registry']->mcpTools('billing'));
    expect($expected)->toBeGreaterThan(0);

    $count = ihInvokeToolCount(ihCommandApp(
        [CapabilityRegistry::class => $h['registry']],
        ihMcpSurfacesConfig(),
    ));

    expect($count)->toBe($expected);
});

it('mcp tool count is 0 when CapabilityRegistry is unbound [ORI-846]', function () {
    $spy = ihSpyMcpAdapter();

    $count = ihInvokeToolCount(ihCommandApp(
        [McpToolAdapter::class => $spy],
        ihMcpSurfacesConfig(),
    ));

    expect($count)->toBe(0)
        ->and($spy->registerCalls)->toBe(0);
});

it('mcp tool count is 0 when MCP plan is empty [ORI-846]', function () {
    $h = AdapterHelpers::harness();
    $spy = ihSpyMcpAdapter();

    $count = ihInvokeToolCount(ihCommandApp(
        [
            CapabilityRegistry::class => $h['registry'],
            McpToolAdapter::class => $spy,
        ],
        ihMcpSurfacesConfig([
            'profiles' => [],
            'servers' => [],
        ]),
    ));

    expect($count)->toBe(0)
        ->and($spy->registerCalls)->toBe(0);
});

it('mcp tool count sums tools across multiple planned profiles [ORI-846]', function () {
    $h = AdapterHelpers::harness();
    $billing = count($h['registry']->mcpTools('billing'));
    $support = count($h['registry']->mcpTools('support'));
    expect($billing)->toBeGreaterThan(0)
        ->and($support)->toBeGreaterThan(0);

    $count = ihInvokeToolCount(ihCommandApp(
        [CapabilityRegistry::class => $h['registry']],
        ihMcpSurfacesConfig([
            'profiles' => [
                'billing' => ['create-invoice', 'void-invoice', 'list-invoices'],
                'support' => ['list-invoices', 'get-customer'],
            ],
        ]),
    ));

    expect($count)->toBe($billing + $support);
});

function ihInvokeProgressReady(object $app): ?bool
{
    $cmd = new IntegrationHealthCommand;
    $ref = new ReflectionClass($cmd);
    $ref->getProperty('laravel')->setValue($cmd, $app);
    $cb = $ref->getMethod('progressStoreReadyCallback')->invoke($cmd);
    expect($cb)->toBeCallable();

    return $cb();
}

it('progress readiness probe resolves the AI ProgressStoreReadiness by class-string', function () {
    $abstract = 'Rawphp\\CapabilitiesAi\\Contracts\\ProgressStoreReadiness';
    $probe = static fn (bool $ready): object => new class($ready)
    {
        public function __construct(private bool $ready) {}

        public function isReady(): bool
        {
            return $this->ready;
        }
    };

    expect(ihInvokeProgressReady(ihCommandApp([$abstract => $probe(true)])))->toBeTrue()
        ->and(ihInvokeProgressReady(ihCommandApp([$abstract => $probe(false)])))->toBeFalse()
        ->and(ihInvokeProgressReady(ihCommandApp()))->toBeNull()
        ->and(ihInvokeProgressReady(ihCommandApp([$abstract => new stdClass])))->toBeFalse();
});

it('progress readiness probe is not ready when resolving the store throws', function () {
    $app = new class
    {
        public function bound(string $abstract): bool
        {
            return true;
        }

        public function make(string $abstract): mixed
        {
            throw new RuntimeException('progress.driver=redis requires a Redis client');
        }
    };

    expect(ihInvokeProgressReady($app))->toBeFalse();
});

/**
 * Invoke private auditWriterWiredCallback against a fake app.
 */
function ihInvokeAuditWired(object $app): bool
{
    $cmd = new IntegrationHealthCommand;
    $ref = new ReflectionClass($cmd);
    $ref->getProperty('laravel')->setValue($cmd, $app);
    $cb = $ref->getMethod('auditWriterWiredCallback')->invoke($cmd);
    expect($cb)->toBeCallable();

    return (bool) $cb();
}

it('audit writer probe reads the live registry writer, not a container binding', function () {
    $wired = AdapterHelpers::harness()['registry'];
    $unwired = AdapterHelpers::harness()['registry']->withAuditWriter(null);

    expect(ihInvokeAuditWired(ihCommandApp([CapabilityRegistry::class => $wired])))->toBeTrue()
        ->and(ihInvokeAuditWired(ihCommandApp([
            CapabilityRegistry::class => $unwired,
            AuditWriter::class => $wired->audit(),
        ])))->toBeFalse()
        ->and(ihInvokeAuditWired(ihCommandApp([])))->toBeFalse();
});

it('command prints OK and exits 0 when a bus-only host is wired', function () {
    $h = AdapterHelpers::harness();

    $r = ArtisanCommandHarness::run(new IntegrationHealthCommand, [], [
        Authorizer::class => $h['fakes']->authorizer,
        CapabilityRegistry::class => $h['registry'],
    ], ihMcpSurfacesConfig());

    expect($r['exit'])->toBe(0)
        ->and($r['output'])->toStartWith("Mode: bus-only\n")
        ->and($r['output'])->toContain('[OK] authorizer_bound: Authorizer is bound.')
        ->and($r['output'])->toContain('[OK] audit_writer:')
        ->and($r['output'])->toContain('[OK] mcp_tools:')
        ->and($r['output'])->toEndWith("Integration health: OK\n");
});

it('command prints FAIL and WARN lines and exits 1 when the authorizer and audit writer are missing', function () {
    $r = ArtisanCommandHarness::run(new IntegrationHealthCommand, [], [], [
        'capabilities' => ['surfaces' => ['http' => ['enabled' => true]]],
    ]);

    expect($r['exit'])->toBe(1)
        ->and($r['output'])->toContain('[FAIL] authorizer_bound:')
        ->and($r['output'])->toContain('[WARN] audit_writer:')
        ->and($r['output'])->toContain('[SKIP] mcp_tools:')
        ->and($r['output'])->toEndWith("Integration health: FAILED\n");
});

it('command switches to ai-chat mode from capabilities-ai config and reports the readiness class', function () {
    $h = AdapterHelpers::harness();

    $r = ArtisanCommandHarness::run(new IntegrationHealthCommand, [], [
        Authorizer::class => $h['fakes']->authorizer,
        CapabilityRegistry::class => $h['registry'],
        IntegrationHealthChecker::AI_IDEMPOTENCY_READINESS => new stdClass,
    ], [
        'capabilities' => ['surfaces' => ['http' => ['enabled' => true]]],
        'capabilities-ai' => ['routes' => ['enabled' => true]],
    ]);

    expect($r['output'])->toStartWith("Mode: ai-chat\n")
        ->and($r['output'])->toContain('[OK] ai_always_ready: IdempotencyReadiness is stdClass')
        ->and($r['output'])->toContain('[FAIL] ai_context_bound:')
        ->and($r['exit'])->toBe(1);
});

it('command treats an unbound config repository as empty config (defaults, bus-only)', function () {
    $r = ArtisanCommandHarness::run(new IntegrationHealthCommand);

    expect($r['output'])->toStartWith("Mode: bus-only\n")
        ->and($r['output'])->toContain('[FAIL] authorizer_bound:')
        ->and($r['exit'])->toBe(1);
});

it('readiness class probe returns the resolved class, null when unbound or unresolvable', function () {
    $invoke = static function (object $app): ?string {
        $cmd = new IntegrationHealthCommand;
        $ref = new ReflectionClass($cmd);
        $ref->getProperty('laravel')->setValue($cmd, $app);

        return $ref->getMethod('idempotencyReadinessClassCallback')->invoke($cmd)();
    };
    $abstract = IntegrationHealthChecker::AI_IDEMPOTENCY_READINESS;
    $throwing = new class
    {
        public function bound(string $abstract): bool
        {
            return true;
        }

        public function make(string $abstract): mixed
        {
            throw new RuntimeException('unresolvable');
        }
    };

    expect($invoke(ihCommandApp([$abstract => new stdClass])))->toBe(stdClass::class)
        ->and($invoke(ihCommandApp([$abstract => 'not-an-object'])))->toBeNull()
        ->and($invoke(ihCommandApp()))->toBeNull()
        ->and($invoke($throwing))->toBeNull();
});
