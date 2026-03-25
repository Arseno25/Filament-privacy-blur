<?php

use Arseno25\FilamentPrivacyBlur\Services\PrivacyAuthorizationService;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Auth;

it('authorizes if no constraints are provided', function () {
    expect(PrivacyAuthorizationService::isAuthorized(null, null, null, null))->toBeFalse();
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
