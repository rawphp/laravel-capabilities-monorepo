<?php

declare(strict_types=1);

use Rawphp\Capabilities\Tests\Fixtures\ApprovalHelpers;

/**
 * @param  list<array<string, mixed>>  $entries
 * @return array<string, mixed>|null
 */
function decidedEntry(array $entries): ?array
{
    foreach ($entries as $e) {
        if (($e['event'] ?? null) === 'approval.decided') {
            return $e;
        }
    }

    return null;
}

it('happy: accept records decided_via channel identity on approval.decided [D-006]', function () {
    $h = ApprovalHelpers::withPending();

    $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester(), [
        'decided_via' => ['channel' => 'telegram', 'channel_user_id' => '42'],
    ]);

    $entry = decidedEntry($h['audit']->all());
    expect($entry['decided_by'])->toBe('7')
        ->and($entry['decided_via'])->toBe(['channel' => 'telegram', 'channel_user_id' => '42']);
});

it('happy: reject records decided_via channel identity on approval.decided [D-006]', function () {
    $h = ApprovalHelpers::withPending();

    $h['manager']->reject((string) $h['row']['id'], ApprovalHelpers::requester(), 'no', [
        'decided_via' => ['channel' => 'telegram', 'channel_user_id' => '42'],
    ]);

    $entry = decidedEntry($h['audit']->all());
    expect($entry['decision'])->toBe('rejected')
        ->and($entry['decided_via'])->toBe(['channel' => 'telegram', 'channel_user_id' => '42']);
});

it('edge: approval.decided omits decided_via when the surface does not supply one [D-006]', function () {
    $h = ApprovalHelpers::withPending();

    $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester());

    expect(decidedEntry($h['audit']->all()))->not->toHaveKey('decided_via');
});

it('edge: decided_via keeps only string channel fields [D-006]', function () {
    $h = ApprovalHelpers::withPending();

    $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester(), [
        'decided_via' => ['channel' => 'telegram', 'channel_user_id' => 42, 'user_id' => 'forged'],
    ]);

    expect(decidedEntry($h['audit']->all())['decided_via'])->toBe(['channel' => 'telegram']);
});

it('fail: decided_via without a channel is dropped [D-006]', function (mixed $via) {
    $h = ApprovalHelpers::withPending();

    $h['manager']->accept((string) $h['row']['id'], ApprovalHelpers::requester(), ['decided_via' => $via]);

    expect(decidedEntry($h['audit']->all()))->not->toHaveKey('decided_via');
})->with([
    'not an array' => ['telegram'],
    'missing channel' => [['channel_user_id' => '42']],
    'empty channel' => [['channel' => '']],
]);
