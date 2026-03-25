<?php

use Arseno25\FilamentPrivacyBlur\Enums\PrivacyMode;
use Arseno25\FilamentPrivacyBlur\Resolvers\PrivacyConfigResolver;
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
