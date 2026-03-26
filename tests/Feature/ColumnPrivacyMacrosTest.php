<?php

use Arseno25\FilamentPrivacyBlur\Filament\ColumnPrivacyMacros;
use Arseno25\FilamentPrivacyBlur\FilamentPrivacyBlurPlugin;
use Arseno25\FilamentPrivacyBlur\Tests\TestCase;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Panel;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;

uses(TestCase::class);

it('registers private macro on form input without error', function () {
    $panel = Panel::make('test')->id('test')->plugin(FilamentPrivacyBlurPlugin::make());
    Filament::setCurrentPanel($panel);

    // Just verify the macro is callable and returns the component (fluent API)
    $input = TextInput::make('salary')->private();

    expect($input)->toBeInstanceOf(TextInput::class);
});

it('tests export masking uses mask strategy instead of hardcoded stars', function () {
    $panel = Panel::make('test')->id('test')->plugin(FilamentPrivacyBlurPlugin::make());
    Filament::setCurrentPanel($panel);

    $request = Request::create('/export', 'GET');
    $request->setRouteResolver(
        function () use ($request) {
            return (new Route('GET', '/export', []))->bind($request)->name('users.export');
        }
    );

    app()->instance('request', $request);

    // With email mask strategy, the export should use masking service
    $column = TextColumn::make('email')
        ->private()
        ->maskUsing('email');
    $formatted = $column->formatState('user@example.com');

    // Should be masked using email strategy, not hardcoded '********'
    expect($formatted)->toBe('u**r@example.com');
});

it('tests export masking with generic strategy fallback', function () {
    $panel = Panel::make('test')->id('test')->plugin(FilamentPrivacyBlurPlugin::make());
    Filament::setCurrentPanel($panel);

    $request = Request::create('/export', 'GET');
    $request->setRouteResolver(
        function () use ($request) {
            return (new Route('GET', '/export', []))->bind($request)->name('users.export');
        }
    );

    app()->instance('request', $request);

    // Without a specific mask strategy, it should use generic
    $column = TextColumn::make('email')->private();
    $formatted = $column->formatState('user@example.com');

    // Generic masking: first char + stars + last char
    expect($formatted)->toBe('u**************m');
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

it('detects export context correctly', function () {
    // Test non-export context
    $request = Request::create('/admin/users', 'GET');
    $request->setRouteResolver(function () use ($request) {
        return (new Route('GET', '/admin/users', []))->bind($request)->name('admin.users.index');
    });
    app()->instance('request', $request);

    expect(ColumnPrivacyMacros::isExportContext())->toBeFalse();

    // Test export route
    $exportRequest = Request::create('/users/export', 'GET');
    $exportRequest->setRouteResolver(function () use ($exportRequest) {
        return (new Route('GET', '/users/export', []))->bind($exportRequest)->name('users.export');
    });
    app()->instance('request', $exportRequest);

    expect(ColumnPrivacyMacros::isExportContext())->toBeTrue();

    // Test X-Filament-Export header
    $headerRequest = Request::create('/admin/users', 'GET');
    $headerRequest->headers->set('X-Filament-Export', 'true');
    app()->instance('request', $headerRequest);

    expect(ColumnPrivacyMacros::isExportContext())->toBeTrue();
});
