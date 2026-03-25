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
