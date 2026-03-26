<?php

use Arseno25\FilamentPrivacyBlur\DataTransferObjects\AuthorizationResult;
use Arseno25\FilamentPrivacyBlur\Services\PrivacyAuthorizationService;
use Arseno25\FilamentPrivacyBlur\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

uses(TestCase::class);

// Note: Gate::forget() is not available in Laravel 11+
// We define gates with unique names per test to avoid conflicts

it('authorizes reveal using Laravel Gate when gate exists', function () {
    Gate::define('view-ssn', fn ($user) => $user->id === 1);

    $user = new User;
    $user->id = 1;
    Auth::login($user);

    $result = PrivacyAuthorizationService::authorizeWith('view-ssn');

    expect($result)->toBeInstanceOf(AuthorizationResult::class)
        ->and($result->authorized)->toBeTrue()
        ->and($result->method)->toBe('gate')
        ->and($result->reason)->toBe('gate_allowed');
});

it('denies authorization when gate denies access', function () {
    Gate::define('view-ssn', fn ($user) => $user->id === 1);

    $user = new User;
    $user->id = 2; // Different ID
    Auth::login($user);

    $result = PrivacyAuthorizationService::authorizeWith('view-ssn');

    expect($result->authorized)->toBeFalse()
        ->and($result->method)->toBe('gate')
        ->and($result->reason)->toBe('gate_denied');
});

it('falls back to user->can() when gate does not exist', function () {
    // Don't define any gate - should fall back to can()
    $user = new User;
    $user->id = 1;
    Auth::login($user);

    // Without a defined gate, can() will return false by default
    $result = PrivacyAuthorizationService::authorizeWith('non-existent-gate');

    expect($result->authorized)->toBeFalse()
        ->and($result->method)->toBe('can')
        ->and($result->reason)->toBe('can_denied');
});

it('returns no user result when no user is authenticated', function () {
    Auth::logout();

    $result = PrivacyAuthorizationService::authorizeWith('any-gate');

    expect($result->authorized)->toBeFalse()
        ->and($result->method)->toBe('none')
        ->and($result->reason)->toBe('no_authenticated_user');
});

it('authorizes using custom closure with full context', function () {
    $user = new User;
    $user->id = 1;
    $user->name = 'Admin';
    Auth::login($user);

    $closure = fn ($user, $record) => $user->name === 'Admin' && $record?->id === 42;

    $testRecord = new class extends Model
    {
        protected $fillable = ['id'];

        public $id = 42;
    };

    $result = PrivacyAuthorizationService::authorizeUsing($closure, $testRecord);

    expect($result->authorized)->toBeTrue()
        ->and($result->method)->toBe('closure')
        ->and($result->reason)->toBe('closure_allowed');
});

it('passes record to custom closure correctly', function () {
    $user = new User;
    $user->id = 1;
    Auth::login($user);

    $receivedRecord = null;
    $closure = function ($user, $record) use (&$receivedRecord) {
        $receivedRecord = $record;

        return true;
    };

    $testRecord = new class extends Model
    {
        protected $fillable = ['id', 'name'];

        public $id = 42;

        public $name = 'Test Record';
    };

    PrivacyAuthorizationService::authorizeUsing($closure, $testRecord);

    expect($receivedRecord)->not->toBeNull()
        ->and($receivedRecord->id)->toBe(42)
        ->and($receivedRecord->name)->toBe('Test Record');
});

it('denies when custom closure returns false', function () {
    $user = new User;
    $user->id = 1;
    Auth::login($user);

    $closure = fn () => false;

    $result = PrivacyAuthorizationService::authorizeUsing($closure);

    expect($result->authorized)->toBeFalse()
        ->and($result->method)->toBe('closure')
        ->and($result->reason)->toBe('closure_denied');
});

it('checks permissions via can() when no Spatie methods exist', function () {
    // Define a gate so can() works
    Gate::define('view-sensitive-data', fn ($user) => true);

    $user = new User;
    $user->id = 1;
    Auth::login($user);

    $result = PrivacyAuthorizationService::authorizeWithPermissions(['view-sensitive-data']);

    expect($result->authorized)->toBeTrue()
        ->and($result->method)->toBe('can');
});

