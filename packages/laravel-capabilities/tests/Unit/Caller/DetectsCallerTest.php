<?php

declare(strict_types=1);

// DetectsCaller trait defaults.

use Rawphp\Capabilities\Http\CallerDeriver;
use Rawphp\Capabilities\Http\DetectsCaller;

it('DetectsCaller exposes default caller config, a deriver, and detectCaller results', function () {
    $host = new class
    {
        use DetectsCaller;

        public function expose(): array
        {
            return [
                'config' => $this->callerConfig(),
                'deriver' => $this->callerDeriver(),
                'detected' => $this->detectCaller(['type' => 'session_user'], 'cli'),
            ];
        }
    };

    $out = $host->expose();
    expect($out['config']['token_abilities'])->toHaveKey('capabilities:cli')
        ->and($out['deriver'])->toBeInstanceOf(CallerDeriver::class)
        ->and($out['detected'])->toHaveKeys(['caller', 'derived', 'rejected', 'reason']);
});
