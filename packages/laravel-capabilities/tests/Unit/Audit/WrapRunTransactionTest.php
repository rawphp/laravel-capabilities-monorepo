<?php

// L-010 / D-010: transactions.wrap_run=true really wraps run() in one connection
// transaction on the injected connection. Throwaway SQLite only — no external DB.

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\ConnectionInterface;
use Rawphp\Capabilities\Boot\ContainerBindings;
use Rawphp\Capabilities\Capability;
use Rawphp\Capabilities\Tests\Fixtures\AuditHelpers;
use Rawphp\Capabilities\Tests\Fixtures\BootHelpers;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceInput;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceResult;
use Rawphp\Capabilities\Tests\Fixtures\PipelineHelpers;

function wrapRunConnection(): ConnectionInterface
{
    $capsule = new Capsule;
    $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);

    return $capsule->getConnection();
}

it('happy: wrap_run=true runs the domain inside one transaction on the injected connection [L-010 / D-010]', function () {
    $connection = wrapRunConnection();
    $levels = [];
    $h = AuditHelpers::harness([
        'transactions' => ['wrap_run' => true],
        'transaction_connection' => $connection,
        'run' => function () use ($connection, &$levels) {
            $levels[] = $connection->transactionLevel();

            return new CreateInvoiceResult(invoice_id: 1);
        },
    ]);

    $result = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options());

    expect($result->isOk())->toBeTrue()
        ->and($levels)->toBe([1])
        ->and($connection->transactionLevel())->toBe(0)
        ->and($h['registry']->lastRunWasWrapped())->toBeTrue()
        ->and($h['registry']->transactionConnection())->toBe($connection);
});

it('happy: wrap_run=false never opens a transaction even with a connection wired [D-010 default]', function () {
    $connection = wrapRunConnection();
    $levels = [];
    $h = AuditHelpers::harness([
        'transactions' => ['wrap_run' => false],
        'transaction_connection' => $connection,
        'run' => function () use ($connection, &$levels) {
            $levels[] = $connection->transactionLevel();

            return new CreateInvoiceResult(invoice_id: 1);
        },
    ]);

    $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options());

    expect($levels)->toBe([0])
        ->and($h['registry']->lastRunWasWrapped())->toBeFalse();
});

it('fail: wrap_run=true without a connection fails closed before run() [L-010]', function () {
    $h = AuditHelpers::harness(['transactions' => ['wrap_run' => true]]);

    $result = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options());

    expect($result->errorCode())->toBe('not_configured')
        ->and($result->error['message'])->toContain('wrap_run')
        ->and($h['runCount']->value)->toBe(0)
        ->and($h['registry']->lastRunWasWrapped())->toBeFalse();
});

it('fail: a domain throw inside the wrapped run rolls back and keeps the domain_error mapping [L-010 / D-010]', function () {
    $connection = wrapRunConnection();
    $connection->statement('create table wrap_probe (id integer primary key, note text)');
    $h = AuditHelpers::harness([
        'transactions' => ['wrap_run' => true],
        'transaction_connection' => $connection,
        'run' => function () use ($connection) {
            $connection->table('wrap_probe')->insert(['note' => 'partial write']);
            throw new RuntimeException('ledger rejected');
        },
    ]);

    $result = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options());

    expect($result->errorCode())->toBe('domain_error')
        ->and($result->error['message'])->toBe('ledger rejected')
        ->and($connection->table('wrap_probe')->count())->toBe(0)
        ->and($connection->transactionLevel())->toBe(0);
});

it('happy: makeRegistry hands the host connection to the pipeline for wrap_run [L-010]', function () {
    $connection = wrapRunConnection();
    $levels = [];
    $registry = ContainerBindings::makeRegistry(BootHelpers::config([
        'approval' => ['store' => 'memory'],
        'idempotency' => ['driver' => 'memory'],
        'transactions' => ['wrap_run' => true],
    ]), null, null, null, $connection);
    Capability::define('wrapped')
        ->description('wrapped run')
        ->input(CreateInvoiceInput::class)
        ->output(CreateInvoiceResult::class)
        ->authorize(fn () => true)
        ->run(function () use ($connection, &$levels) {
            $levels[] = $connection->transactionLevel();

            return new CreateInvoiceResult(invoice_id: 2);
        })
        ->register($registry);

    $result = $registry->invoke('wrapped', PipelineHelpers::validInput(), PipelineHelpers::options());

    expect($result->isOk())->toBeTrue()
        ->and($levels)->toBe([1])
        ->and($registry->transactionConnection())->toBe($connection);
});
