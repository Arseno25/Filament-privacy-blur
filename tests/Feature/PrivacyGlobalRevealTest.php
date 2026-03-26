<?php

use Arseno25\FilamentPrivacyBlur\Enums\PrivacyMode;
use Arseno25\FilamentPrivacyBlur\Resolvers\PrivacyDecisionResolver;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Auth;

it('only reveals fields user is authorized for during global reveal', function () {
    // Test that decision resolver returns different results based on authorization
    $authorizedDecision = PrivacyDecisionResolver::resolveForColumn(
        'email',
        PrivacyMode::BlurClick,
        isAuthorized: true, // User is authorized
        columnBlur: null,
        record: null,
        hiddenRoles: null,
        resourceClass: null,
        neverReveal: false
    );

    $unauthorizedDecision = PrivacyDecisionResolver::resolveForColumn(
        'ssn',
        PrivacyMode::BlurClick,
        isAuthorized: false, // User is NOT authorized
        columnBlur: null,
        record: null,
        hiddenRoles: null,
        resourceClass: null,
        neverReveal: false
    );

    // Authorized field should have reveal enabled
    expect($authorizedDecision['reveal_enabled'])->toBeTrue();

    // Unauthorized field should NOT have reveal enabled
    expect($unauthorizedDecision['reveal_enabled'])->toBeFalse();
});

it('hiddenFromRoles prevents global reveal', function () {
    // Create a user with roles
    $user = new class extends User
    {
        public array $roles = ['guest'];

        public function hasRole(string $role): bool
        {
            return in_array($role, $this->roles);
        }

        public function hasAnyRole(array $roles): bool
        {
            return ! empty(array_intersect($roles, $this->roles));
        }
    };
    $user->id = 1;

    auth()->login($user);

    $decision = PrivacyDecisionResolver::resolveForColumn(
        'customer_notes',
        PrivacyMode::BlurClick,
        isAuthorized: true, // Even if authorized via other methods
        columnBlur: null,
        record: null,
        hiddenRoles: ['guest'], // User is in hidden role
        resourceClass: null,
        neverReveal: false
    );

    // With hidden roles, reveal should always be disabled
    expect($decision['reveal_enabled'])->toBeFalse();

    auth()->logout();
});

it('revealNever prevents any reveal even for authorized users', function () {
    $decision = PrivacyDecisionResolver::resolveForColumn(
        'api_key',
        PrivacyMode::BlurClick,
        isAuthorized: true, // User is authorized
        columnBlur: null,
        record: null,
        hiddenRoles: null,
        resourceClass: null,
        neverReveal: true // But neverReveal flag is set
    );

    // neverReveal should override authorization
    expect($decision['reveal_enabled'])->toBeFalse();
});

it('neverReveal combined with hiddenRoles prevents reveal', function () {
    // Create a user with roles
    $user = new class extends User
    {
        public array $roles = ['guest'];

        public function hasRole(string $role): bool
        {
            return in_array($role, $this->roles);
        }

        public function hasAnyRole(array $roles): bool
        {
            return ! empty(array_intersect($roles, $this->roles));
        }
    };
    $user->id = 1;

    auth()->login($user);

    $decision = PrivacyDecisionResolver::resolveForColumn(
        'secret_data',
        PrivacyMode::BlurClick,
        isAuthorized: true,
        columnBlur: null,
        record: null,
        hiddenRoles: ['guest'],
        resourceClass: null,
        neverReveal: true
    );

    // Both flags should prevent reveal
    expect($decision['reveal_enabled'])->toBeFalse();

    auth()->logout();
});

it('blur mode without reveal has correct decision', function () {
    $decision = PrivacyDecisionResolver::resolveForColumn(
        'password',
        PrivacyMode::Blur,
        isAuthorized: true, // Authorized users bypass blur entirely
        columnBlur: null,
        record: null,
        hiddenRoles: null,
        resourceClass: null,
        neverReveal: false
    );

    // Blur mode with authorized user: no blur, no mask, no reveal
    expect($decision['should_blur'])->toBeFalse();
    expect($decision['should_mask'])->toBeFalse();
    expect($decision['reveal_enabled'])->toBeFalse();
});

