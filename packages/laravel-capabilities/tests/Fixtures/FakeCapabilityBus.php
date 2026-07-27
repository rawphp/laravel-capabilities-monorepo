<?php

declare(strict_types=1);

namespace Rawphp\Capabilities\Tests\Fixtures;

use Rawphp\Capabilities\Contracts\CapabilityBus;
use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Rawphp\Capabilities\Schema\CatalogPresenter;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Support\ErrorCodeMap;

/**
 * In-memory CapabilityBus for controller unit tests (no DB).
 */
final class FakeCapabilityBus implements CapabilityBus
{
    public int $invokeCalls = 0;

    /** @var list<array{name: string, input: array<string, mixed>, options: array<string, mixed>}> */
    public array $invocations = [];

    public bool $domainCommitted = false;

    public function __construct(
        private ?CapabilityResult $invokeResult = null,
        private ?CatalogPresenter $catalog = null,
        bool $domainCommitted = false,
        private readonly ?CapabilityRegistry $backing = null,
    ) {
        $this->domainCommitted = $domainCommitted;
    }

    public function invoke(string $nameOrAlias, array $input = [], array $options = []): CapabilityResult
    {
        $this->invokeCalls++;
        $this->invocations[] = [
            'name' => $nameOrAlias,
            'input' => $input,
            'options' => $options,
        ];

        if ($this->backing !== null) {
            $result = $this->backing->invoke($nameOrAlias, $input, $options);
            if ($result->isOk() || $result->errorCode() === 'domain_error') {
                $this->domainCommitted = true;
            }

            return $result;
        }

        if ($this->invokeResult !== null) {
            $code = $this->invokeResult->errorCode();
            // Only mark commit when invoke actually runs (not on construction).
            if ($this->invokeResult->isOk() || $code === 'domain_error') {
                $this->domainCommitted = true;
            }

            return $this->invokeResult;
        }

        $this->domainCommitted = true;

        return CapabilityResult::ok(['ok' => true]);
    }

    public function catalog(): CatalogPresenter
    {
        if ($this->catalog !== null) {
            return $this->catalog;
        }

        if ($this->backing !== null) {
            return $this->backing->catalog();
        }

        $h = CatalogHelpers::harness();

        return $h['catalog'];
    }

    public function setResult(CapabilityResult $result): void
    {
        $this->invokeResult = $result;
    }

    public static function failing(string $code): self
    {
        $result = $code === 'approval_required'
            ? CapabilityResult::approvalRequired('appr-http-1', 'needs approval')
            : CapabilityResult::failure($code, 'error: '.$code, ErrorCodeMap::wireFields($code));

        // domainCommitted starts false; set true only when invoke() runs for domain_error/ok.
        return new self($result, domainCommitted: false);
    }
}
