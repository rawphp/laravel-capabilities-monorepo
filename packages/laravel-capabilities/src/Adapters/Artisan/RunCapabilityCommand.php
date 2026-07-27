<?php

namespace Rawphp\Capabilities\Adapters\Artisan;

use Illuminate\Console\Command;
use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Rawphp\Capabilities\Support\CapabilityResult;
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

        $invoker = new ArtisanCapabilityInvoker($registry);

        try {
            $result = $invoker->invoke([
                'name' => (string) $this->argument('name'),
                'input' => $decoded,
                'acting_as' => $this->option('acting-as'),
                'system' => $this->option('system'),
                'tenant' => $this->option('tenant'),
            ]);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($result instanceof CapabilityResult && ! $result->ok) {
            $this->error($result->error['message'] ?? 'Capability failed');

            return self::FAILURE;
        }

        $this->line(json_encode($result instanceof CapabilityResult ? $result->toArray() : $result, JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
