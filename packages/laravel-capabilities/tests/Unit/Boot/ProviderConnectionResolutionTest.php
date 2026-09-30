<?php

// L-008: approval.connection / idempotency.connection must win over the container's
// default ConnectionInterface (which Laravel aliases to db.connection). Unit-only.

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\ConnectionInterface;
use Rawphp\Capabilities\Approval\ApprovalManager;
use Rawphp\Capabilities\Boot\BootException;
use Rawphp\Capabilities\Contracts\IdempotencyStore;
use Rawphp\Capabilities\Persistence\QueryTableGateway;
use Rawphp\Capabilities\Tests\Fixtures\BootHelpers;
use Rawphp\Capabilities\Tests\Fixtures\FakeProviderApp;

function l008Connection(): ConnectionInterface
{
    $capsule = new Capsule;
    $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);

    return $capsule->getConnection();
}

/**
 * @param  array<string, ConnectionInterface>  $named
 */
function l008DbManager(ConnectionInterface $default, array $named): object
{
    return new class($default, $named)
    {
        /** @var list<string|null> */
        public array $asked = [];

        public function __construct(private ConnectionInterface $default, private array $named) {}

        public function connection(?string $name = null): ConnectionInterface
        {
            $this->asked[] = $name;

            if ($name === null) {
                return $this->default;
            }

            // Laravel's DatabaseManager throws InvalidArgumentException for an unknown name.
            return $this->named[$name] ?? throw new InvalidArgumentException("Database connection [{$name}] not configured.");
        }
    };
}

function l008StoreConnection(object $store): ConnectionInterface
{
    $table = (new ReflectionClass($store))->getProperty('table')->getValue($store);
    expect($table)->toBeInstanceOf(QueryTableGateway::class);

    return (new ReflectionClass(QueryTableGateway::class))->getProperty('connection')->getValue($table);
}

it('resolves approval.connection through the db manager even when ConnectionInterface is bound', function () {
    $default = l008Connection();
    $ledger = l008Connection();
    $db = l008DbManager($default, ['ledger' => $ledger]);

    $app = FakeProviderApp::registered(
        BootHelpers::config([
            'approval' => ['store' => 'database', 'connection' => 'ledger'],
            'idempotency' => ['driver' => 'database'],
        ]),
        [ConnectionInterface::class => $default, 'db' => $db],
    );

    $approval = $app->make(ApprovalManager::class);
    $idempotency = $app->make(IdempotencyStore::class);

    expect(l008StoreConnection($approval->store()))->toBe($ledger)
        ->and(l008StoreConnection($idempotency))->toBe($default)
        ->and($db->asked)->toBe(['ledger']);
});

it('resolves idempotency.connection independently of approval.connection', function () {
    $default = l008Connection();
    $keys = l008Connection();
    $db = l008DbManager($default, ['keys' => $keys]);

    $app = FakeProviderApp::registered(
        BootHelpers::config([
            'approval' => ['store' => 'database'],
            'idempotency' => ['driver' => 'database', 'connection' => 'keys'],
        ]),
        [ConnectionInterface::class => $default, 'db' => $db],
    );

    expect(l008StoreConnection($app->make(IdempotencyStore::class)))->toBe($keys)
        ->and(l008StoreConnection($app->make(ApprovalManager::class)->store()))->toBe($default);
});

it('falls back to the bound ConnectionInterface, then the db default, when no name is configured', function () {
    $bound = l008Connection();
    $dbDefault = l008Connection();

    $viaBound = FakeProviderApp::registered(
        BootHelpers::config(['approval' => ['store' => 'database'], 'idempotency' => ['driver' => 'database']]),
        [ConnectionInterface::class => $bound, 'db' => l008DbManager($dbDefault, [])],
    );
    $viaDb = FakeProviderApp::registered(
        BootHelpers::config(['approval' => ['store' => 'database'], 'idempotency' => ['driver' => 'database']]),
        ['db' => l008DbManager($dbDefault, [])],
    );

    expect(l008StoreConnection($viaBound->make(ApprovalManager::class)->store()))->toBe($bound)
        ->and(l008StoreConnection($viaDb->make(ApprovalManager::class)->store()))->toBe($dbDefault);
});

it('a configured connection name that cannot be resolved fails closed rather than using the default', function () {
    $default = l008Connection();

    $app = FakeProviderApp::registered(
        BootHelpers::config(['approval' => ['store' => 'database', 'connection' => 'missing'], 'idempotency' => ['driver' => 'memory']]),
        [ConnectionInterface::class => $default, 'db' => l008DbManager($default, [])],
    );

    expect(fn () => $app->make(ApprovalManager::class))->toThrow(BootException::class, 'connection');
});
