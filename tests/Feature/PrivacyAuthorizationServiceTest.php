<?php

use Arseno25\FilamentPrivacyBlur\Services\PrivacyAuthorizationService;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Auth;

it('returns true when no constraints are provided (no auth restriction)', function () {
    $user = new User;
    $user->id = 1;
    Auth::login($user);

    expect(PrivacyAuthorizationService::isAuthorized(null, null, null, null))->toBeTrue();
});

it('returns false when no user is authenticated', function () {
    Auth::logout();
    expect(PrivacyAuthorizationService::isAuthorized(null, null, null, null))->toBeFalse();
});

it('returns false when roles are defined but user has no matching role', function () {
    $user = new User;
    $user->id = 1;
    Auth::login($user);

    // User doesn't have hasAnyRole method (no Spatie installed), so it returns false
    expect(PrivacyAuthorizationService::isAuthorized(roles: ['admin']))->toBeFalse();
});

it('authorizes using custom closure', function () {
    $user = new User;
    $user->id = 1;
    Auth::login($user);

    $closure = fn () => true;
    expect(PrivacyAuthorizationService::isAuthorized(customAuth: $closure))->toBeTrue();
});

it('fails authorization if closure returns false', function () {
    $user = new User;
    $user->id = 1;
    Auth::login($user);

    $closure = fn () => false;
    expect(PrivacyAuthorizationService::isAuthorized(customAuth: $closure))->toBeFalse();
});

it('passes record to custom closure', function () {
    $user = new User;
    $user->id = 1;
    Auth::login($user);

    $receivedRecord = null;
    $closure = function ($user, $record) use (&$receivedRecord) {
        $receivedRecord = $record;

        return true;
    };

    $mockRecord = new User;
    $mockRecord->id = 42;

    PrivacyAuthorizationService::isAuthorized(customAuth: $closure, record: $mockRecord);

    expect($receivedRecord)->not->toBeNull()
        ->and($receivedRecord->id)->toBe(42);
});

it('returns false when permissions are defined but user does not have them', function () {
    $user = new User;
    $user->id = 1;
    Auth::login($user);

    // User can't resolve this permission via can() by default
    expect(PrivacyAuthorizationService::isAuthorized(permissions: ['view-sensitive-data']))->toBeFalse();
});
