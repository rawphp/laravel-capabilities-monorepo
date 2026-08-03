<?php

declare(strict_types=1);

use Rawphp\CapabilitiesMessaging\Telegram\TelegramAdapter;
use Rawphp\CapabilitiesMessaging\Tests\Fixtures\FakeCapabilityBus;
use Rawphp\CapabilitiesMessaging\Tests\Fixtures\MessagingHelpers as H;

it('happy: tool may run via registry when linked=True profile_ok=True tool_in_profile=True [MSG-003]', function () {
    $linked = true;
    $profileOk = true;
    $toolIn = true;
    $identity = H::identity();
    if ($linked) {
        $identity->link('42', 'u1');
    }
    $registry = new FakeCapabilityBus;
    $cfg = H::config(['agent_profile' => $profileOk ? 'support' : '']);
    $tools = $toolIn ? ['support.ping'] : [];
    $adapter = new TelegramAdapter(H::bot(), static function () use ($toolIn) {
        return [
            'text' => 'x',
            'tool_calls' => $toolIn
                ? [['name' => 'support.ping', 'input' => []]]
                : [['name' => 'outside.profile', 'input' => []]],
        ];
    });
    $p = H::processor([
        'config' => $cfg,
        'identity' => $identity,
        'registry' => $registry,
        'adapter' => $adapter,
        'profile_tools' => $tools,
    ]);
    $r = $p->runPipeline(H::telegramUpdate(userId: 42));
    if ($linked && $profileOk && $toolIn) {
        expect($r['ok'])->toBeTrue();
        expect($registry->invokeCount())->toBe(1);
    } elseif (! $linked) {
        expect($r['ok'])->toBeFalse();
        expect($registry->invokeCount())->toBe(0);
    } else {
        expect($r['ok'])->toBeFalse();
        expect($registry->invokeCount())->toBe(0);
    }
});

it('fail: tool blocked when linked=True profile_ok=True tool_in_profile=False [P2-007]', function () {
    $linked = true;
    $profileOk = true;
    $toolIn = false;
    $identity = H::identity();
    if ($linked) {
        $identity->link('42', 'u1');
    }
    $registry = new FakeCapabilityBus;
    $cfg = H::config(['agent_profile' => $profileOk ? 'support' : '']);
    $tools = $toolIn ? ['support.ping'] : [];
    $adapter = new TelegramAdapter(H::bot(), static function () use ($toolIn) {
        return [
            'text' => 'x',
            'tool_calls' => $toolIn
                ? [['name' => 'support.ping', 'input' => []]]
                : [['name' => 'outside.profile', 'input' => []]],
        ];
    });
    $p = H::processor([
        'config' => $cfg,
        'identity' => $identity,
        'registry' => $registry,
        'adapter' => $adapter,
        'profile_tools' => $tools,
    ]);
    $r = $p->runPipeline(H::telegramUpdate(userId: 42));
    if ($linked && $profileOk && $toolIn) {
        expect($r['ok'])->toBeTrue();
        expect($registry->invokeCount())->toBe(1);
    } elseif (! $linked) {
        expect($r['ok'])->toBeFalse();
        expect($registry->invokeCount())->toBe(0);
    } else {
        expect($r['ok'])->toBeFalse();
        expect($registry->invokeCount())->toBe(0);
    }
});

