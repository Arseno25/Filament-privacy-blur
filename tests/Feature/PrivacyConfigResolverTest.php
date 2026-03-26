<?php

use Arseno25\FilamentPrivacyBlur\Enums\PrivacyMode;
use Arseno25\FilamentPrivacyBlur\FilamentPrivacyBlurPlugin;
use Arseno25\FilamentPrivacyBlur\Resolvers\PrivacyConfigResolver;
use Arseno25\FilamentPrivacyBlur\Resolvers\PrivacyDecisionResolver;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Support\Facades\Config;

it('resolves default mode from config', function () {
    Config::set('filament-privacy-blur.default_mode', 'blur_click');
    expect(PrivacyConfigResolver::resolveMode())->toBe(PrivacyMode::BlurClick);
});

it('uses provided override mode', function () {
    Config::set('filament-privacy-blur.default_mode', 'blur_click');
    expect(PrivacyConfigResolver::resolveMode(PrivacyMode::Mask))->toBe(PrivacyMode::Mask);
});

it('resolves default blur amount', function () {
    Config::set('filament-privacy-blur.default_blur_amount', 6);
    expect(PrivacyConfigResolver::resolveBlurAmount())->toBe(6);
});

it('checks if column is excepted', function () {
    Config::set('filament-privacy-blur.except_columns', ['id', 'created_at']);

    expect(PrivacyConfigResolver::isColumnExcepted('id'))->toBeTrue();
    expect(PrivacyConfigResolver::isColumnExcepted('name'))->toBeFalse();
});

it('checks if resource is excepted', function () {
    Config::set('filament-privacy-blur.except_resources', ['App\\Filament\\Resources\\PublicResource']);

    expect(PrivacyConfigResolver::isResourceExcepted('App\\Filament\\Resources\\PublicResource'))->toBeTrue();
    expect(PrivacyConfigResolver::isResourceExcepted('App\\Filament\\Resources\\PrivateResource'))->toBeFalse();
});

it('resolves audit enabled from config', function () {
    Config::set('filament-privacy-blur.audit_enabled', true);

    expect(PrivacyConfigResolver::isAuditEnabled())->toBeTrue();
});

it('resolves audit disabled from config', function () {
    Config::set('filament-privacy-blur.audit_enabled', false);

    expect(PrivacyConfigResolver::isAuditEnabled())->toBeFalse();
});

it('resolves default mask strategy', function () {
    Config::set('filament-privacy-blur.default_mask_strategy', 'email');

    expect(PrivacyConfigResolver::resolveMaskStrategy())->toBe('email');
});

it('uses column override for mask strategy', function () {
    Config::set('filament-privacy-blur.default_mask_strategy', 'generic');

    expect(PrivacyConfigResolver::resolveMaskStrategy('phone'))->toBe('phone');
});

it('resolves audit enabled from plugin context in active panel', function () {
    $plugin = FilamentPrivacyBlurPlugin::make()->enableAudit(false);
    $panel = Panel::make('test')->id('test')->plugin($plugin);
    Filament::setCurrentPanel($panel);

    // Config says true, but plugin says false. Plugin wins.
    Config::set('filament-privacy-blur.audit_enabled', true);
    expect(PrivacyConfigResolver::isAuditEnabled())->toBeFalse();
});

it('resolves resource excepted from plugin context in active panel', function () {
    $plugin = FilamentPrivacyBlurPlugin::make()->exceptResources(['App\\Filament\\Resources\\PanelResource']);
    $panel = Panel::make('test')->id('test')->plugin($plugin);
    Filament::setCurrentPanel($panel);

    expect(PrivacyConfigResolver::isResourceExcepted('App\\Filament\\Resources\\PanelResource'))->toBeTrue();
});

it('checks if panel is excepted', function () {
    Config::set('filament-privacy-blur.except_panels', ['admin']);

    $adminPanel = Panel::make('admin')->id('admin');
    Filament::setCurrentPanel($adminPanel);
    expect(PrivacyConfigResolver::isPanelExcepted())->toBeTrue();

    $userPanel = Panel::make('user')->id('user');
    Filament::setCurrentPanel($userPanel);
    expect(PrivacyConfigResolver::isPanelExcepted())->toBeFalse();
});

it('exceptPanels at plugin level affects resolver decision flow', function () {
    // Plugin-level exceptPanels should override config
    $plugin = FilamentPrivacyBlurPlugin::make()
        ->exceptPanels(['admin-panel']);

    $adminPanel = Panel::make('admin')->id('admin-panel')->plugin($plugin);
    Filament::setCurrentPanel($adminPanel);

    // Even though config doesn't exclude this panel, plugin setting should win
    Config::set('filament-privacy-blur.except_panels', []);
    expect(PrivacyConfigResolver::isPanelExcepted())->toBeTrue();

    // Different panel should not be affected
    $userPanel = Panel::make('user')->id('user-panel')->plugin($plugin);
    Filament::setCurrentPanel($userPanel);
    expect(PrivacyConfigResolver::isPanelExcepted())->toBeFalse();
});

