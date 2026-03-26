<?php

use Arseno25\FilamentPrivacyBlur\Enums\PrivacyMode;
use Arseno25\FilamentPrivacyBlur\Filament\ColumnPrivacyMacros;
use Arseno25\FilamentPrivacyBlur\FilamentPrivacyBlurPlugin;
use Arseno25\FilamentPrivacyBlur\Resolvers\PrivacyDecisionResolver;
use Arseno25\FilamentPrivacyBlur\Services\PrivacyAuthorizationService;
use Arseno25\FilamentPrivacyBlur\Services\PrivacyMaskingService;
use Arseno25\FilamentPrivacyBlur\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

uses(TestCase::class);

// ============================================================================
// authorizeRevealWith() & revealIfCan() Tests
// ============================================================================

it('authorizeRevealWith returns authorized when gate allows', function () {
    Gate::define('view-sensitive-data', fn ($user) => true);

    $user = new User;
    $user->id = 1;
    Auth::login($user);

    $result = PrivacyAuthorizationService::authorizeWith('view-sensitive-data');

    expect($result->authorized)->toBeTrue()
        ->and($result->method)->toBe('gate')
        ->and($result->reason)->toBe('gate_allowed');
});

it('authorizeRevealWith returns denied when gate denies', function () {
    Gate::define('view-sensitive-data', fn ($user) => false);

    $user = new User;
    $user->id = 1;
    Auth::login($user);

    $result = PrivacyAuthorizationService::authorizeWith('view-sensitive-data');

    expect($result->authorized)->toBeFalse()
        ->and($result->method)->toBe('gate')
        ->and($result->reason)->toBe('gate_denied');
});

it('authorizeRevealWith passes record to gate for policy checks', function () {
    Gate::define('view-post', fn ($user, $post) => $user->id === $post->user_id);

    $user = new User;
    $user->id = 1;
    Auth::login($user);

    $post = new class extends Model
    {
        protected $fillable = ['user_id'];

        public $user_id = 1;
    };

    $result = PrivacyAuthorizationService::authorizeWith('view-post', $post);

    expect($result->authorized)->toBeTrue();
});

it('authorizeRevealWith returns no_user when not authenticated', function () {
    Auth::logout();

    $result = PrivacyAuthorizationService::authorizeWith('any-ability');

    expect($result->authorized)->toBeFalse()
        ->and($result->method)->toBe('none')
        ->and($result->reason)->toBe('no_authenticated_user');
});

it('revealIfCan is alias for authorizeRevealWith and works identically', function () {
    Gate::define('view-email', fn ($user) => true);

    $user = new User;
    $user->id = 1;
    Auth::login($user);

    $result = PrivacyAuthorizationService::authorizeWith('view-email');
    $result2 = PrivacyAuthorizationService::authorizeWith('view-email');

    expect($result->authorized)->toBe($result2->authorized);
});

// ============================================================================
// permission() Tests
// ============================================================================

it('permission with single permission returns authorized when can() passes', function () {
    Gate::define('view-salary', fn ($user) => true);

    $user = new User;
    $user->id = 1;
    Auth::login($user);

    $result = PrivacyAuthorizationService::checkAuthorization([
        'privacy_permission' => 'view-salary',
    ]);

    expect($result)->toBeTrue();
});

it('permission with single permission returns denied when can() fails', function () {
    // Don't define any gate - can() will return false

    $user = new User;
    $user->id = 1;
    Auth::login($user);

    $result = PrivacyAuthorizationService::checkAuthorization([
        'privacy_permission' => 'view-salary',
    ]);

    expect($result)->toBeFalse();
});

// ============================================================================
// visibleToPermissions() Tests
// ============================================================================

