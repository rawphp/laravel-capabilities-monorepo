<?php

declare(strict_types=1);

use Rawphp\Capabilities\Adapters\Mcp\McpCredential;
use Rawphp\Capabilities\Support\SystemActor;
use Rawphp\Capabilities\Tests\Fixtures\AdapterHelpers;

/**
 * Matrix: auth profile × allow_integration × allowSystemCallers.
 */

function matrix_case(string $profile, bool $allowInt, bool|array $allowSys): array
{
    $h = AdapterHelpers::harness([
        'mcp_auth' => [
            'allow_integration_credentials' => $allowInt,
            'integration_actors' => ['mcp-billing-service' => 'billing-bot'],
        ],
        'caps' => [[
            'name' => 'create-invoice',
            'groups' => ['billing'],
            'allowSystemCallers' => $allowSys,
        ]],
    ]);
    $h['mcp']->register('billing');

    $cred = match ($profile) {
        'user_pat' => McpCredential::userPat($h['user']),
        'integration' => McpCredential::integration('mcp-billing-service'),
        'user_delegated' => McpCredential::userDelegated($h['user'], 'cursor-mcp'),
    };

    $result = $h['mcp']->handle('create-invoice', AdapterHelpers::input(), $cred, [
        'profile' => 'billing',
    ]);

    return ['h' => $h, 'result' => $result, 'profile' => $profile, 'allowInt' => $allowInt, 'allowSys' => $allowSys];
}

it('edge: auth path when profile=user_pat allow_int=True allow_sys=True [D-023]', function () {
    $c = matrix_case('user_pat', true, true);
    expect($c['result']->isOk())->toBeTrue();
});

it('edge: auth path when profile=user_pat allow_int=True allow_sys=False [D-023]', function () {
    $c = matrix_case('user_pat', true, false);
    expect($c['result']->isOk())->toBeTrue(); // user path ignores allowSystemCallers
});

it('edge: auth path when profile=user_pat allow_int=True allow_sys=list [D-023]', function () {
    $c = matrix_case('user_pat', true, ['billing-bot']);
    expect($c['result']->isOk())->toBeTrue();
});

it('edge: auth path when profile=user_pat allow_int=False allow_sys=True [D-023]', function () {
    $c = matrix_case('user_pat', false, true);
    expect($c['result']->isOk())->toBeTrue();
});

it('edge: auth path when profile=user_pat allow_int=False allow_sys=False [D-023]', function () {
    $c = matrix_case('user_pat', false, false);
    expect($c['result']->isOk())->toBeTrue();
});

it('edge: auth path when profile=user_pat allow_int=False allow_sys=list [D-023]', function () {
    $c = matrix_case('user_pat', false, ['billing-bot']);
    expect($c['result']->isOk())->toBeTrue();
});

it('edge: auth path when profile=integration allow_int=True allow_sys=True [D-023]', function () {
    $c = matrix_case('integration', true, true);
    expect($c['result']->isOk())->toBeTrue()
        ->and($c['h']['registry']->lastState()?->context?->actor())->toBeInstanceOf(SystemActor::class);
});

it('fail: integration system denied when profile=integration allow_int=True allow_sys=False [D-023]', function () {
    $c = matrix_case('integration', true, false);
    expect($c['result']->isOk())->toBeFalse()->and($c['result']->errorCode())->toBe('forbidden');
});

it('edge: auth path when profile=integration allow_int=True allow_sys=list [D-023]', function () {
    $c = matrix_case('integration', true, ['billing-bot']);
    expect($c['result']->isOk())->toBeTrue();
});

it('fail: integration denied when profile=integration allow_int=False allow_sys=True [D-023]', function () {
    $c = matrix_case('integration', false, true);
    expect($c['result']->isOk())->toBeFalse();
});

it('fail: integration denied when profile=integration allow_int=False allow_sys=False [D-023]', function () {
    $c = matrix_case('integration', false, false);
    expect($c['result']->isOk())->toBeFalse();
});

it('fail: integration denied when profile=integration allow_int=False allow_sys=list [D-023]', function () {
    $c = matrix_case('integration', false, ['billing-bot']);
    expect($c['result']->isOk())->toBeFalse();
});

it('edge: auth path when profile=user_delegated allow_int=True allow_sys=True [D-023]', function () {
    $c = matrix_case('user_delegated', true, true);
    expect($c['result']->isOk())->toBeTrue()
        ->and($c['h']['registry']->lastState()?->context?->mcp()['auth_profile'] ?? null)->toBe('user_delegated');
});

it('edge: auth path when profile=user_delegated allow_int=True allow_sys=False [D-023]', function () {
    $c = matrix_case('user_delegated', true, false);
    expect($c['result']->isOk())->toBeTrue();
});

it('edge: auth path when profile=user_delegated allow_int=True allow_sys=list [D-023]', function () {
    $c = matrix_case('user_delegated', true, ['billing-bot']);
    expect($c['result']->isOk())->toBeTrue();
});

it('edge: auth path when profile=user_delegated allow_int=False allow_sys=True [D-023]', function () {
    $c = matrix_case('user_delegated', false, true);
    expect($c['result']->isOk())->toBeTrue();
});

it('edge: auth path when profile=user_delegated allow_int=False allow_sys=False [D-023]', function () {
    $c = matrix_case('user_delegated', false, false);
    expect($c['result']->isOk())->toBeTrue();
});

it('edge: auth path when profile=user_delegated allow_int=False allow_sys=list [D-023]', function () {
    $c = matrix_case('user_delegated', false, ['billing-bot']);
    expect($c['result']->isOk())->toBeTrue();
});
