<?php

use Arseno25\FilamentPrivacyBlur\Enums\PrivacyMode;
use Arseno25\FilamentPrivacyBlur\Helpers\PrivacyMetadataHelper;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\HtmlString;

it('blurs only the masked value in hybrid mode and never puts the raw value in HTML', function () {
    $column = TextColumn::make('email')
        ->private()
        ->privacyMode('hybrid')
        ->maskUsing('email');

    $rendered = $column->formatState('johndoe@example.com');

    expect($rendered)->toBeInstanceOf(HtmlString::class);

    $html = $rendered->toHtml();

    expect($html)->toContain('fi-privacy-blur')
        ->toContain('j*****e@example.com')
        ->not->toContain('johndoe@example.com');
});

it('keeps mask mode when revealNever is chained after privacyMode', function () {
    $column = TextColumn::make('email')
        ->private()
        ->privacyMode('mask')
        ->revealNever()
        ->maskUsing('email');

    $metadata = PrivacyMetadataHelper::get($column);

    expect($metadata['privacy_mode'])->toBe(PrivacyMode::Mask)
        ->and($metadata['privacy_never_reveal'])->toBeTrue();

    expect($column->formatState('johndoe@example.com'))->toBe('j*****e@example.com');
});

it('keeps mask mode when privacyMode is chained after revealNever', function () {
    $column = TextColumn::make('email')
        ->private()
        ->revealNever()
        ->privacyMode('mask')
        ->maskUsing('email');

    $metadata = PrivacyMetadataHelper::get($column);

    expect($metadata['privacy_mode'])->toBe(PrivacyMode::Mask)
        ->and($metadata['privacy_never_reveal'])->toBeTrue();

    expect($column->formatState('johndoe@example.com'))->toBe('j*****e@example.com');
});