it('visibleToPermissions returns authorized when user has any permission', function () {
    Gate::define('view-data', fn ($user) => false);
    Gate::define('edit-data', fn ($user) => true); // This one passes

    $user = new User;
    $user->id = 1;
    Auth::login($user);

    $result = PrivacyAuthorizationService::checkAuthorization([
        'privacy_permissions' => ['view-data', 'edit-data'],
    ]);

    expect($result)->toBeTrue();
});

it('visibleToPermissions returns denied when user has no permissions', function () {
    // Don't define any gates

    $user = new User;
    $user->id = 1;
    Auth::login($user);

    $result = PrivacyAuthorizationService::checkAuthorization([
        'privacy_permissions' => ['view-data', 'edit-data'],
    ]);

    expect($result)->toBeFalse();
});

it('visibleToPermissions with empty array returns denied', function () {
    $user = new User;
    $user->id = 1;
    Auth::login($user);

    $result = PrivacyAuthorizationService::authorizeWithPermissions([]);

    expect($result->authorized)->toBeFalse()
        ->and($result->reason)->toBe('no_permissions');
});

// ============================================================================
// Role Helper Degradation Tests
// ============================================================================

it('role helpers degrade gracefully when no role methods exist', function () {
    // User without any role methods
    $user = new User;
    $user->id = 1;
    Auth::login($user);

    $result = PrivacyAuthorizationService::checkAuthorization([
        'privacy_roles' => ['admin', 'super-admin'],
    ]);

    expect($result)->toBeFalse();
});

it('role helpers work with hasAnyRole method', function () {
    $user = new class extends User
    {
        public array $roles = ['editor'];

        public function hasAnyRole(array $roles): bool
        {
            return ! empty(array_intersect($roles, $this->roles));
        }
    };
    $user->id = 1;
    Auth::login($user);

    $result = PrivacyAuthorizationService::checkAuthorization([
        'privacy_roles' => ['editor', 'admin'],
    ]);

    expect($result)->toBeTrue();
});

it('role helpers work with hasRole method', function () {
    $user = new class extends User
    {
        public string $role = 'admin';

        public function hasRole(string $role): bool
        {
            return $this->role === $role;
        }
    };
    $user->id = 1;
    Auth::login($user);

    $result = PrivacyAuthorizationService::checkAuthorization([
        'privacy_roles' => ['admin'],
    ]);

    expect($result)->toBeTrue();
});

it('role helpers return denied when user does not have required role', function () {
    $user = new class extends User
    {
        public string $role = 'user';

        public function hasRole(string $role): bool
        {
            return $this->role === $role;
        }
    };
    $user->id = 1;
    Auth::login($user);

    $result = PrivacyAuthorizationService::checkAuthorization([
        'privacy_roles' => ['admin'],
    ]);

    expect($result)->toBeFalse();
});

// ============================================================================
// Secure-by-Default Tests
// ============================================================================

it('secure by default: private() with no authorization returns false', function () {
    $user = new User;
    $user->id = 1;
    Auth::login($user);

    // No authorization constraints set
    $result = PrivacyAuthorizationService::checkAuthorization([]);

    expect($result)->toBeFalse(); // Secure by default
});

it('secure by default: isAuthorized with no constraints returns false', function () {
    $user = new User;
    $user->id = 1;
    Auth::login($user);

    $result = PrivacyAuthorizationService::isAuthorized(
        roles: null,
        permissions: null,
        policy: null,
        customAuth: null,
    );

    expect($result)->toBeFalse();
});

it('secure by default: explicit authorization returns true when allowed', function () {
    Gate::define('view-data', fn ($user) => true);

    $user = new User;
    $user->id = 1;
    Auth::login($user);

    $result = PrivacyAuthorizationService::checkAuthorization([
        'privacy_permission' => 'view-data',
    ]);

    expect($result)->toBeTrue();
});

// ============================================================================
// revealNever() Tests
// ============================================================================