it('fail: fail loud when linked=True profile_ok=False tool_in_profile=True [D-008]', function () {
    $linked = true;
    $profileOk = false;
    $toolIn = true;
    $identity = H::identity();
    if ($linked) {
        $identity->link('42', 'u1');
    }
    $registry = new FakeCapabilityBus;
    $cfg = H::config(['agent_profile' => $profileOk ? 'support' : '']);
    $tools = $toolIn ? ['support.ping'] : [];
    $adapter = new TelegramAdapter(H::bot(), static function () use ($toolIn) {
        return [
            'text' => 'x',
            'tool_calls' => $toolIn
                ? [['name' => 'support.ping', 'input' => []]]
                : [['name' => 'outside.profile', 'input' => []]],
        ];
    });
    $p = H::processor([
        'config' => $cfg,
        'identity' => $identity,
        'registry' => $registry,
        'adapter' => $adapter,
        'profile_tools' => $tools,
    ]);
    $r = $p->runPipeline(H::telegramUpdate(userId: 42));
    if ($linked && $profileOk && $toolIn) {
        expect($r['ok'])->toBeTrue();
        expect($registry->invokeCount())->toBe(1);
    } elseif (! $linked) {
        expect($r['ok'])->toBeFalse();
        expect($registry->invokeCount())->toBe(0);
    } else {
        expect($r['ok'])->toBeFalse();
        expect($registry->invokeCount())->toBe(0);
    }
});

it('fail: fail loud when linked=True profile_ok=False tool_in_profile=False [D-008]', function () {
    $linked = true;
    $profileOk = false;
    $toolIn = false;
    $identity = H::identity();
    if ($linked) {
        $identity->link('42', 'u1');
    }
    $registry = new FakeCapabilityBus;
    $cfg = H::config(['agent_profile' => $profileOk ? 'support' : '']);
    $tools = $toolIn ? ['support.ping'] : [];
    $adapter = new TelegramAdapter(H::bot(), static function () use ($toolIn) {
        return [
            'text' => 'x',
            'tool_calls' => $toolIn
                ? [['name' => 'support.ping', 'input' => []]]
                : [['name' => 'outside.profile', 'input' => []]],
        ];
    });
    $p = H::processor([
        'config' => $cfg,
        'identity' => $identity,
        'registry' => $registry,
        'adapter' => $adapter,
        'profile_tools' => $tools,
    ]);
    $r = $p->runPipeline(H::telegramUpdate(userId: 42));
    if ($linked && $profileOk && $toolIn) {
        expect($r['ok'])->toBeTrue();
        expect($registry->invokeCount())->toBe(1);
    } elseif (! $linked) {
        expect($r['ok'])->toBeFalse();
        expect($registry->invokeCount())->toBe(0);
    } else {
        expect($r['ok'])->toBeFalse();
        expect($registry->invokeCount())->toBe(0);
    }
});

it('fail: no tools when linked=False profile_ok=True tool_in_profile=True [MSG-002]', function () {
    $linked = false;
    $profileOk = true;
    $toolIn = true;
    $identity = H::identity();
    if ($linked) {
        $identity->link('42', 'u1');
    }
    $registry = new FakeCapabilityBus;
    $cfg = H::config(['agent_profile' => $profileOk ? 'support' : '']);
    $tools = $toolIn ? ['support.ping'] : [];
    $adapter = new TelegramAdapter(H::bot(), static function () use ($toolIn) {
        return [
            'text' => 'x',
            'tool_calls' => $toolIn
                ? [['name' => 'support.ping', 'input' => []]]
                : [['name' => 'outside.profile', 'input' => []]],
        ];
    });
    $p = H::processor([
        'config' => $cfg,
        'identity' => $identity,
        'registry' => $registry,
        'adapter' => $adapter,
        'profile_tools' => $tools,
    ]);
    $r = $p->runPipeline(H::telegramUpdate(userId: 42));
    if ($linked && $profileOk && $toolIn) {
        expect($r['ok'])->toBeTrue();
        expect($registry->invokeCount())->toBe(1);
    } elseif (! $linked) {
        expect($r['ok'])->toBeFalse();
        expect($registry->invokeCount())->toBe(0);
    } else {
        expect($r['ok'])->toBeFalse();
        expect($registry->invokeCount())->toBe(0);
    }
});

