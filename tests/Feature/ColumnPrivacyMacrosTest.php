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