it('denies when user lacks all permissions', function () {
    // Don't define any gates
    $user = new User;
    $user->id = 1;
    Auth::login($user);

    $result = PrivacyAuthorizationService::authorizeWithPermissions(['view-data', 'edit-data']);

    expect($result->authorized)->toBeFalse()
        ->and($result->method)->toBe('can')
        ->and($result->reason)->toBe('all_can_denied');
});

it('authorizes when user has at least one permission', function () {
    Gate::define('view-data', fn ($user) => false);
    Gate::define('edit-data', fn ($user) => true); // This one passes

    $user = new User;
    $user->id = 1;
    Auth::login($user);

    $result = PrivacyAuthorizationService::authorizeWithPermissions(['view-data', 'edit-data']);

    expect($result->authorized)->toBeTrue()
        ->and($result->method)->toBe('can');
});

it('returns denied for empty permissions array', function () {
    $user = new User;
    $user->id = 1;
    Auth::login($user);

    $result = PrivacyAuthorizationService::authorizeWithPermissions([]);

    expect($result->authorized)->toBeFalse()
        ->and($result->reason)->toBe('no_permissions');
});

it('degrades gracefully when role methods do not exist', function () {
    // User without hasAnyRole or hasRole methods
    $user = new User;
    $user->id = 1;
    Auth::login($user);

    $result = PrivacyAuthorizationService::authorizeWithRoles(['admin', 'super-admin']);

    expect($result->authorized)->toBeFalse()
        ->and($result->reason)->toBe('no_role_method_available');
});

it('uses checkAuthorization with metadata array - ability first', function () {
    Gate::define('view-ssn', fn ($user) => true);

    $user = new User;
    $user->id = 1;
    Auth::login($user);

    $meta = [
        'privacy_ability' => 'view-ssn',
    ];

    $result = PrivacyAuthorizationService::checkAuthorization($meta);

    expect($result)->toBeTrue();
});

it('uses checkAuthorization with metadata array - closure', function () {
    $user = new User;
    $user->id = 1;
    Auth::login($user);

    $meta = [
        'privacy_auth_closure' => fn ($user) => $user->id === 1,
    ];

    $result = PrivacyAuthorizationService::checkAuthorization($meta);

    expect($result)->toBeTrue();
});

it('uses checkAuthorization with metadata array - permission', function () {
    Gate::define('view-email', fn ($user) => true);

    $user = new User;
    $user->id = 1;
    Auth::login($user);

    $meta = [
        'privacy_permission' => 'view-email',
    ];

    $result = PrivacyAuthorizationService::checkAuthorization($meta);

    expect($result)->toBeTrue();
});

it('respects priority order in checkAuthorization', function () {
    Gate::define('ability-gate', fn ($user) => false); // Gate denies
    Gate::define('perm-gate', fn ($user) => true); // Permission allows

    $user = new User;
    $user->id = 1;
    Auth::login($user);

    // Closure should take priority
    $meta = [
        'privacy_auth_closure' => fn () => true, // Priority 1
        'privacy_ability' => 'ability-gate',    // Priority 2
        'privacy_permission' => 'perm-gate',    // Priority 3
    ];

    $result = PrivacyAuthorizationService::checkAuthorization($meta);

    expect($result)->toBeTrue(); // Closure allowed
});

it('is secure by default when no constraints provided', function () {
    $user = new User;
    $user->id = 1;
    Auth::login($user);

    $meta = []; // No constraints

    $result = PrivacyAuthorizationService::checkAuthorization($meta);

    expect($result)->toBeFalse(); // Secure by default
});

it('legacy isAuthorized method still works', function () {
    Gate::define('view-data', fn ($user) => true);

    $user = new User;
    $user->id = 1;
    Auth::login($user);

    $result = PrivacyAuthorizationService::isAuthorized(
        roles: null,
        permissions: ['view-data'],
        policy: null,
        customAuth: null,
    );

    expect($result)->toBeTrue();
});

it('legacy isAuthorized respects closure priority', function () {
    $user = new User;
    $user->id = 1;
    Auth::login($user);

    $closure = fn () => true;

    $result = PrivacyAuthorizationService::isAuthorized(
        roles: ['admin'], // Would fail
        permissions: ['admin-perm'], // Would fail
        policy: 'admin-policy', // Would fail
        customAuth: $closure, // Should pass - priority
    );

    expect($result)->toBeTrue();
});
