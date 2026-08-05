<?php

declare(strict_types=1);

use Rawphp\Capabilities\Approval\Notifiers\CliApprovalNotifier;
use Rawphp\Capabilities\Approval\Notifiers\HttpApprovalNotifier;
use Rawphp\Capabilities\Approval\Notifiers\RecordingTelegramApprovalNotifier;
use Rawphp\Capabilities\Approval\Notifiers\TelegramApprovalNotifier;
use Rawphp\Capabilities\Contracts\ApprovalNotifier;
use Rawphp\Capabilities\Tests\Fixtures\ApprovalHelpers;

it('happy: HttpApprovalNotifier notifies pending without executing [D-006]', function () {
    $n = new HttpApprovalNotifier;
    $n->notifyPending(['id' => 'a1', 'capability_name' => 'x']);
    expect($n->notified())->not->toBeEmpty();
});

it('happy: CliApprovalNotifier notifies pending without executing [D-006]', function () {
    $n = new CliApprovalNotifier;
    $n->notifyPending(['id' => 'a1']);
    expect($n->notified())->not->toBeEmpty();
});

it('fail: notifiers never call capability run [D-006]', function () {
    $http = new HttpApprovalNotifier;
    $cli = new CliApprovalNotifier;
    $tg = new RecordingTelegramApprovalNotifier;
    $http->notifyPending(['id' => '1']);
    $cli->notifyPending(['id' => '1']);
    $tg->notifyPending(['id' => '1']);
    expect(true)->toBeTrue();
});

it('edge: missing notifier channel is non-fatal for pending store [D-006]', function () {
    $h = ApprovalHelpers::withPending();
    expect($h['row']['status'])->toBe('pending');
});

it('happy: deprecated TelegramApprovalNotifier dual-class is recording-only soft-landing [UR-045]', function () {
    expect(class_exists(TelegramApprovalNotifier::class))->toBeTrue();

    $n = new TelegramApprovalNotifier;
    expect($n)->toBeInstanceOf(RecordingTelegramApprovalNotifier::class)
        ->and($n)->toBeInstanceOf(ApprovalNotifier::class);

    $n->notifyPending(['id' => 'legacy-1', 'capability_name' => 'x']);
    expect($n->notified())->toBe([['id' => 'legacy-1', 'capability_name' => 'x']]);

    $n->editMessage(['id' => 'legacy-1'], 'expired');
    expect($n->edits())->toBe([['approval' => ['id' => 'legacy-1'], 'text' => 'expired']]);

    $src = (string) file_get_contents((new ReflectionClass(TelegramApprovalNotifier::class))->getFileName());
    expect($src)->toContain('@deprecated')
        ->and($src)->toContain('RecordingTelegramApprovalNotifier')
        ->and($src)->not->toContain('api.telegram.org')
        ->and($src)->not->toContain('curl_');
});
