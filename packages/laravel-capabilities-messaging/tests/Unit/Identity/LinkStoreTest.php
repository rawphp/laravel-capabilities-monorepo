<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Rawphp\CapabilitiesMessaging\Identity\CacheLinkStore;
use Rawphp\CapabilitiesMessaging\Identity\IdentityLinker;
use Rawphp\CapabilitiesMessaging\Identity\InMemoryLinkStore;
use Rawphp\CapabilitiesMessaging\Identity\LinkStore;
use Rawphp\CapabilitiesMessaging\Tests\Fixtures\InMemoryUserModel;
use Rawphp\CapabilitiesMessaging\Tests\Fixtures\MessagingHelpers as H;

/**
 * Link codes and identity links outlive the process (MSG-002 / L-006): a code issued in the web
 * process must bind on the queue worker, and a bound link must survive a worker restart. The
 * container binds a cache-backed LinkStore; each "process" below is a fresh IdentityLinker over
 * the same shared cache repository (array store — in memory, no DB).
 */
function linkerOver(Repository $cache, array $config = []): IdentityLinker
{
    return new IdentityLinker(H::config($config), store: new CacheLinkStore($cache));
}

afterEach(function () {
    Carbon::setTestNow();
});

it('happy: a code issued in the web process binds on a separate worker process [MSG-002]', function () {
    $cache = new Repository(new ArrayStore);
    $code = linkerOver($cache)->issueLinkCode('user-9', 'tenant-a');

    $user = linkerOver($cache)->bindWithCode('tg-1', $code);

    expect($user)->not->toBeNull()
        ->and($user->id)->toBe('user-9')
        ->and($user->tenantId)->toBe('tenant-a');
});

it('happy: a bound link survives a worker restart [MSG-002]', function () {
    $cache = new Repository(new ArrayStore);
    $worker = linkerOver($cache);
    $worker->bindWithCode('tg-1', linkerOver($cache)->issueLinkCode('user-9', 'tenant-a'));

    $restarted = linkerOver($cache);

    expect($restarted->isLinked('tg-1'))->toBeTrue()
        ->and($restarted->resolve(['telegram_user_id' => 'tg-1'])?->id)->toBe('user-9');
});

it('happy: an explicit link is shared across processes [MSG-002]', function () {
    $cache = new Repository(new ArrayStore);
    linkerOver($cache)->link('42', 'user-1', 'tenant-a');

    expect(linkerOver($cache)->resolve(['telegram_user_id' => '42'])?->id)->toBe('user-1');
});

it('fail: a code is single-use across processes [MSG-002]', function () {
    $cache = new Repository(new ArrayStore);
    $code = linkerOver($cache)->issueLinkCode('user-9');

    expect(linkerOver($cache)->bindWithCode('tg-1', $code))->not->toBeNull()
        ->and(linkerOver($cache)->bindWithCode('tg-2', $code))->toBeNull()
        ->and(linkerOver($cache)->isLinked('tg-2'))->toBeFalse();
});

it('fail: two workers racing on one code bind at most once [MSG-002]', function () {
    // Both workers read the code before either forgets it: the claim marker still refuses the loser.
    $cache = new class(new ArrayStore) extends Repository
    {
        public function forget($key): bool
        {
            return true;
        }
    };
    $code = linkerOver($cache)->issueLinkCode('user-9');

    expect(linkerOver($cache)->bindWithCode('tg-1', $code))->not->toBeNull()
        ->and(linkerOver($cache)->bindWithCode('tg-2', $code))->toBeNull();
});

it('fail: a code expires with identity.code_ttl_seconds in the shared cache [MSG-002]', function () {
    $cache = new Repository(new ArrayStore);
    Carbon::setTestNow(Carbon::createFromTimestamp(1_700_000_000));
    $code = linkerOver($cache, ['identity' => ['code_ttl_seconds' => 10]])->issueLinkCode('user-9');

    Carbon::setTestNow(Carbon::createFromTimestamp(1_700_000_011));

    expect((new CacheLinkStore($cache))->takeCode($code))->toBeNull();
});

it('fail: an expired code is refused even if the cache still holds it [MSG-002]', function () {
    $cache = new Repository(new ArrayStore);
    $now = 1_700_000_000;
    $code = linkerOver($cache, ['identity' => ['code_ttl_seconds' => 10]])->issueLinkCode('user-9', null, $now);

    expect(linkerOver($cache)->bindWithCode('tg-1', $code, $now + 11))->toBeNull()
        ->and(linkerOver($cache)->isLinked('tg-1'))->toBeFalse();
});

it('fail: unknown codes and malformed cache entries fail closed [MSG-002]', function () {
    $cache = new Repository(new ArrayStore);
    $store = new CacheLinkStore($cache);
    $cache->forever(CacheLinkStore::PREFIX.'link:tg-bad', 'not-a-link');
    $cache->put(CacheLinkStore::PREFIX.'code:bad', 'not-a-code', 60);

    expect($store->takeCode('missing'))->toBeNull()
        ->and($store->takeCode('bad'))->toBeNull()
        ->and($store->findLink('tg-bad'))->toBeNull()
        ->and($store->findLink('tg-none'))->toBeNull()
        ->and(linkerOver($cache)->resolve(['telegram_user_id' => 'tg-bad']))->toBeNull();
});