it('blur mode with unauthorized user still blurs', function () {
    $decision = PrivacyDecisionResolver::resolveForColumn(
        'password',
        PrivacyMode::Blur,
        isAuthorized: false, // Unauthorized users still see blur
        columnBlur: null,
        record: null,
        hiddenRoles: null,
        resourceClass: null,
        neverReveal: false
    );

    // Blur mode with unauthorized user: blur, no reveal
    expect($decision['should_blur'])->toBeTrue();
    expect($decision['should_mask'])->toBeFalse();
    expect($decision['reveal_enabled'])->toBeFalse();
});

it('mask mode prevents reveal', function () {
    $decision = PrivacyDecisionResolver::resolveForColumn(
        'email',
        PrivacyMode::Mask,
        isAuthorized: false, // Unauthorized
        columnBlur: null,
        record: null,
        hiddenRoles: null,
        resourceClass: null,
        neverReveal: false
    );

    // Mask mode should mask, not blur, and not enable reveal
    expect($decision['should_mask'])->toBeTrue();
    expect($decision['should_blur'])->toBeFalse();
    expect($decision['reveal_enabled'])->toBeFalse();
});

// ============================================================================
// Global Reveal Toggle Visibility Tests
// ============================================================================

it('toggle button is hidden when no globally revealable fields exist', function () {
    // When no fields have canBeGloballyRevealed enabled, toggle should not show
    $maskDecision = PrivacyDecisionResolver::resolveForColumn(
        'ssn',
        PrivacyMode::Mask,
        isAuthorized: false,
        columnBlur: null,
        record: null,
        hiddenRoles: null,
        resourceClass: null,
        neverReveal: false
    );

    expect($maskDecision['reveal_enabled'])->toBeFalse();
    // In real scenario, Alpine would check: document.querySelectorAll('[data-privacy-can-globally-reveal="true"]').length === 0
});

it('toggle button is visible when at least one globally revealable field exists', function () {
    // When any field has canBeGloballyRevealed enabled, toggle should show
    $blurClickDecision = PrivacyDecisionResolver::resolveForColumn(
        'salary',
        PrivacyMode::BlurClick,
        isAuthorized: true, // Authorized
        columnBlur: null,
        record: null,
        hiddenRoles: null,
        resourceClass: null,
        neverReveal: false
    );

    expect($blurClickDecision['reveal_enabled'])->toBeTrue();
    // In real scenario, Alpine would check: document.querySelectorAll('[data-privacy-can-globally-reveal="true"]').length > 0
});

it('toggle respects security: unauthorized fields never reveal', function () {
    // Even during global reveal, unauthorized fields stay blurred
    $unauthorizedDecision = PrivacyDecisionResolver::resolveForColumn(
        'admin_notes',
        PrivacyMode::BlurClick,
        isAuthorized: false, // Unauthorized
        columnBlur: null,
        record: null,
        hiddenRoles: null,
        resourceClass: null,
        neverReveal: false
    );

    expect($unauthorizedDecision['reveal_enabled'])->toBeFalse();
});

it('toggle respects security: revealNever fields never reveal', function () {
    // Fields with revealNever() are never globally revealable, regardless of auth
    $neverRevealDecision = PrivacyDecisionResolver::resolveForColumn(
        'salary',
        PrivacyMode::BlurClick,
        isAuthorized: true, // Authorized
        columnBlur: null,
        record: null,
        hiddenRoles: null,
        resourceClass: null,
        neverReveal: true // But neverReveal flag is set
    );

    expect($neverRevealDecision['reveal_enabled'])->toBeFalse();
});

it('toggle correctly shows when hybrid mode field is authorized for global reveal', function () {
    // Hybrid mode shows masked content to all users, so it's not globally revealable
    $hybridDecision = PrivacyDecisionResolver::resolveForColumn(
        'customer_notes',
        PrivacyMode::Hybrid,
        isAuthorized: true, // Authorized
        columnBlur: null,
        record: null,
        hiddenRoles: null,
        resourceClass: null,
        neverReveal: false
    );

    expect($hybridDecision['reveal_enabled'])->toBeFalse(); // Hybrid doesn't support interactive reveal
    expect($hybridDecision['should_mask'])->toBeTrue(); // Shows masked to authorized users
    expect($hybridDecision['should_blur'])->toBeFalse(); // No blur overlay, just masking
});
