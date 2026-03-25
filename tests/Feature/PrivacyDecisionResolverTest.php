<?php

use Arseno25\FilamentPrivacyBlur\Enums\PrivacyMode;
use Arseno25\FilamentPrivacyBlur\Resolvers\PrivacyDecisionResolver;
use Illuminate\Support\Facades\Config;

it('returns empty decision if globally disabled', function () {
    // Explicitly disable for this test
    Config::set('filament-privacy-blur.enabled', false);

    $decision = PrivacyDecisionResolver::resolveForColumn('email', null, false);

    expect($decision['should_blur'])->toBeFalse()
        ->and($decision['should_mask'])->toBeFalse();
});

it('resolves disabled mode correctly', function () {
    // Override column mode to disabled
    $decision = PrivacyDecisionResolver::resolveForColumn('email', PrivacyMode::Disabled, false);

    expect($decision['should_blur'])->toBeFalse()
        ->and($decision['should_mask'])->toBeFalse();
});

it('allows authorized user to reveal in blur_click mode', function () {
    $decision = PrivacyDecisionResolver::resolveForColumn('email', PrivacyMode::BlurClick, true);

    expect($decision['should_blur'])->toBeTrue()
        ->and($decision['reveal_enabled'])->toBeTrue()
        ->and($decision['mode'])->toBe(PrivacyMode::BlurClick);
});

it('prevents unauthorized user from revealing in blur_click mode', function () {
    $decision = PrivacyDecisionResolver::resolveForColumn('email', PrivacyMode::BlurClick, false);

    expect($decision['should_blur'])->toBeTrue()
        ->and($decision['reveal_enabled'])->toBeFalse()
        ->and($decision['mode'])->toBe(PrivacyMode::BlurClick);
});

it('allows authorized user to reveal in blur_hover mode', function () {
    $decision = PrivacyDecisionResolver::resolveForColumn('email', PrivacyMode::BlurHover, true);

    expect($decision['should_blur'])->toBeTrue()
        ->and($decision['reveal_enabled'])->toBeTrue()
        ->and($decision['mode'])->toBe(PrivacyMode::BlurHover);
});

it('prevents unauthorized user from revealing in blur_hover mode', function () {
    $decision = PrivacyDecisionResolver::resolveForColumn('email', PrivacyMode::BlurHover, false);

    expect($decision['should_blur'])->toBeTrue()
        ->and($decision['reveal_enabled'])->toBeFalse()
        ->and($decision['mode'])->toBe(PrivacyMode::BlurHover);
});

it('prevents unauthorized user from revealing in blur_auth mode', function () {
    $decision = PrivacyDecisionResolver::resolveForColumn('email', PrivacyMode::BlurAuth, false);

    expect($decision['should_blur'])->toBeTrue()
        ->and($decision['reveal_enabled'])->toBeFalse();
});

it('authorized user bypasses blur entirely in blur_auth mode', function () {
    $decision = PrivacyDecisionResolver::resolveForColumn('email', PrivacyMode::BlurAuth, true);

    expect($decision['should_blur'])->toBeFalse()
        ->and($decision['should_mask'])->toBeFalse();
});

it('skips blur for excepted column', function () {
    Config::set('filament-privacy-blur.except_columns', ['id', 'created_at']);

    $decision = PrivacyDecisionResolver::resolveForColumn('id', PrivacyMode::BlurClick, false);

    expect($decision['should_blur'])->toBeFalse()
        ->and($decision['should_mask'])->toBeFalse();
});

it('skips blur for excepted resource', function () {
    Config::set('filament-privacy-blur.except_resources', ['App\\Filament\\Resources\\PublicResource']);

    $decision = PrivacyDecisionResolver::resolveForColumn(
        'email',
        PrivacyMode::BlurClick,
        false,
        null,
        null,
        null,
        'App\\Filament\\Resources\\PublicResource'
    );

    expect($decision['should_blur'])->toBeFalse()
        ->and($decision['should_mask'])->toBeFalse();
});

it('applies blur for non-excepted resource', function () {
    Config::set('filament-privacy-blur.except_resources', ['App\\Filament\\Resources\\PublicResource']);

    $decision = PrivacyDecisionResolver::resolveForColumn(
        'email',
        PrivacyMode::BlurClick,
        false,
        null,
        null,
        null,
        'App\\Filament\\Resources\\PrivateResource'
    );

    expect($decision['should_blur'])->toBeTrue();
});

it('masks data for unauthorized user in mask mode', function () {
    $decision = PrivacyDecisionResolver::resolveForColumn('email', PrivacyMode::Mask, false);

    expect($decision['should_mask'])->toBeTrue()
        ->and($decision['should_blur'])->toBeFalse()
        ->and($decision['reveal_enabled'])->toBeFalse();
});

it('authorized user bypasses mask mode entirely', function () {
    $decision = PrivacyDecisionResolver::resolveForColumn('email', PrivacyMode::Mask, true);

    expect($decision['should_mask'])->toBeFalse()
        ->and($decision['should_blur'])->toBeFalse();
});

it('hybrid mode shows masked to authorized user', function () {
    $decision = PrivacyDecisionResolver::resolveForColumn('email', PrivacyMode::Hybrid, true);

    expect($decision['should_mask'])->toBeTrue()
        ->and($decision['should_blur'])->toBeFalse();
});

it('hybrid mode blurs and masks for unauthorized user', function () {
    $decision = PrivacyDecisionResolver::resolveForColumn('email', PrivacyMode::Hybrid, false);

    expect($decision['should_mask'])->toBeTrue()
        ->and($decision['should_blur'])->toBeTrue()
        ->and($decision['reveal_enabled'])->toBeFalse();
});
