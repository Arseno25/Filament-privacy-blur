<?php

use Arseno25\FilamentPrivacyBlur\Services\PrivacyAuthorizationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

// Note: Gate::forget() is not available in Laravel 11+
// We define gates with unique names per test to avoid conflicts

/**
 * Shield Compatibility Tests
 *
 * Filament Shield creates Laravel Gate abilities that work seamlessly
 * with the plugin's ability-first authorization approach.
 * These tests verify that integration works correctly.
 */
it('works with Filament Shield abilities via Gate', function () {
    // Shield creates gates like this
    Gate::define('view_sensitive_data', fn ($user) => true);

    $user = new User;
    $user->id = 1;
    Auth::login($user);

    // The plugin should work with Shield-defined abilities
    $result = PrivacyAuthorizationService::authorizeWith('view_sensitive_data');

    expect($result->authorized)->toBeTrue()
        ->and($result->method)->toBe('gate');
});

it('denies when Shield ability is not granted', function () {
    // Shield gate that denies access
    Gate::define('view_sensitive_data', fn ($user) => false);

    $user = new User;
    $user->id = 1;
    Auth::login($user);

    $result = PrivacyAuthorizationService::authorizeWith('view_sensitive_data');

    expect($result->authorized)->toBeFalse()
        ->and($result->method)->toBe('gate');
});

it('does not require Shield to be installed', function () {
    // Plugin should work without Shield - using standard Laravel Gates
    Gate::define('view_data', fn ($user) => true);

    $user = new User;
    $user->id = 1;
    Auth::login($user);

    $result = PrivacyAuthorizationService::authorizeWith('view_data');

    expect($result->authorized)->toBeTrue();
});

it('works with multiple Shield abilities', function () {
    // Define multiple Shield-style abilities
    Gate::define('view_admin_data', fn ($user) => $user->id === 1);
    Gate::define('edit_admin_data', fn ($user) => $user->id === 1);

    $user = new User;
    $user->id = 1;
    Auth::login($user);

    $result1 = PrivacyAuthorizationService::authorizeWith('view_admin_data');
    $result2 = PrivacyAuthorizationService::authorizeWith('edit_admin_data');

    expect($result1->authorized)->toBeTrue()
        ->and($result2->authorized)->toBeTrue();
});

it('Shield abilities work with policy methods via can()', function () {
    // Simulate Shield's integration with model policies
    Gate::define('access_pii', fn ($user, $record) => $user->id === $record->owner_id);

    $user = new User;
    $user->id = 1;
    Auth::login($user);

    $record = new class extends Model
    {
        protected $fillable = ['owner_id'];

        public $owner_id = 1;
    };

    $result = PrivacyAuthorizationService::authorizeWith('access_pii', $record);

    expect($result->authorized)->toBeTrue();
});

it('Shield abilities work with metadata checkAuthorization', function () {
    // Define a Shield-style ability
    Gate::define('view_pii', fn ($user) => $user->id === 1);

    $user = new User;
    $user->id = 1;
    Auth::login($user);

    // Use the metadata-based check (how ColumnPrivacyMacros actually uses it)
    $meta = [
        'privacy_ability' => 'view_pii',
    ];

    $result = PrivacyAuthorizationService::checkAuthorization($meta);

    expect($result)->toBeTrue();
});

it('handles Shield abilities with custom closure fallback', function () {
    $user = new User;
    $user->id = 1;
    Auth::login($user);

    // Custom closure can add additional logic beyond Shield
    $meta = [
        'privacy_ability' => 'some_shield_ability', // Would fail - not defined
        'privacy_auth_closure' => fn ($user) => $user->id === 1, // But closure passes
    ];

    $result = PrivacyAuthorizationService::checkAuthorization($meta);

    // Closure takes priority and allows access
    expect($result)->toBeTrue();
});

it('permission macro works with Shield-defined permissions', function () {
    // Shield defines permissions as gates
    Gate::define('view_sensitive', fn ($user) => true);

    $user = new User;
    $user->id = 1;
    Auth::login($user);

    // Using the permission macro
    $result = PrivacyAuthorizationService::authorizeWithPermissions(['view_sensitive']);

    expect($result->authorized)->toBeTrue();
});

it('visibleToPermissions macro works with Shield', function () {
    // Multiple Shield permissions
    Gate::define('view_data', fn ($user) => false);
    Gate::define('edit_data', fn ($user) => true);

    $user = new User;
    $user->id = 1;
    Auth::login($user);

    $result = PrivacyAuthorizationService::authorizeWithPermissions(['view_data', 'edit_data']);

    // At least one permission allows access
    expect($result->authorized)->toBeTrue();
});

it('works without any authorization package installed', function () {
    // No Spatie, no Shield - just plain Laravel
    $user = new User;
    $user->id = 1;
    Auth::login($user);

    // Define a simple gate (vanilla Laravel)
    Gate::define('view_simple', fn ($user) => true);

    $result = PrivacyAuthorizationService::authorizeWith('view_simple');

    expect($result->authorized)->toBeTrue();
});

it('secure by default even without Shield', function () {
    $user = new User;
    $user->id = 1;
    Auth::login($user);

    // No gates defined, no permissions
    $meta = [];

    $result = PrivacyAuthorizationService::checkAuthorization($meta);

    // Should be secure by default
    expect($result)->toBeFalse();
});

it('Shield resource permissions work with plugin', function () {
    // Shield creates resource-specific permissions like this
    Gate::define('view_user::ssn', fn ($user) => $user->id === 1);

    $user = new User;
    $user->id = 1;
    Auth::login($user);

    $result = PrivacyAuthorizationService::authorizeWith('view_user::ssn');

    expect($result->authorized)->toBeTrue();
});

it('handles unauthorized Shield permissions correctly', function () {
    // Shield permission that denies
    Gate::define('view_admin_only', fn ($user) => $user->id === 999); // Impossible to pass

    $user = new User;
    $user->id = 1;
    Auth::login($user);

    $result = PrivacyAuthorizationService::authorizeWith('view_admin_only');

    expect($result->authorized)->toBeFalse()
        ->and($result->reason)->toBe('gate_denied'); // Gate denies access
});
