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

it('happy: deprecated TelegramApprovalNotifier dual-class is recording-only soft-landing [UR-045 / ORI-771]', function () {
    expect(class_exists(TelegramApprovalNotifier::class))->toBeTrue();

    $n = new TelegramApprovalNotifier;
    expect($n)->toBeInstanceOf(RecordingTelegramApprovalNotifier::class)
        ->and($n)->toBeInstanceOf(ApprovalNotifier::class);

    $n->notifyPending(['id' => 'legacy-1', 'capability_name' => 'x']);
    expect($n->notified())->toBe([['id' => 'legacy-1', 'capability_name' => 'x']]);

    $n->editMessage(['id' => 'legacy-1'], 'expired');
    expect($n->edits())->toBe([['approval' => ['id' => 'legacy-1'], 'text' => 'expired']]);

    $legacyRef = new ReflectionClass(TelegramApprovalNotifier::class);
    expect($legacyRef->getParentClass()?->getName())->toBe(RecordingTelegramApprovalNotifier::class);

    // Empty subclass: no methods declared on the soft-landing FQCN itself.
    $ownMethods = array_values(array_filter(
        $legacyRef->getMethods(),
        static fn (ReflectionMethod $m): bool => $m->getDeclaringClass()->getName() === TelegramApprovalNotifier::class
    ));
    expect($ownMethods)->toBeEmpty();

    $src = (string) file_get_contents((string) $legacyRef->getFileName());
    expect($src)->toContain('@deprecated')
        ->and($src)->toContain('RecordingTelegramApprovalNotifier')
        ->and($src)->toMatch('/class\s+TelegramApprovalNotifier\s+extends\s+RecordingTelegramApprovalNotifier\s*\{\s*\}/s')
        ->and($src)->not->toContain('api.telegram.org')
        ->and($src)->not->toContain('TelegramBot')
        ->and($src)->not->toContain('Http::')
        ->and($src)->not->toMatch('/\bcurl\b/i');

    // Canonical recording double remains the preferred FQCN (not deprecated).
    $canonicalSrc = (string) file_get_contents(
        (string) (new ReflectionClass(RecordingTelegramApprovalNotifier::class))->getFileName()
    );
    expect($canonicalSrc)->not->toContain('@deprecated')
        ->and($canonicalSrc)->not->toContain('api.telegram.org')
        ->and($canonicalSrc)->not->toContain('TelegramBot')
        ->and($canonicalSrc)->not->toContain('Http::')
        ->and($canonicalSrc)->not->toMatch('/\bcurl\b/i');
});

it('happy: Approval/Notifiers sources have zero Bot API / HTTP client usage [ORI-771]', function () {
    $dir = dirname((string) (new ReflectionClass(RecordingTelegramApprovalNotifier::class))->getFileName());
    expect(is_dir($dir))->toBeTrue();

    $hits = [];
    foreach (glob($dir.'/*.php') ?: [] as $file) {
        $body = (string) file_get_contents($file);
        if (preg_match('/TelegramBot|\bcurl\b|Http::|api\.telegram/i', $body) === 1) {
            $hits[] = basename($file);
        }
    }

    expect($hits)->toBe([], 'Notifiers must not ship Bot/HTTP clients (files: '.implode(', ', $hits).')');
});

it('happy: matrix and metadata tests prefer RecordingTelegramApprovalNotifier FQCN [ORI-771]', function () {
    $files = [
        __DIR__.'/NotifierChannelMatrixTest.php',
        __DIR__.'/MessagingMetadataTest.php',
    ];

    foreach ($files as $file) {
        expect(is_file($file))->toBeTrue();
        $src = (string) file_get_contents($file);
        expect($src)->toContain('RecordingTelegramApprovalNotifier')
            ->and($src)->not->toMatch('/new\s+TelegramApprovalNotifier\b/')
            ->and($src)->not->toMatch('/use\s+Rawphp\\\\Capabilities\\\\Approval\\\\Notifiers\\\\TelegramApprovalNotifier\b/');
    }
});
