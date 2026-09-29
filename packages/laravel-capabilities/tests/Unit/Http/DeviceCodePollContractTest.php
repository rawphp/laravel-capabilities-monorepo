<?php

// C-001: the device-code poll contract between the core auth routes and the Go CLI.
// Unit-only — pure AuthController with the fake issuer.

declare(strict_types=1);

use Rawphp\Capabilities\Adapters\Http\AuthController;
use Rawphp\Capabilities\Contracts\AuthTokenIssuer;
use Rawphp\Capabilities\Http\HttpRequestContext;
use Rawphp\Capabilities\Tests\Fixtures\HttpHelpers;

function devicePoll(AuthController $auth, string $deviceCode = 'host-device-code'): array
{
    return $auth->token(new HttpRequestContext(jsonBody: [
        'grant_type' => AuthTokenIssuer::GRANT_DEVICE_CODE,
        'device_code' => $deviceCode,
        'client_id' => 'capabilities-cli',
    ]))->body;
}

it('device start returns the RFC 8628 shape with an interval the throttled auth routes allow', function () {
    $auth = new AuthController(['enabled' => true], ['enabled' => true], HttpHelpers::fakeAuthTokenIssuer());

    $body = $auth->device(new HttpRequestContext(jsonBody: ['client_id' => 'capabilities-cli']))->body;

    expect($body['ok'])->toBeTrue()
        ->and($body['data'])->toHaveKeys(['device_code', 'user_code', 'verification_uri', 'expires_in', 'interval'])
        ->and($body['data'])->not->toHaveKey('access_token')
        ->and($body['data']['interval'])->toBeGreaterThanOrEqual(10);
});

it('a pending poll is an ok:true envelope carrying data.status, never an error envelope', function () {
    $auth = new AuthController(['enabled' => true], ['enabled' => true], HttpHelpers::fakeAuthTokenIssuer());

    $body = devicePoll($auth);

    expect($body['ok'])->toBeTrue()
        ->and($body['data']['status'])->toBe('authorization_pending')
        ->and($body['data'])->not->toHaveKey('access_token')
        ->and($body['meta']['flow'])->toBe('token');
});

it('every documented poll status round-trips through the token route unchanged', function (string $status) {
    $auth = new AuthController(['enabled' => true], ['enabled' => true], HttpHelpers::fakeAuthTokenIssuer(['device_poll' => ['status' => $status]]));

    expect(devicePoll($auth)['data']['status'])->toBe($status)
        ->and(AuthTokenIssuer::DEVICE_POLL_STATUSES)->toContain($status);
})->with(['authorization_pending', 'slow_down', 'access_denied', 'expired_token']);

it('the RFC error key is also passed through inside the ok envelope', function () {
    $auth = new AuthController(['enabled' => true], ['enabled' => true], HttpHelpers::fakeAuthTokenIssuer(['device_poll' => ['error' => 'slow_down']]));

    $body = devicePoll($auth);

    expect($body['ok'])->toBeTrue()->and($body['data']['error'])->toBe('slow_down');
});

it('an approved poll returns the token shape and completes the flow', function () {
    $auth = new AuthController(['enabled' => true], ['enabled' => true], HttpHelpers::fakeAuthTokenIssuer([
        'device_poll' => ['token_type' => 'Bearer', 'access_token' => 'device-tok', 'expires_in' => 3600],
    ]));

    $body = devicePoll($auth);

    expect($body['ok'])->toBeTrue()
        ->and($body['data']['access_token'])->toBe('device-tok')
        ->and($body['data'])->not->toHaveKey('status');
});

it('the grant constant matches the CLI wire value', function () {
    expect(AuthTokenIssuer::GRANT_DEVICE_CODE)->toBe('urn:ietf:params:oauth:grant-type:device_code');
});
