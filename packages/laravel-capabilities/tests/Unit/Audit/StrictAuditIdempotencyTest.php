<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\ConnectionInterface;
use Rawphp\Capabilities\Tests\Fixtures\AuditHelpers;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceResult;

function strictAuditConnection(): ConnectionInterface
{
    $capsule = new Capsule;
    $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    $connection = $capsule->getConnection();
    $connection->statement('create table wrap_probe (id integer primary key, note text)');

    return $connection;
}

it('fail: a strict audit failure is replayed as audit_failed and does not run again [D-010]', function () {
    $h = AuditHelpers::harness(['mode' => 'strict', 'fail_audit' => true]);
    $options = AuditHelpers::options('http', ['idempotency_key' => 'strict-audit-replay-1']);

    $first = $h['registry']->invoke($h['name'], AuditHelpers::input(), $options);
    $second = $h['registry']->invoke($h['name'], AuditHelpers::input(), $options);

    expect($first->isOk())->toBeFalse()
        ->and($first->errorCode())->toBe('audit_failed')
        ->and($second->isOk())->toBeFalse()
        ->and($second->errorCode())->toBe('audit_failed')
        ->and($h['runCount']->value)->toBe(1);
});

it('fail: strict audit failure inside wrap_run rolls the domain back and a retry can run [D-010]', function () {
    $connection = strictAuditConnection();
    $h = AuditHelpers::harness([
        'mode' => 'strict',
        'fail_audit' => true,
        'transactions' => ['wrap_run' => true],
        'transaction_connection' => $connection,
        'run' => function () use ($connection, &$ran) {
            $ran = ($ran ?? 0) + 1;
            $connection->table('wrap_probe')->insert(['note' => 'committed']);

            return new CreateInvoiceResult(invoice_id: 1);
        },
    ]);
    $options = AuditHelpers::options('http', ['idempotency_key' => 'strict-audit-wrap-1']);

    $first = $h['registry']->invoke($h['name'], AuditHelpers::input(), $options);

    expect($first->errorCode())->toBe('audit_failed')
        ->and($first->error['domain_committed'] ?? null)->toBeFalse()
        ->and($connection->table('wrap_probe')->count())->toBe(0)
        ->and($connection->transactionLevel())->toBe(0)
        ->and($ran ?? 0)->toBe(1);

    $second = $h['registry']->invoke($h['name'], AuditHelpers::input(), $options);

    expect($second->errorCode())->toBe('audit_failed')
        ->and($connection->table('wrap_probe')->count())->toBe(0)
        ->and($ran ?? 0)->toBe(2);
});

it('fail: a rolled-back strict wrap does not queue a success audit to the outbox [D-010]', function () {
    $connection = strictAuditConnection();
    $h = AuditHelpers::harness([
        'mode' => 'strict',
        'required' => true,
        'fail_audit' => true,
        'transactions' => ['wrap_run' => true],
        'transaction_connection' => $connection,
    ]);

    $result = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options());

    expect($result->errorCode())->toBe('audit_failed')
        ->and($result->error['domain_committed'] ?? null)->toBeFalse()
        ->and($h['outbox']->all())->toBe([]);
});

it('fail: strict audit failure without a wrap still queues the committed entry when required [D-010]', function () {
    $h = AuditHelpers::harness(['mode' => 'strict', 'required' => true, 'fail_audit' => true]);

    $result = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options());

    expect($result->errorCode())->toBe('audit_failed')
        ->and($h['outbox']->all())->toHaveCount(1);
});

it('happy: best_effort audit failure inside wrap_run keeps the domain write [D-010]', function () {
    $connection = strictAuditConnection();
    $h = AuditHelpers::harness([
        'mode' => 'best_effort',
        'fail_audit' => true,
        'transactions' => ['wrap_run' => true],
        'transaction_connection' => $connection,
        'run' => function () use ($connection) {
            $connection->table('wrap_probe')->insert(['note' => 'kept']);

            return new CreateInvoiceResult(invoice_id: 1);
        },
    ]);

    $result = $h['registry']->invoke($h['name'], AuditHelpers::input(), AuditHelpers::options());

    expect($result->isOk())->toBeTrue()
        ->and($connection->table('wrap_probe')->count())->toBe(1)
        ->and($connection->transactionLevel())->toBe(0);
});