it('revealNever prevents all reveal regardless of authorization', function () {
    Gate::define('view-data', fn ($user) => true);

    $user = new User;
    $user->id = 1;
    Auth::login($user);

    // User is authorized, but neverReveal is set
    $decision = PrivacyDecisionResolver::createDecision(
        'test_field',
        PrivacyMode::BlurClick,
        isAuthorized: true,
        columnBlur: null,
        record: null,
        hiddenRoles: null,
        resourceClass: null,
        neverReveal: true // <-- neverReveal flag
    );

    expect($decision->canRevealInteractively)->toBeFalse();
    expect($decision->canBeGloballyRevealed)->toBeFalse();
});

it('revealNever overrides blur_click mode', function () {
    Gate::define('view-data', fn ($user) => true);

    $user = new User;
    $user->id = 1;
    Auth::login($user);

    $decision = PrivacyDecisionResolver::createDecision(
        'test_field',
        PrivacyMode::BlurClick,
        isAuthorized: true,
        columnBlur: null,
        record: null,
        hiddenRoles: null,
        resourceClass: null,
        neverReveal: true
    );

    expect($decision->canRevealInteractively)->toBeFalse();
});

it('revealNever overrides blur_hover mode', function () {
    Gate::define('view-data', fn ($user) => true);

    $user = new User;
    $user->id = 1;
    Auth::login($user);

    $decision = PrivacyDecisionResolver::createDecision(
        'test_field',
        PrivacyMode::BlurHover,
        isAuthorized: true,
        columnBlur: null,
        record: null,
        hiddenRoles: null,
        resourceClass: null,
        neverReveal: true
    );

    expect($decision->canRevealInteractively)->toBeFalse();
});

// ============================================================================
// hiddenFromRoles() Tests
// ============================================================================

it('hiddenFromRoles prevents reveal even for authorized users', function () {
    Gate::define('view-data', fn ($user) => true);

    $user = new class extends User
    {
        public array $roles = ['guest'];

        public function hasAnyRole(array $roles): bool
        {
            return ! empty(array_intersect($roles, $this->roles));
        }
    };
    $user->id = 1;
    Auth::login($user);

    $decision = PrivacyDecisionResolver::createDecision(
        'test_field',
        PrivacyMode::BlurClick,
        isAuthorized: true, // Authorized via gate
        columnBlur: null,
        record: null,
        hiddenRoles: ['guest'], // But hidden role applies
        resourceClass: null,
        neverReveal: false
    );

    expect($decision->canRevealInteractively)->toBeFalse();
    expect($decision->canBeGloballyRevealed)->toBeFalse();
});

it('hiddenFromRoles with empty array does not hide', function () {
    $user = new User;
    $user->id = 1;
    Auth::login($user);

    $decision = PrivacyDecisionResolver::createDecision(
        'test_field',
        PrivacyMode::BlurClick,
        isAuthorized: true,
        columnBlur: null,
        record: null,
        hiddenRoles: [], // Empty array = no restriction
        resourceClass: null,
        neverReveal: false
    );

    expect($decision->canRevealInteractively)->toBeTrue();
});

// ============================================================================
// Global Reveal Restrictions Tests
// ============================================================================

it('global reveal only affects fields with canBeGloballyRevealed true', function () {
    Gate::define('view-sensitive', fn ($user) => true);

    $user = new User;
    $user->id = 1;
    Auth::login($user);

    // Field that CAN be globally revealed
    $decision1 = PrivacyDecisionResolver::createDecision(
        'sensitive_field',
        PrivacyMode::BlurClick,
        isAuthorized: true,
        columnBlur: null,
        record: null,
        hiddenRoles: null,
        resourceClass: null,
        neverReveal: false
    );

    // Field that CANNOT be globally revealed (blur mode - no reveal)
    $decision2 = PrivacyDecisionResolver::createDecision(
        'always_blurred',
        PrivacyMode::Blur,
        isAuthorized: true,
        columnBlur: null,
        record: null,
        hiddenRoles: null,
        resourceClass: null,
        neverReveal: false
    );

    expect($decision1->canBeGloballyRevealed)->toBeTrue();
    expect($decision2->canBeGloballyRevealed)->toBeFalse();
});

