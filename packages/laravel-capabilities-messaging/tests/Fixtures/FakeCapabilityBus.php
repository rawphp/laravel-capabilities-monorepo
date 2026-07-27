<?php

namespace Rawphp\CapabilitiesMessaging\Tests\Fixtures;

use Rawphp\Capabilities\Contracts\CapabilityBus;
use Rawphp\Capabilities\Schema\CatalogPresenter;
use Rawphp\Capabilities\Support\CapabilityResult;
use RuntimeException;

/**
 * Minimal CapabilityBus fake for messaging unit tests — no domain, no DB.
 */
final class FakeCapabilityBus implements CapabilityBus
{
    /** @var list<array{name: string, input: array<string, mixed>, options: array<string, mixed>}> */
    private array $invocations = [];

    /** @var array<string, CapabilityResult|callable> */
    private array $responses = [];

    private ?string $forceError = null;

    public function alwaysFail(string $code): void
    {
        $this->forceError = $code;
    }

    public function clearForceFail(): void
    {
        $this->forceError = null;
    }

    /**
     * @param  CapabilityResult|callable(string, array, array): CapabilityResult  $response
     */
    public function when(string $name, CapabilityResult|callable $response): void
    {
        $this->responses[$name] = $response;
    }

    public function invoke(string $nameOrAlias, array $input = [], array $options = []): CapabilityResult
    {
        $this->invocations[] = [
            'name' => $nameOrAlias,
            'input' => $input,
            'options' => $options,
        ];

        if ($this->forceError !== null) {
            return CapabilityResult::failure(code: $this->forceError, message: $this->forceError);
        }

        if (isset($this->responses[$nameOrAlias])) {
            $r = $this->responses[$nameOrAlias];
            if (is_callable($r)) {
                return $r($nameOrAlias, $input, $options);
            }

            return $r;
        }

        return CapabilityResult::ok(['name' => $nameOrAlias, 'input' => $input]);
    }

    public function catalog(): CatalogPresenter
    {
        throw new RuntimeException('catalog() not used in messaging unit tests.');
    }

    /**
     * @return list<array{name: string, input: array<string, mixed>, options: array<string, mixed>}>
     */
    public function invocations(): array
    {
        return $this->invocations;
    }

    public function invokeCount(): int
    {
        return count($this->invocations);
    }
}