it('edge: allowlist entries stay in config and are never written to the store [MSG-002]', function () {
    $cache = new Repository(new ArrayStore);
    $allowlisted = ['identity' => ['mode' => 'allowlist', 'allowlist' => [
        ['telegram_user_id' => 'tg-al', 'laravel_user_id' => 'user-al'],
    ]]];

    expect(linkerOver($cache, $allowlisted)->resolve(['telegram_user_id' => 'tg-al'])?->id)->toBe('user-al')
        ->and($cache->get(CacheLinkStore::PREFIX.'link:tg-al'))->toBeNull()
        ->and(linkerOver($cache, ['identity' => ['mode' => 'allowlist']])->isLinked('tg-al'))->toBeFalse();
});

it('edge: a code-bound link takes precedence over an allowlist entry [MSG-002]', function () {
    $linker = new IdentityLinker(H::config(['identity' => ['allowlist' => [
        ['telegram_user_id' => 'tg-1', 'laravel_user_id' => 'user-al'],
    ]]]));
    $linker->bindWithCode('tg-1', $linker->issueLinkCode('user-code'));

    expect($linker->resolve(['telegram_user_id' => 'tg-1'])?->id)->toBe('user-code');
});

it('happy: the in-memory store keeps codes single-use for unit tests [MSG-002]', function () {
    $store = new InMemoryLinkStore;
    $store->putCode('c1', ['user_id' => 'u1', 'tenant_id' => null, 'exp' => 10], 10);
    $store->putLink('tg-1', ['user_id' => 'u1', 'tenant_id' => null, 'telegram_user_id' => 'tg-1']);

    expect($store)->toBeInstanceOf(LinkStore::class)
        ->and($store->takeCode('c1'))->toBe(['user_id' => 'u1', 'tenant_id' => null, 'exp' => 10])
        ->and($store->takeCode('c1'))->toBeNull()
        ->and($store->findLink('tg-1')['user_id'])->toBe('u1')
        ->and($store->findLink('tg-2'))->toBeNull();
});

it('happy: the container binds a cache-backed LinkStore shared by web and worker [MSG-002]', function () {
    InMemoryUserModel::seed(['u1']);
    $cache = new Repository(new ArrayStore);
    $web = H::container(['user_model' => InMemoryUserModel::class], cache: $cache);
    $worker = H::container(['user_model' => InMemoryUserModel::class], cache: $cache);

    $code = $web->make(IdentityLinker::class)->issueLinkCode('u1');
    $worker->make(IdentityLinker::class)->bindWithCode('tg-1', $code);

    expect($web->make(LinkStore::class))->toBeInstanceOf(CacheLinkStore::class)
        ->and($web->make(IdentityLinker::class)->isLinked('tg-1'))->toBeTrue();
});

it('fail: switching identity.mode to allowlist stops code-bound links resolving [MSG-002]', function () {
    $cache = new Repository(new ArrayStore);
    $codeLink = linkerOver($cache);
    $codeLink->bindWithCode('tg-1', $codeLink->issueLinkCode('user-code'));
    $codeLink->link('tg-2', 'user-admin');

    $allowlist = linkerOver($cache, ['identity' => ['mode' => 'allowlist', 'allowlist' => [
        ['telegram_user_id' => 'tg-al', 'laravel_user_id' => 'user-al'],
    ]]]);

    expect($allowlist->resolve(['telegram_user_id' => 'tg-1']))->toBeNull()
        ->and($allowlist->resolve(['telegram_user_id' => 'tg-2']))->toBeNull()
        ->and($allowlist->isLinked('tg-1'))->toBeFalse()
        ->and($allowlist->resolve(['telegram_user_id' => 'tg-al'])?->id)->toBe('user-al');
});

it('fail: an allowlisted telegram user resolves to the allowlist entry, not an old code link [MSG-002]', function () {
    $cache = new Repository(new ArrayStore);
    linkerOver($cache)->link('tg-1', 'user-code');

    $allowlist = linkerOver($cache, ['identity' => ['mode' => 'allowlist', 'allowlist' => [
        ['telegram_user_id' => 'tg-1', 'laravel_user_id' => 'user-al'],
    ]]]);

    expect($allowlist->resolve(['telegram_user_id' => 'tg-1'])?->id)->toBe('user-al');
});

it('fail: explicit link is refused outside code_link mode [MSG-002]', function () {
    $cache = new Repository(new ArrayStore);

    expect(fn () => linkerOver($cache, ['identity' => ['mode' => 'allowlist']])->link('tg-1', 'user-1'))
        ->toThrow(RuntimeException::class, 'code_link');
    expect($cache->get(CacheLinkStore::PREFIX.'link:tg-1'))->toBeNull();
});