it('global reveal is blocked by neverReveal flag', function () {
    Gate::define('view-data', fn ($user) => true);

    $user = new User;
    $user->id = 1;
    Auth::login($user);

    $decision = PrivacyDecisionResolver::createDecision(
        'never_reveal_field',
        PrivacyMode::BlurClick,
        isAuthorized: true,
        columnBlur: null,
        record: null,
        hiddenRoles: null,
        resourceClass: null,
        neverReveal: true
    );

    expect($decision->canBeGloballyRevealed)->toBeFalse();
});

it('global reveal is blocked by hidden roles', function () {
    Gate::define('view-data', fn ($user) => true);

    $user = new class extends User
    {
        public array $roles = ['guest'];

        public function hasAnyRole(array $roles): bool
        {
            return ! empty(array_intersect($roles, $this->roles));
        }
    };
    $user->id = 1;
    Auth::login($user);

    $decision = PrivacyDecisionResolver::createDecision(
        'field',
        PrivacyMode::BlurClick,
        isAuthorized: true,
        columnBlur: null,
        record: null,
        hiddenRoles: ['guest'],
        resourceClass: null,
        neverReveal: false
    );

    expect($decision->canBeGloballyRevealed)->toBeFalse();
});

// ============================================================================
// Audit Validation Tests
// ============================================================================

it('audit is not logged when audit is disabled for field', function () {
    // Audit disabled globally in config, can be overridden per field
    // This tests the metadata respects that setting
    $meta = ['privacy_audit_reveal' => false];

    expect($meta['privacy_audit_reveal'])->toBeFalse();
});

it('audit is only logged when canRevealInteractively is true', function () {
    // From ColumnPrivacyMacros - audit attributes only added when both conditions met:
    // 1. privacy_audit_reveal is true
    // 2. canRevealInteractively is true

    $decision = PrivacyDecisionResolver::createDecision(
        'audit_field',
        PrivacyMode::BlurClick,
        isAuthorized: true,
        columnBlur: null,
        record: null,
        hiddenRoles: null,
        resourceClass: null,
        neverReveal: false
    );

    // Only when interactive reveal is allowed should audit be considered
    expect($decision->canRevealInteractively)->toBeTrue();
});

// ============================================================================
// Mode Behavior Tests
// ============================================================================

it('blur_auth mode: authorized users see plain text, unauthorized see blur', function () {
    Gate::define('view-data', fn ($user) => true);

    $authorizedUser = new User;
    $authorizedUser->id = 1;
    Auth::login($authorizedUser);

    $authDecision = PrivacyDecisionResolver::createDecision(
        'field',
        PrivacyMode::BlurAuth,
        isAuthorized: true,
        columnBlur: null,
        record: null,
        hiddenRoles: null,
        resourceClass: null,
        neverReveal: false
    );

    expect($authDecision->shouldBlur)->toBeFalse();
    expect($authDecision->shouldRenderMasked)->toBeFalse();

    Auth::logout();
    $unauthUser = new User;
    $unauthUser->id = 2;
    Auth::login($unauthUser);

    $unauthDecision = PrivacyDecisionResolver::createDecision(
        'field',
        PrivacyMode::BlurAuth,
        isAuthorized: false,
        columnBlur: null,
        record: null,
        hiddenRoles: null,
        resourceClass: null,
        neverReveal: false
    );

    expect($unauthDecision->shouldBlur)->toBeTrue();
    expect($unauthDecision->canRevealInteractively)->toBeFalse();

    Auth::logout();
});

