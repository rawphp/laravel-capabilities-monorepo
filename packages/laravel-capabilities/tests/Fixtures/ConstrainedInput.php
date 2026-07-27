<?php

declare(strict_types=1);

namespace Rawphp\Capabilities\Tests\Fixtures;

use Rawphp\Capabilities\Attributes\Field;
use Rawphp\Capabilities\Support\CapabilityData;

final class ConstrainedInput extends CapabilityData
{
    public function __construct(
        #[Field(enum: ['usd', 'eur', 'gbp'])]
        public string $currency,
        #[Field(format: 'date')]
        public string $due_date,
        #[Field(minLength: 2, maxLength: 10)]
        public string $code,
        #[Field(minimum: 0, maximum: 100)]
        public int $score,
    ) {}
}
