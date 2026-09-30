<?php

namespace Rawphp\Capabilities\Adapters\Artisan;

use Illuminate\Console\Command;
use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Throwable;

/**
 * Optional in-server ops Artisan command: capability:run (D-016 / REQ-024).
 *
 * Not the product CLI — invoker enforces caller=artisan and ROLE=ops.
 */
class RunCapabilityCommand extends Command
{
    protected $signature = 'capability:run
                            {name : Capability name}
                            {--acting-as= : User id to act as}
                            {--system= : SystemActor name for mutations}
                            {--tenant= : Tenant id}
                            {--input= : JSON input object}';

    protected $description = 'Invoke a capability in-process as an operator (requires --acting-as or --system for mutations).';

    public function __construct(
        private readonly ?CapabilityRegistry $registry = null,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $registry = $this->registry;
        if ($registry === null) {
            try {
                $registry = $this->laravel->make(CapabilityRegistry::class);
            } catch (Throwable) {
                $this->error('CapabilityRegistry is not bound.');

                return self::FAILURE;
            }
        }

        $inputJson = (string) ($this->option('input') ?? '{}');
        $decoded = json_decode($inputJson === '' ? '{}' : $inputJson, true);
        if (! is_array($decoded)) {
            $this->error('Option --input must be a JSON object.');

            return self::FAILURE;
        }

        try {
            $flags = ArtisanCapabilityInvoker::parseFlags([
                'acting-as' => $this->option('acting-as'),
                'system' => $this->option('system'),
                'tenant' => $this->option('tenant'),
            ]);
            $result = (new ArtisanCapabilityInvoker($registry))->run([
                'name' => (string) $this->argument('name'),
                'input' => $decoded,
                ...$flags,
                // --acting-as loads the host's real user through the same lookup approvals use
                // (D-002 / L-107); with no resolver the invoker refuses instead of fabricating one.
                'user_resolver' => $registry->hasRequesterResolver()
                    ? static fn (int|string $id): ?object => $registry->resolveRequester('user', (string) $id)
                    : null,
            ]);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if (! $result->ok) {
            $this->error((string) ($result->error['message'] ?? 'Capability failed'));

            return self::FAILURE;
        }

        $this->line((string) json_encode($result->toArray(), JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
