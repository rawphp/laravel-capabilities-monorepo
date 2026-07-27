<?php

// Spec-derived unit tests for D-005 key format matrix. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Idempotency\IdempotencyKey;

it('happy: key format accepts single_char_a [D-005]', function () {
    expect(IdempotencyKey::isValid('a'))->toBeTrue()
        ->and(IdempotencyKey::validate('a'))->toBe('a');
});

it('happy: key format accepts len128 [D-005]', function () {
    $key = str_repeat('x', 128);
    expect(IdempotencyKey::isValid($key))->toBeTrue()
        ->and(IdempotencyKey::validate($key))->toBe($key);
});

it('fail: key format rejects len129 [D-005]', function () {
    $key = str_repeat('x', 129);
    expect(IdempotencyKey::isValid($key))->toBeFalse();
    expect(fn () => IdempotencyKey::validate($key))->toThrow(InvalidArgumentException::class);
});

it('fail: key format rejects empty_string [D-005]', function () {
    expect(IdempotencyKey::isValid(''))->toBeFalse();
    expect(fn () => IdempotencyKey::validate(''))->toThrow(InvalidArgumentException::class);
});

it('happy: key format accepts alnum_dot_dash_colon [D-005]', function () {
    $key = 'Abc.123_x-y:Z';
    expect(IdempotencyKey::isValid($key))->toBeTrue()
        ->and(IdempotencyKey::validate($key))->toBe($key);
});

it('fail: key format rejects contains_space [D-005]', function () {
    expect(IdempotencyKey::isValid('has space'))->toBeFalse();
    expect(fn () => IdempotencyKey::validate('has space'))->toThrow(InvalidArgumentException::class);
});

it('fail: key format rejects contains_slash [D-005]', function () {
    expect(IdempotencyKey::isValid('a/b'))->toBeFalse();
});

it('fail: key format rejects contains_at [D-005]', function () {
    expect(IdempotencyKey::isValid('user@host'))->toBeFalse();
});

it('happy: key format accepts uuid_style [D-005]', function () {
    $uuid = '550e8400-e29b-41d4-a716-446655440000';
    expect(IdempotencyKey::isValid($uuid))->toBeTrue();
    $generated = IdempotencyKey::generate();
    expect(IdempotencyKey::isValid($generated))->toBeTrue()
        ->and(strlen($generated))->toBe(36);
});