/**
 * Revocation (M-106): a code-bound link is a durable credential, so one link can be revoked
 * without touching other users, by Telegram id or by product user id.
 */
dataset('link stores', [
    'cache' => [fn () => new CacheLinkStore(new Repository(new ArrayStore))],
    'in-memory' => [fn () => new InMemoryLinkStore],
]);

it('happy: unlink revokes one telegram account and leaves other links [MSG-002]', function (LinkStore $store) {
    $linker = new IdentityLinker(H::config(), store: $store);
    $linker->link('tg-1', 'user-1', 'tenant-a');
    $linker->link('tg-2', 'user-2', 'tenant-a');

    $linker->unlink('tg-1');

    expect($linker->resolve(['telegram_user_id' => 'tg-1']))->toBeNull()
        ->and($linker->isLinked('tg-1'))->toBeFalse()
        ->and($linker->resolve(['telegram_user_id' => 'tg-2'])?->id)->toBe('user-2')
        ->and($store->findTelegramUserId('user-1', 'tenant-a'))->toBeNull();
})->with('link stores');

it('happy: unlinkUser revokes by product user id [MSG-002]', function (LinkStore $store) {
    $linker = new IdentityLinker(H::config(), store: $store);
    $linker->bindWithCode('tg-1', $linker->issueLinkCode('user-1', 'tenant-a'));
    $linker->link('tg-2', 'user-2', 'tenant-a');

    $linker->unlinkUser('user-1', 'tenant-a');
    $linker->unlinkUser('user-unknown');

    expect($linker->isLinked('tg-1'))->toBeFalse()
        ->and($linker->isLinked('tg-2'))->toBeTrue();
})->with('link stores');

it('fail: linking a user from another telegram account revokes the old account [MSG-002]', function (LinkStore $store) {
    $linker = new IdentityLinker(H::config(), store: $store);
    $linker->link('tg-old', 'user-1', 'tenant-a');

    $linker->bindWithCode('tg-new', $linker->issueLinkCode('user-1', 'tenant-a'));

    expect($linker->isLinked('tg-old'))->toBeFalse()
        ->and($linker->resolve(['telegram_user_id' => 'tg-new'])?->id)->toBe('user-1')
        ->and($store->findTelegramUserId('user-1', 'tenant-a'))->toBe('tg-new');
})->with('link stores');

it('edge: the same product id in another tenant is a separate link [MSG-002]', function (LinkStore $store) {
    $linker = new IdentityLinker(H::config(), store: $store);
    $linker->link('tg-a', 'user-1', 'tenant-a');
    $linker->link('tg-b', 'user-1', 'tenant-b');

    $linker->unlinkUser('user-1', 'tenant-a');

    expect($linker->isLinked('tg-a'))->toBeFalse()
        ->and($linker->isLinked('tg-b'))->toBeTrue();
})->with('link stores');

it('edge: a telegram account moved to another user is not revoked by unlinking the old user [MSG-002]', function (LinkStore $store) {
    $linker = new IdentityLinker(H::config(), store: $store);
    $linker->link('tg-1', 'user-old');
    $linker->link('tg-1', 'user-new');

    $linker->unlinkUser('user-old');

    expect($linker->resolve(['telegram_user_id' => 'tg-1'])?->id)->toBe('user-new')
        ->and($store->findTelegramUserId('user-old', null))->toBeNull();
})->with('link stores');

it('edge: unlink is shared across processes and works in any identity mode [MSG-002]', function () {
    $cache = new Repository(new ArrayStore);
    linkerOver($cache)->link('tg-1', 'user-1');
    linkerOver($cache)->link('tg-al', 'user-al');
    $allowlisted = ['identity' => ['mode' => 'allowlist', 'allowlist' => [
        ['telegram_user_id' => 'tg-al', 'laravel_user_id' => 'user-al'],
    ]]];

    // Revoked while the install runs allowlist mode: stays revoked after switching back.
    linkerOver($cache, $allowlisted)->unlink('tg-1');
    linkerOver($cache, $allowlisted)->unlink('tg-al');

    expect(linkerOver($cache)->isLinked('tg-1'))->toBeFalse()
        ->and(linkerOver($cache)->isLinked('tg-al'))->toBeFalse()
        // Allowlist entries live in config: unlink cannot remove them.
        ->and(linkerOver($cache, $allowlisted)->resolve(['telegram_user_id' => 'tg-al'])?->id)->toBe('user-al');
});

it('fail: malformed reverse-index entries fail closed [MSG-002]', function () {
    $cache = new Repository(new ArrayStore);
    $store = new CacheLinkStore($cache);
    $cache->forever(CacheLinkStore::PREFIX.'user:'.rawurlencode('').':'.rawurlencode('user-1'), ['not-an-id']);

    $store->forgetLink('tg-none');

    expect($store->findTelegramUserId('user-1', null))->toBeNull();
});