it('exceptPanels bypasses privacy for all fields in excepted panel', function () {
    // Test real resolver behavior: excepted panels should bypass privacy for all fields

    $plugin = FilamentPrivacyBlurPlugin::make()
        ->exceptPanels(['public-panel']);

    $publicPanel = Panel::make('public')->id('public-panel')->plugin($plugin);
    Filament::setCurrentPanel($publicPanel);

    // Even with no authorization, fields in excepted panel should bypass privacy
    $result = PrivacyDecisionResolver::resolveForColumn(
        'email', // Normally private field
        PrivacyMode::BlurClick,
        isAuthorized: false, // Even unauthorized
        columnBlur: null,
        record: null,
        hiddenRoles: null,
        resourceClass: null,
        neverReveal: false
    );

    // The result contains the decision object in the 'decision' key
    $decision = $result['decision'];

    // Excepted panel should bypass all privacy effects
    expect($decision->hasPrivacyEffect())->toBeFalse();
    expect($decision->shouldBlur)->toBeFalse();
    expect($decision->shouldRenderMasked)->toBeFalse();
    expect($decision->canRevealInteractively)->toBeFalse();
    expect($decision->canBeGloballyRevealed)->toBeFalse();
});

it('exceptPanels at plugin level overrides authorization for non-excepted panels', function () {
    // Prove that panel exclusion at plugin level affects real decision flow
    // and non-excepted panels still respect authorization

    $plugin = FilamentPrivacyBlurPlugin::make()
        ->exceptPanels(['public-panel']);

    $publicPanel = Panel::make('public')->id('public-panel')->plugin($plugin);
    $adminPanel = Panel::make('admin')->id('admin-panel')->plugin($plugin);

    // Test PUBLIC panel (excepted) - should bypass privacy even unauthorized
    Filament::setCurrentPanel($publicPanel);

    $publicResult = PrivacyDecisionResolver::resolveForColumn(
        'salary',
        PrivacyMode::BlurClick,
        isAuthorized: false, // Unauthorized
        columnBlur: null,
        record: null,
        hiddenRoles: null,
        resourceClass: null,
        neverReveal: false
    );

    $publicDecision = $publicResult['decision'];

    // Public panel bypasses privacy entirely
    expect($publicDecision->hasPrivacyEffect())->toBeFalse();
    expect($publicDecision->shouldBlur)->toBeFalse();
    expect($publicDecision->canRevealInteractively)->toBeFalse();

    // Test ADMIN panel (NOT excepted) - should respect authorization
    Filament::setCurrentPanel($adminPanel);

    $adminResultUnauthorized = PrivacyDecisionResolver::resolveForColumn(
        'salary',
        PrivacyMode::BlurClick,
        isAuthorized: false, // Unauthorized
        columnBlur: null,
        record: null,
        hiddenRoles: null,
        resourceClass: null,
        neverReveal: false
    );

    $adminUnauthorizedDecision = $adminResultUnauthorized['decision'];

    // Admin panel enforces privacy for unauthorized users
    expect($adminUnauthorizedDecision->hasPrivacyEffect())->toBeTrue();
    expect($adminUnauthorizedDecision->shouldBlur)->toBeTrue();
    expect($adminUnauthorizedDecision->canRevealInteractively)->toBeFalse();

    // Admin panel with authorized user should allow reveal
    $adminResultAuthorized = PrivacyDecisionResolver::resolveForColumn(
        'salary',
        PrivacyMode::BlurClick,
        isAuthorized: true, // Authorized
        columnBlur: null,
        record: null,
        hiddenRoles: null,
        resourceClass: null,
        neverReveal: false
    );

    $adminAuthorizedDecision = $adminResultAuthorized['decision'];

    expect($adminAuthorizedDecision->hasPrivacyEffect())->toBeTrue();
    expect($adminAuthorizedDecision->shouldBlur)->toBeTrue();
    expect($adminAuthorizedDecision->canRevealInteractively)->toBeTrue();
    expect($adminAuthorizedDecision->canBeGloballyRevealed)->toBeTrue();
});

it('exceptPanels at config level bypasses privacy for specified panels', function () {
    // Test that config-level except_panels also works correctly
    Config::set('filament-privacy-blur.except_panels', ['public', 'reports']);

    $publicPanel = Panel::make('public')->id('public');
    Filament::setCurrentPanel($publicPanel);

    // Panel in config except list should bypass privacy
    expect(PrivacyConfigResolver::isPanelExcepted())->toBeTrue();

    // Create a decision for a field in the excepted panel
    $result = PrivacyDecisionResolver::resolveForColumn(
        'sensitive_data',
        PrivacyMode::BlurClick,
        isAuthorized: false,
        columnBlur: null,
        record: null,
        hiddenRoles: null,
        resourceClass: null,
        neverReveal: false
    );

    $decision = $result['decision'];

    expect($decision->hasPrivacyEffect())->toBeFalse();
});
