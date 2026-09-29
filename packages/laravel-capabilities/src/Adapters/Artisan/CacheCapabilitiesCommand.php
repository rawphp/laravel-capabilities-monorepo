<?php

namespace Rawphp\Capabilities\Adapters\Artisan;

use Illuminate\Console\Command;
use Rawphp\Capabilities\Discovery\DiscoveryManifest;
use Rawphp\Capabilities\Discovery\DiscoveryPaths;
use Throwable;

/**
 * `capabilities:cache` — write the discovery class map (L-015). Hooked into `optimize`.
 */
class CacheCapabilitiesCommand extends Command
{
    public const SIGNATURE_NAME = 'capabilities:cache';

    protected $signature = 'capabilities:cache';

    protected $description = 'Cache the #[Capability] class map so boot skips scanning the discovery path.';

    public function __construct(
        private readonly ?string $manifestPath = null,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $path = $this->manifestPath ?? DiscoveryManifest::pathFor($this->laravel);
        if ($path === null) {
            $this->error('No bootstrap/cache path is available; capabilities cannot be cached here.');

            return self::FAILURE;
        }

        try {
            $config = $this->laravel->make('config')->get('capabilities', []);
            $classes = DiscoveryManifest::build(DiscoveryPaths::fromConfig(is_array($config) ? $config : []));
            DiscoveryManifest::write($path, $classes);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf('Cached %d capability class%s in %s.', count($classes), count($classes) === 1 ? '' : 'es', $path));

        return self::SUCCESS;
    }
}
