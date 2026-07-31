<?php

declare(strict_types=1);

namespace Rawphp\Capabilities\Tests\Fixtures;

use Rawphp\Capabilities\Adapters\Http\ApprovalController;
use Rawphp\Capabilities\Adapters\Http\AuthController;
use Rawphp\Capabilities\Adapters\Http\CapabilityController;
use Rawphp\Capabilities\Approval\ApprovalManager;
use Rawphp\Capabilities\Contracts\AuthTokenIssuer;
use Rawphp\Capabilities\Http\HttpAuthGate;
use Rawphp\Capabilities\Http\HttpRequestContext;
use Rawphp\Capabilities\Http\RouteTable;
use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Rawphp\Capabilities\Schema\CatalogPresenter;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Tests\Support\SharedFakes;
use stdClass;

/**
 * Shared builders for HTTP surface unit tests (no Illuminate kernel / DB).
 */
final class HttpHelpers
{
    /**
     * @param  array<string, mixed>  $opts
     * @return array{
     *     registry: CapabilityRegistry,
     *     controller: CapabilityController,
     *     approvals: ApprovalManager,
     *     approvalController: ApprovalController,
     *     auth: AuthController,
     *     fakes: SharedFakes,
     *     name: string,
     *     user: object
     * }
     */
    public static function harness(array $opts = []): array
    {
        $h = CatalogHelpers::harness($opts);
        $user = $opts['user'] ?? self::user(1);
        $clients = $opts['clients'] ?? [
            'token_abilities' => ['capabilities:cli' => 'cli'],
            'oauth' => ['cli-app' => 'cli'],
            'privilege_order' => ['http', 'cli', 'mcp', 'agent', 'job'],
            'reject_upgrade_attempts' => false,
        ];
        $httpConfig = $opts['http'] ?? [
            'enabled' => true,
            'prefix' => 'capabilities',
            'middleware' => ['api', 'auth:sanctum'],
            'health_public' => $opts['health_public'] ?? false,
        ];

        $controller = new CapabilityController(
            $h['registry'],
            $clients,
            $httpConfig,
            new HttpAuthGate(['health_public' => (bool) ($httpConfig['health_public'] ?? false)]),
        );

        $approvals = ApprovalManager::inMemory($h['fakes']->clock);
        if (isset($opts['approval_executor']) && is_callable($opts['approval_executor'])) {
            $approvals = new ApprovalManager(
                $h['fakes']->approvals,
                $h['fakes']->clock,
                ['default_policy' => 'any_staff', 'execution' => 'deferred'],
                executor: $opts['approval_executor'],
            );
        }

        $approvalController = new ApprovalController($approvals, $httpConfig);
        /** @var AuthTokenIssuer|null $issuer */
        $issuer = array_key_exists('issuer', $opts)
            ? $opts['issuer']
            : null;
        $auth = new AuthController(
            $httpConfig,
            $opts['cli'] ?? ['enabled' => true],
            $issuer instanceof AuthTokenIssuer ? $issuer : null,
        );

        return [
            'registry' => $h['registry'],
            'controller' => $controller,
            'approvals' => $approvals,
            'approvalController' => $approvalController,
            'auth' => $auth,
            'fakes' => $h['fakes'],
            'name' => $h['name'],
            'user' => $user,
            'http' => $httpConfig,
            'clients' => $clients,
        ];
    }

    public static function user(int|string $id = 1): object
    {
        $u = new stdClass;
        $u->id = $id;
        $u->is_staff = true;

        return $u;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public static function authedRequest(array $overrides = []): HttpRequestContext
    {
        $user = $overrides['user'] ?? self::user();
        unset($overrides['user']);

        $base = [
            'authenticated' => true,
            'user' => $user,
            'authKind' => HttpAuthGate::AUTH_USER,
            'credential' => ['adapter' => 'http'],
            'headers' => [],
            'jsonBody' => null,
            'method' => 'GET',
        ];

        return new HttpRequestContext(...array_merge($base, $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public static function guestRequest(array $overrides = []): HttpRequestContext
    {
        return new HttpRequestContext(...array_merge([
            'authenticated' => false,
            'user' => null,
            'authKind' => HttpAuthGate::AUTH_NONE,
            'credential' => [],
            'headers' => [],
            'jsonBody' => null,
            'method' => 'GET',
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $httpConfig
     * @return list<array{key: string, method: string, uri: string, name: string, action: string, middleware: list<string>}>
     */
    public static function routes(array $httpConfig = []): array
    {
        return RouteTable::routes($httpConfig === [] ? [
            'enabled' => true,
            'prefix' => 'capabilities',
            'middleware' => ['api', 'auth:sanctum'],
        ] : $httpConfig);
    }

    /**
     * Mock bus that returns fixed results — domain commit only when configured.
     */
    public static function mockBus(
        ?CapabilityResult $invokeResult = null,
        ?CatalogPresenter $catalog = null,
        bool $domainCommitted = false,
    ): FakeCapabilityBus {
        return new FakeCapabilityBus($invokeResult, $catalog, $domainCommitted);
    }

    /**
     * In-memory AuthTokenIssuer for unit tests (L-002). Never used as production default.
     *
     * @param  array<string, mixed>  $overrides  keys: token|device|oauth → response arrays
     */
    public static function fakeAuthTokenIssuer(array $overrides = []): AuthTokenIssuer
    {
        return new class($overrides) implements AuthTokenIssuer
        {
            /** @param array<string, mixed> $overrides */
            public function __construct(private readonly array $overrides) {}

            public function issueToken(HttpRequestContext $request, array $body): array
            {
                return $this->overrides['token'] ?? [
                    'token_type' => 'Bearer',
                    'access_token' => 'host-issued-token',
                    'expires_in' => 3600,
                ];
            }

            public function issueDeviceCode(HttpRequestContext $request, array $body): array
            {
                return $this->overrides['device'] ?? [
                    'device_code' => 'host-device-code',
                    'user_code' => 'HOST-USER',
                    'verification_uri' => 'https://example.test/device',
                    'expires_in' => 600,
                    'interval' => 5,
                ];
            }

            public function handleOAuthCallback(HttpRequestContext $request, array $query): array
            {
                return $this->overrides['oauth'] ?? [
                    'status' => 'authorized',
                    'code' => $query['code'] ?? null,
                ];
            }
        };
    }
}