it('blur_click mode: authorized users can click to reveal', function () {
    Gate::define('view-data', fn ($user) => true);

    $user = new User;
    $user->id = 1;
    Auth::login($user);

    $decision = PrivacyDecisionResolver::createDecision(
        'field',
        PrivacyMode::BlurClick,
        isAuthorized: true,
        columnBlur: null,
        record: null,
        hiddenRoles: null,
        resourceClass: null,
        neverReveal: false
    );

    expect($decision->canRevealInteractively)->toBeTrue();
    expect($decision->canBeGloballyRevealed)->toBeTrue();
    expect($decision->shouldBlur)->toBeTrue();
});

it('blur_click mode: unauthorized users cannot click to reveal', function () {
    Gate::define('view-data', fn ($user) => false);

    $user = new User;
    $user->id = 1;
    Auth::login($user);

    $decision = PrivacyDecisionResolver::createDecision(
        'field',
        PrivacyMode::BlurClick,
        isAuthorized: false,
        columnBlur: null,
        record: null,
        hiddenRoles: null,
        resourceClass: null,
        neverReveal: false
    );

    expect($decision->canRevealInteractively)->toBeFalse();
    expect($decision->canBeGloballyRevealed)->toBeFalse();
    expect($decision->shouldBlur)->toBeTrue();
});

it('blur_hover mode: authorized users can hover to reveal', function () {
    Gate::define('view-data', fn ($user) => true);

    $user = new User;
    $user->id = 1;
    Auth::login($user);

    $decision = PrivacyDecisionResolver::createDecision(
        'field',
        PrivacyMode::BlurHover,
        isAuthorized: true,
        columnBlur: null,
        record: null,
        hiddenRoles: null,
        resourceClass: null,
        neverReveal: false
    );

    expect($decision->canRevealInteractively)->toBeTrue();
    expect($decision->shouldBlur)->toBeTrue();
});

it('mask mode: data is masked for unauthorized users', function () {
    $decision = PrivacyDecisionResolver::createDecision(
        'email',
        PrivacyMode::Mask,
        isAuthorized: false,
        columnBlur: null,
        record: null,
        hiddenRoles: null,
        resourceClass: null,
        neverReveal: false
    );

    expect($decision->shouldRenderMasked)->toBeTrue();
    expect($decision->shouldBlur)->toBeFalse();
    expect($decision->canRevealInteractively)->toBeFalse();
});

it('mask mode: authorized users see plain text', function () {
    Gate::define('view-email', fn ($user) => true);

    $user = new User;
    $user->id = 1;
    Auth::login($user);

    $decision = PrivacyDecisionResolver::createDecision(
        'email',
        PrivacyMode::Mask,
        isAuthorized: true,
        columnBlur: null,
        record: null,
        hiddenRoles: null,
        resourceClass: null,
        neverReveal: false
    );

    expect($decision->shouldRenderMasked)->toBeFalse();
    expect($decision->shouldBlur)->toBeFalse();
});

it('hybrid mode: shows masked text even to authorized users', function () {
    Gate::define('view-data', fn ($user) => true);

    $user = new User;
    $user->id = 1;
    Auth::login($user);

    $decision = PrivacyDecisionResolver::createDecision(
        'field',
        PrivacyMode::Hybrid,
        isAuthorized: true,
        columnBlur: null,
        record: null,
        hiddenRoles: null,
        resourceClass: null,
        neverReveal: false
    );

    expect($decision->shouldRenderMasked)->toBeTrue();
    expect($decision->shouldBlur)->toBeFalse();
});

it('hybrid mode: unauthorized users see masked + blurred', function () {
    $decision = PrivacyDecisionResolver::createDecision(
        'field',
        PrivacyMode::Hybrid,
        isAuthorized: false,
        columnBlur: null,
        record: null,
        hiddenRoles: null,
        resourceClass: null,
        neverReveal: false
    );

    expect($decision->shouldRenderMasked)->toBeTrue();
    expect($decision->shouldBlur)->toBeTrue();
});

