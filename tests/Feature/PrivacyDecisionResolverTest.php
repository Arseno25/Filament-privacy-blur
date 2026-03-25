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
