<?php

use Arseno25\FilamentPrivacyBlur\FilamentPrivacyBlurPlugin;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Panel;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;

it('applies private macro to form inputs with blur CSS', function () {
    $panel = Panel::make('test')->id('test')->plugin(FilamentPrivacyBlurPlugin::make());
    Filament::setCurrentPanel($panel);

    $input = TextInput::make('salary')->private();

    // TextInput supports extraInputAttributes, so we check that
    $attributes = $input->getExtraInputAttributes();

    expect($attributes)
        ->toHaveKey('class')
        ->and($attributes['class'])->toContain('fi-privacy-blur')
        ->and($attributes['data-privacy-input'])->toBe('true');
});

it('tests export masking fallback when route is export', function () {
    $panel = Panel::make('test')->id('test')->plugin(FilamentPrivacyBlurPlugin::make());
    Filament::setCurrentPanel($panel);

    $request = Request::create('/export', 'GET');
    $request->setRouteResolver(
        function () use ($request) {
            return (new Route('GET', '/export', []))->bind($request)->name('users.export');
        }
    );

    app()->instance('request', $request);

    $column = TextColumn::make('email')->private();
    $formatted = $column->formatState('user@example.com');

    expect($formatted)->toBe('********');
});

it('applies simple string mask for generic strategy', function () {
    $panel = Panel::make('test')->id('test')->plugin(FilamentPrivacyBlurPlugin::make());
    Filament::setCurrentPanel($panel);

    $column = TextColumn::make('phone')
        ->private()
        ->privacyMode('mask')
        ->maskUsing('phone');

    $formatted = $column->formatState('081234567890');

    expect($formatted)->toBe('0812****7890');
});

it('applies custom closure for dynamic masking', function () {
    $panel = Panel::make('test')->id('test')->plugin(FilamentPrivacyBlurPlugin::make());
    Filament::setCurrentPanel($panel);

    $column = TextColumn::make('account_number')
        ->private()
        ->privacyMode('mask')
        ->maskUsing(fn (string $state) => substr($state, 0, 4) . ' **** ****');

    $formatted = $column->formatState('123456789012');

    expect($formatted)->toBe('1234 **** ****');
});