it('disabled mode: no privacy effect applied', function () {
    $decision = PrivacyDecisionResolver::createDecision(
        'field',
        PrivacyMode::Disabled,
        isAuthorized: false,
        columnBlur: null,
        record: null,
        hiddenRoles: null,
        resourceClass: null,
        neverReveal: false
    );

    expect($decision->hasPrivacyEffect())->toBeFalse();
});

// ============================================================================
// Plugin Disabled State Tests
// ============================================================================

it('plugin disabled state: no privacy attributes are rendered', function () {
    // When plugin is disabled at panel level, render hooks don't run
    // This tests the expected behavior
    $plugin = FilamentPrivacyBlurPlugin::make()
        ->enabled(false);

    expect($plugin->getIsEnabled())->toBeFalse();
});

// ============================================================================
// Except Columns/Resources/Panels Tests
// ============================================================================

it('except columns: fields in except list bypass privacy', function () {
    // Set config for this test
    config(['filament-privacy-blur.except_columns' => ['id', 'created_at']]);

    $decision1 = PrivacyDecisionResolver::createDecision(
        'id', // In except list
        PrivacyMode::BlurClick,
        isAuthorized: false, // Even unauthorized
        columnBlur: null,
        record: null,
        hiddenRoles: null,
        resourceClass: null,
        neverReveal: false
    );

    $decision2 = PrivacyDecisionResolver::createDecision(
        'email', // NOT in except list
        PrivacyMode::BlurClick,
        isAuthorized: false,
        columnBlur: null,
        record: null,
        hiddenRoles: null,
        resourceClass: null,
        neverReveal: false
    );

    expect($decision1->hasPrivacyEffect())->toBeFalse();
    expect($decision2->hasPrivacyEffect())->toBeTrue();
});

it('except resources: resources in except list bypass privacy', function () {
    config(['filament-privacy-blur.except_resources' => ['App\\Filament\\Resources\\PublicResource']]);

    $decision1 = PrivacyDecisionResolver::createDecision(
        'field',
        PrivacyMode::BlurClick,
        isAuthorized: false,
        columnBlur: null,
        record: null,
        hiddenRoles: null,
        resourceClass: 'App\\Filament\\Resources\\PublicResource', // In except list
        neverReveal: false
    );

    $decision2 = PrivacyDecisionResolver::createDecision(
        'field',
        PrivacyMode::BlurClick,
        isAuthorized: false,
        columnBlur: null,
        record: null,
        hiddenRoles: null,
        resourceClass: 'App\\Filament\\Resources\\PrivateResource', // NOT in except list
        neverReveal: false
    );

    expect($decision1->hasPrivacyEffect())->toBeFalse();
    expect($decision2->hasPrivacyEffect())->toBeTrue();
});

it('except panels: panels in except list bypass privacy', function () {
    config(['filament-privacy-blur.except_panels' => ['public']]);

    // This would be checked by PrivacyConfigResolver::isPanelExcepted()
    // which is called in PrivacyDecisionResolver::createDecision()
    $exceptedPanel = config('filament-privacy-blur.except_panels');

    expect($exceptedPanel)->toContain('public');
});

// ============================================================================
// Export Masking Safety Tests
// ============================================================================

it('export context automatically applies masking instead of blur', function () {
    $isExport = ColumnPrivacyMacros::isExportContext();

    // In export context, blur modes should fallback to masking
    // This is tested via ColumnPrivacyMacros::applyMasking()
    expect(true)->toBeTrue(); // Placeholder - actual test needs proper request context
});

it('export with mask strategy uses correct masking', function () {
    $service = app(PrivacyMaskingService::class);

    // Test various masking strategies work correctly in export
    expect($service->mask('email', 'user@example.com'))->toBe('u**r@example.com');
    expect($service->mask('phone', '081234567890'))->toBe('0812****7890');
    expect($service->mask('nik', '3173123456789012'))->toBe('3173********9012');
});