it('fail: no tools when linked=False profile_ok=True tool_in_profile=False [MSG-002]', function () {
    $linked = false;
    $profileOk = true;
    $toolIn = false;
    $identity = H::identity();
    if ($linked) {
        $identity->link('42', 'u1');
    }
    $registry = new FakeCapabilityBus;
    $cfg = H::config(['agent_profile' => $profileOk ? 'support' : '']);
    $tools = $toolIn ? ['support.ping'] : [];
    $adapter = new TelegramAdapter(H::bot(), static function () use ($toolIn) {
        return [
            'text' => 'x',
            'tool_calls' => $toolIn
                ? [['name' => 'support.ping', 'input' => []]]
                : [['name' => 'outside.profile', 'input' => []]],
        ];
    });
    $p = H::processor([
        'config' => $cfg,
        'identity' => $identity,
        'registry' => $registry,
        'adapter' => $adapter,
        'profile_tools' => $tools,
    ]);
    $r = $p->runPipeline(H::telegramUpdate(userId: 42));
    if ($linked && $profileOk && $toolIn) {
        expect($r['ok'])->toBeTrue();
        expect($registry->invokeCount())->toBe(1);
    } elseif (! $linked) {
        expect($r['ok'])->toBeFalse();
        expect($registry->invokeCount())->toBe(0);
    } else {
        expect($r['ok'])->toBeFalse();
        expect($registry->invokeCount())->toBe(0);
    }
});

it('fail: no tools when linked=False profile_ok=False tool_in_profile=True [MSG-002]', function () {
    $linked = false;
    $profileOk = false;
    $toolIn = true;
    $identity = H::identity();
    if ($linked) {
        $identity->link('42', 'u1');
    }
    $registry = new FakeCapabilityBus;
    $cfg = H::config(['agent_profile' => $profileOk ? 'support' : '']);
    $tools = $toolIn ? ['support.ping'] : [];
    $adapter = new TelegramAdapter(H::bot(), static function () use ($toolIn) {
        return [
            'text' => 'x',
            'tool_calls' => $toolIn
                ? [['name' => 'support.ping', 'input' => []]]
                : [['name' => 'outside.profile', 'input' => []]],
        ];
    });
    $p = H::processor([
        'config' => $cfg,
        'identity' => $identity,
        'registry' => $registry,
        'adapter' => $adapter,
        'profile_tools' => $tools,
    ]);
    $r = $p->runPipeline(H::telegramUpdate(userId: 42));
    if ($linked && $profileOk && $toolIn) {
        expect($r['ok'])->toBeTrue();
        expect($registry->invokeCount())->toBe(1);
    } elseif (! $linked) {
        expect($r['ok'])->toBeFalse();
        expect($registry->invokeCount())->toBe(0);
    } else {
        expect($r['ok'])->toBeFalse();
        expect($registry->invokeCount())->toBe(0);
    }
});

it('fail: no tools when linked=False profile_ok=False tool_in_profile=False [MSG-002]', function () {
    $linked = false;
    $profileOk = false;
    $toolIn = false;
    $identity = H::identity();
    if ($linked) {
        $identity->link('42', 'u1');
    }
    $registry = new FakeCapabilityBus;
    $cfg = H::config(['agent_profile' => $profileOk ? 'support' : '']);
    $tools = $toolIn ? ['support.ping'] : [];
    $adapter = new TelegramAdapter(H::bot(), static function () use ($toolIn) {
        return [
            'text' => 'x',
            'tool_calls' => $toolIn
                ? [['name' => 'support.ping', 'input' => []]]
                : [['name' => 'outside.profile', 'input' => []]],
        ];
    });
    $p = H::processor([
        'config' => $cfg,
        'identity' => $identity,
        'registry' => $registry,
        'adapter' => $adapter,
        'profile_tools' => $tools,
    ]);
    $r = $p->runPipeline(H::telegramUpdate(userId: 42));
    if ($linked && $profileOk && $toolIn) {
        expect($r['ok'])->toBeTrue();
        expect($registry->invokeCount())->toBe(1);
    } elseif (! $linked) {
        expect($r['ok'])->toBeFalse();
        expect($registry->invokeCount())->toBe(0);
    } else {
        expect($r['ok'])->toBeFalse();
        expect($registry->invokeCount())->toBe(0);
    }
});
