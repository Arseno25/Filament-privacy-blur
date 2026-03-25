<?php

use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Arseno25\FilamentPrivacyBlur\FilamentPrivacyBlurPlugin;
use Filament\Facades\Filament;
use Filament\Panel;

it('applies private macro to form inputs with alpine logic', function () {
    $input = TextInput::make('salary')->private();

    // Simulate extraInputAttributes evaluation
    $attributes = $input->getExtraInputAttributes();

    expect($attributes)
        ->toHaveKey('x-data')
        ->and($attributes['x-data'])->toContain('isFocused: false')
        ->and($attributes['x-on:focus'])->toBe('isFocused = true')
        ->and($attributes['x-on:toggle-privacy-blur.window'])->toBe('isGlobalRevealed = !isGlobalRevealed');
});

it('tests export masking fallback when route is export', function () {
    $panel = Panel::make('test')->id('test')->plugin(FilamentPrivacyBlurPlugin::make());
    Filament::setCurrentPanel($panel);

    $request = Illuminate\Http\Request::create('/export', 'GET');
    $request->setRouteResolver(function () use ($request) {
            return (new Illuminate\Routing\Route('GET', '/export', []))->bind($request)->name('users.export');
        }
        );

        app()->instance('request', $request);

        $column = TextColumn::make('email')->private();
        $formatted = $column->formatState('user@example.com');

        expect($formatted)->toBe('********');    });
