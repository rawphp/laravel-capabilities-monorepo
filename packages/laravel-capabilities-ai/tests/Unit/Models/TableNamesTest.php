<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Rawphp\CapabilitiesAi\Models\TableNames;

/**
 * @param  array<string, mixed>  $items
 */
function tableNamesConfig(array $items): object
{
    return new class($items)
    {
        /** @param  array<string, mixed>  $items */
        public function __construct(private readonly array $items) {}

        public function get(string $key, mixed $default = null): mixed
        {
            $cur = $this->items;
            foreach (explode('.', $key) as $part) {
                if (! is_array($cur) || ! array_key_exists($part, $cur)) {
                    return $default;
                }
                $cur = $cur[$part];
            }

            return $cur;
        }
    };
}

afterEach(function (): void {
    Container::setInstance(null);
});

it('reads table_prefix from the bound app config (published / cached config wins)', function () {
    $app = new Container;
    $app->instance('config', tableNamesConfig(['capabilities-ai' => ['table_prefix' => 'x_']]));
    Container::setInstance($app);

    expect(TableNames::prefix())->toBe('x_')
        ->and(TableNames::turns())->toBe('x_turns')
        ->and(TableNames::conversations())->toBe('x_conversations');
});

it('falls back to the package default when the bound config has no usable table_prefix', function () {
    $app = new Container;
    $app->instance('config', tableNamesConfig(['capabilities-ai' => ['table_prefix' => '']]));
    Container::setInstance($app);

    expect(TableNames::prefix())->toBe('capabilities_ai_');
});

it('falls back to the package config file without a container config', function () {
    Container::setInstance(new Container);

    expect(TableNames::prefix())->toBe('capabilities_ai_');
});
