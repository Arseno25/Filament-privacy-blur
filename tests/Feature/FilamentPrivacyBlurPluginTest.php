<?php

use Arseno25\FilamentPrivacyBlur\FilamentPrivacyBlurPlugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;

it('registers render hooks inside panel boot method', function () {
    $plugin = FilamentPrivacyBlurPlugin::make();
    $panel = Panel::make('test')->id('test')->plugin($plugin);
    Filament::setCurrentPanel($panel);

    // Verify the hooks output the correct HTML strings
    $globalSearchOutput = FilamentView::renderHook(PanelsRenderHook::GLOBAL_SEARCH_AFTER);
    $bodyEndOutput = FilamentView::renderHook(PanelsRenderHook::BODY_END);

    expect($globalSearchOutput->toHtml())->toBeString()
        ->and($bodyEndOutput->toHtml())->toBeString();
});
