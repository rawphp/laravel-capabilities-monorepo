<?php

declare(strict_types=1);

// PipelineStages ordering and error codes.

use Rawphp\Capabilities\Pipeline\PipelineStages;

it('PipelineStages::ordered starts at schema validation and errorCodeFor maps every stage to its error code', function () {
    $order = PipelineStages::ordered();
    expect($order)->toContain(PipelineStages::RUN)
        ->and($order[0])->toBe(PipelineStages::JSON_SCHEMA_VALIDATE);

    expect(PipelineStages::errorCodeFor(PipelineStages::JSON_SCHEMA_VALIDATE))->toBe('validation_failed')
        ->and(PipelineStages::errorCodeFor(PipelineStages::HYDRATE_DTO))->toBe('validation_failed')
        ->and(PipelineStages::errorCodeFor(PipelineStages::SERVER_ONLY_VALIDATE))->toBe('validation_failed')
        ->and(PipelineStages::errorCodeFor(PipelineStages::RESOLVE_ACTOR))->toBe('unauthenticated')
        ->and(PipelineStages::errorCodeFor(PipelineStages::RESOLVE_SCOPE))->toBe('forbidden')
        ->and(PipelineStages::errorCodeFor(PipelineStages::IDEMPOTENCY_LOOKUP))->toBe('conflict')
        ->and(PipelineStages::errorCodeFor(PipelineStages::AUTHORIZE))->toBe('forbidden')
        ->and(PipelineStages::errorCodeFor(PipelineStages::NEEDS_APPROVAL))->toBe('approval_required')
        ->and(PipelineStages::errorCodeFor(PipelineStages::RATE_LIMIT))->toBe('rate_limited')
        ->and(PipelineStages::errorCodeFor(PipelineStages::RUN))->toBe('internal')
        ->and(PipelineStages::errorCodeFor('unknown'))->toBe('internal');
});
