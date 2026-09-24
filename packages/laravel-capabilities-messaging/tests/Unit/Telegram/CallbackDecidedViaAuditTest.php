<?php

declare(strict_types=1);

use Rawphp\Capabilities\Support\FixedClock;
use Rawphp\Capabilities\Support\InMemoryAuditWriter;
use Rawphp\CapabilitiesMessaging\Tests\Fixtures\MessagingHelpers as H;

it('happy: telegram callback audits the approving telegram user on approval.decided [D-006]', function (string $action, string $decision) {
    $audit = new InMemoryAuditWriter(new FixedClock(new DateTimeImmutable('2026-01-15T12:00:00Z')));
    $approvals = H::approvals()->withAudit($audit);
    $approvals->request([
        'id' => "via-{$action}",
        'capability_name' => 'x',
        'requester_actor_type' => 'user',
        'requester_actor_id' => 'u1',
        'original_caller' => 'http',
        'input_json' => [],
    ]);
    $identity = H::identity();
    $identity->link('42', 'u1');

    $r = H::callbackHandler($identity, $approvals)->handle(H::signer()->sign("via-{$action}", $action), ['id' => 42]);

    $decided = array_values(array_filter($audit->all(), fn (array $e) => $e['event'] === 'approval.decided'));
    expect($r['status'])->toBe('ok')
        ->and($decided)->toHaveCount(1)
        ->and($decided[0]['decision'])->toBe($decision)
        ->and($decided[0]['decided_by'])->toBe('u1')
        ->and($decided[0]['decided_via'])->toBe(['channel' => 'telegram', 'channel_user_id' => '42']);
})->with([
    'accept' => ['accept', 'approved'],
    'reject' => ['reject', 'rejected'],
]);
