<?php

use Arseno25\FilamentPrivacyBlur\Services\PrivacyAuthorizationService;
use Arseno25\FilamentPrivacyBlur\Tests\TestCase;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Auth;


it('returns false when no constraints are provided (secure by default)', function () {
    $user = new User;
    $user->id = 1;
    Auth::login($user);

    // Secure by default: no constraints = deny access
    expect(PrivacyAuthorizationService::isAuthorized(null, null, null, null))->toBeFalse();
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

it('returns false when only policy is defined but gate does not exist', function () {
    $user = new User;
    $user->id = 1;
    Auth::login($user);

    // Policy defined but no gate exists, user->can() will return false
    expect(PrivacyAuthorizationService::isAuthorized(policy: 'view-sensitive'))->toBeFalse();
});
