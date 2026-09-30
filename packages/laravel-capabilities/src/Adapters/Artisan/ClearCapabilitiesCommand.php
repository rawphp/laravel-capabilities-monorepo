<?php

namespace Rawphp\Capabilities\Adapters\Artisan;

use Illuminate\Console\Command;
use Rawphp\Capabilities\Discovery\DiscoveryManifest;

/**
 * `capabilities:clear` — remove the discovery class map so boot scans again (L-015).
 * Hooked into `optimize:clear`.
 */
class ClearCapabilitiesCommand extends Command
{
    public const SIGNATURE_NAME = 'capabilities:clear';

    protected $signature = 'capabilities:clear';

    protected $description = 'Remove the cached #[Capability] class map.';

    public function __construct(
        private readonly ?string $manifestPath = null,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $path = $this->manifestPath ?? DiscoveryManifest::pathFor($this->laravel);
        if ($path === null) {
            $this->info('No bootstrap/cache path is available; nothing to clear.');

            return self::SUCCESS;
        }

        $this->info(DiscoveryManifest::clear($path) ? 'Capability cache cleared.' : 'No capability cache to clear.');

        return self::SUCCESS;
    }
}
