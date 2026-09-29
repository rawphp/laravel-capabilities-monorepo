<?php

// L-016 / D-002: RunCapabilityJob is a real queueable job — dispatch() enqueues through the
// bus, handle() resolves users through the registry's requester resolver, failed() records
// the D-019 tags. Unit-only: recording bus + container, no queue worker.

declare(strict_types=1);

use Illuminate\Bus\Queueable;
use Illuminate\Container\Container;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Rawphp\Capabilities\Adapters\JobSurface;
use Rawphp\Capabilities\Adapters\RunCapabilityJob;
use Rawphp\Capabilities\Support\MissingJobActorException;
use Rawphp\Capabilities\Support\SystemActor;
use Rawphp\Capabilities\Tests\Fixtures\ScopeCallerJobHelpers as H;

function l016Bus(): Dispatcher
{
    return new class implements Dispatcher
    {
        /** @var list<object> */
        public array $dispatched = [];

        public function dispatch($command)
        {
            $this->dispatched[] = $command;

            return $command;
        }

        public function dispatchSync($command, $handler = null)
        {
            return $this->dispatch($command);
        }

        public function dispatchNow($command, $handler = null)
        {
            return $this->dispatch($command);
        }

        public function hasCommandHandler($command)
        {
            return false;
        }

        public function getCommandHandler($command)
        {
            return false;
        }

        public function pipeThrough(array $pipes)
        {
            return $this;
        }

        public function map(array $map)
        {
            return $this;
        }
    };
}

afterEach(fn () => Container::setInstance(null));

it('is a queueable job (ShouldQueue + Queueable) so a worker can run handle()', function () {
    expect(is_subclass_of(RunCapabilityJob::class, ShouldQueue::class))->toBeTrue()
        ->and(in_array(Queueable::class, class_uses(RunCapabilityJob::class), true))->toBeTrue()
        ->and(method_exists(RunCapabilityJob::class, 'onQueue'))->toBeTrue()
        ->and(method_exists(RunCapabilityJob::class, 'failed'))->toBeTrue();
});

it('dispatch() validates the actor and pushes the job onto the bus', function () {
    $bus = l016Bus();

    $job = RunCapabilityJob::dispatch(['name' => 'cap', 'actingAs' => SystemActor::named('scheduler'), 'tenantId' => 't1'], $bus);

    expect($bus->dispatched)->toBe([$job])
        ->and($job->name)->toBe('cap')
        ->and(fn () => RunCapabilityJob::dispatch(['name' => 'cap'], $bus))->toThrow(MissingJobActorException::class)
        ->and($bus->dispatched)->toHaveCount(1);
});

it('dispatch() resolves the bus from the container when none is passed, and fails closed without one', function () {
    $bus = l016Bus();
    $container = new Container;
    $container->instance(Dispatcher::class, $bus);
    Container::setInstance($container);

    $job = RunCapabilityJob::dispatch(['name' => 'cap', 'actingAs' => 7]);
    expect($bus->dispatched)->toBe([$job]);

    Container::setInstance(new Container);
    expect(fn () => RunCapabilityJob::dispatch(['name' => 'cap', 'actingAs' => 7]))
        ->toThrow(LogicException::class, 'Dispatcher');
});

it('make() builds the validated job without enqueuing', function () {
    $job = RunCapabilityJob::make(['name' => 'cap', 'actingAs' => 7, 'tenantId' => 't1']);

    expect($job)->toBeInstanceOf(RunCapabilityJob::class)
        ->and($job->actingAs)->toBe(7)
        ->and(fn () => RunCapabilityJob::make(['name' => 'cap']))->toThrow(MissingJobActorException::class);
});

it('handle() resolves a user id through the registry requester resolver when no option is passed', function () {
    $h = H::scopeHarness();
    $user = new stdClass;
    $user->id = 7;
    $user->tenant_id = 'tenant-a';
    $seen = [];
    $h['registry']->withRequesterResolver(function (string $type, string $id) use ($user, &$seen): ?object {
        $seen[] = [$type, $id];

        return $id === '7' ? $user : null;
    });

    $job = new RunCapabilityJob(name: $h['name'], input: H::homeInput(), actingAs: 7, tenantId: 'tenant-a');
    $result = $job->handle($h['registry']);

    expect($result->isOk())->toBeTrue()
        ->and($seen)->toBe([['user', '7']])
        ->and($h['registry']->lastState()?->context?->actor())->toBe($user);
});

it('handle() still fails closed when neither a resolver option nor a registry resolver exists', function () {
    $h = H::scopeHarness();
    $job = new RunCapabilityJob(name: $h['name'], input: H::homeInput(), actingAs: 7, tenantId: 'tenant-a');

    expect(fn () => $job->handle($h['registry']))->toThrow(MissingJobActorException::class);
});

it('handle() refuses a user id the registry resolver cannot find', function () {
    $h = H::scopeHarness();
    $h['registry']->withRequesterResolver(fn (string $type, string $id): ?object => null);
    $job = new RunCapabilityJob(name: $h['name'], input: H::homeInput(), actingAs: 404, tenantId: 'tenant-a');

    expect(fn () => $job->handle($h['registry']))->toThrow(RuntimeException::class, '404');
});

it('failed() records the D-019 tags with the exception and logs through the container logger when bound', function () {
    $logger = new class
    {
        /** @var list<array{0: string, 1: array<string, mixed>}> */
        public array $errors = [];

        public function error(string $message, array $context = []): void
        {
            $this->errors[] = [$message, $context];
        }
    };
    $container = new Container;
    $container->instance('log', $logger);
    Container::setInstance($container);

    $job = new RunCapabilityJob(name: 'cap-x', actingAs: SystemActor::named('scheduler'), tenantId: 't1');
    $job->failed(new RuntimeException('worker died'));

    expect($job->lastFailure())->toMatchArray(['capability' => 'cap-x', 'caller' => 'job', 'actor_type' => 'system', 'tenant_id' => 't1', 'exception' => RuntimeException::class, 'message' => 'worker died'])
        ->and($logger->errors)->toHaveCount(1)
        ->and($logger->errors[0][1]['capability'])->toBe('cap-x');

    Container::setInstance(new Container);
    (new RunCapabilityJob(name: 'cap-y', actingAs: 1))->failed(new RuntimeException('no logger'));
});

it('job surface lists make alongside dispatch helpers', function () {
    expect(JobSurface::registeredHelpers(['enabled' => true]))->toContain('make', 'dispatch', 'dispatchSync');
});
