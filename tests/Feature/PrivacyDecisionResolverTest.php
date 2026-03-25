<?php

use Arseno25\FilamentPrivacyBlur\Enums\PrivacyMode;
use Arseno25\FilamentPrivacyBlur\Resolvers\PrivacyDecisionResolver;
use Filament\Panel;

it('returns empty decision if globally disabled', function () {
    // Assuming panel is not registered in testing without setup
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
