<?php

use Arseno25\FilamentPrivacyBlur\Filament\ColumnPrivacyMacros;
use Arseno25\FilamentPrivacyBlur\FilamentPrivacyBlurPlugin;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Panel;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\HtmlString;

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

    // Simulate a real export request
    $request = Request::create('/users/export', 'GET');
    $request->setRouteResolver(
        function () use ($request) {
            return (new Route('GET', '/users/export', []))->bind($request)->name('filament.admin.users.export');
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

it('properly detects export context in real Filament export scenarios', function () {
    $panel = Panel::make('admin')->id('admin')->plugin(FilamentPrivacyBlurPlugin::make());
    Filament::setCurrentPanel($panel);

    // Test various Filament export URL patterns
    $exportUrls = [
        '/users/export',
        '/admin/users/export',
        '/filament/admin/users/export',
        'https://example.com/users/export',
    ];

    foreach ($exportUrls as $url) {
        $request = Request::create($url, 'GET');
        $request->setRouteResolver(
            function () use ($request) {
                return (new Route('GET', '/export', []))->bind($request)->name('users.export');
            }
        );

        app()->instance('request', $request);

        $column = TextColumn::make('salary')
            ->private()
            ->maskUsing('email');

        // Export context should be detected and masking should be applied
        $formatted = $column->formatState('high-salary@example.com');
        expect($formatted)->not->toBe('high-salary@example.com')
            ->and($formatted)->toContain('**'); // Should be masked
    }
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

it('export context masks blur fields instead of exposing raw data', function () {
    $panel = Panel::make('admin')->id('admin')->plugin(FilamentPrivacyBlurPlugin::make());
    Filament::setCurrentPanel($panel);

    // Simulate a real export request
    $request = Request::create('/admin/employees/export', 'GET');
    $request->setRouteResolver(
        function () use ($request) {
            return (new Route('GET', '/admin/employees/export', []))
                ->bind($request)
                ->name('filament.admin.resources.employees.export');
        }
    );

    app()->instance('request', $request);

    // In export context, blur_click mode should apply masking instead of blur
    $blurClickColumn = TextColumn::make('email')
        ->private()
        ->privacyMode('blur_click');

    $formatted = $blurClickColumn->formatState('john.doe@company.com');

    // Export should mask the email, not return raw data
    expect($formatted)->not->toBe('john.doe@company.com')
        ->and($formatted)->toContain('**'); // Masked output
});

it('export context preserves mask strategy configuration', function () {
    $panel = Panel::make('admin')->id('admin')->plugin(FilamentPrivacyBlurPlugin::make());
    Filament::setCurrentPanel($panel);

    // Create export request
    $request = Request::create('/users/export', 'GET');
    $request->setRouteResolver(
        function () use ($request) {
            return (new Route('GET', '/users/export', []))
                ->bind($request)
                ->name('users.export');
        }
    );

    app()->instance('request', $request);

    // Test that custom mask strategies are preserved in export context
    $phoneColumn = TextColumn::make('phone')
        ->private()
        ->maskUsing('phone');

    $emailColumn = TextColumn::make('email')
        ->private()
        ->maskUsing('email');

    $genericColumn = TextColumn::make('address')
        ->private();

    expect($phoneColumn->formatState('081234567890'))->toBe('0812****7890')
        ->and($emailColumn->formatState('user@example.com'))->toContain('**')
        ->and($genericColumn->formatState('Some Address'))->toContain('***');
});

it('non-export routes do not apply export masking', function () {
    $panel = Panel::make('admin')->id('admin')->plugin(FilamentPrivacyBlurPlugin::make());
    Filament::setCurrentPanel($panel);

    // Regular index route (not export)
    $request = Request::create('/admin/users', 'GET');
    $request->setRouteResolver(
        function () use ($request) {
            return (new Route('GET', '/admin/users', []))
                ->bind($request)
                ->name('filament.admin.resources.users.index');
        }
    );

    app()->instance('request', $request);

    // In regular view, blur_click should preserve original data (blur is client-side)
    $column = TextColumn::make('email')
        ->private()
        ->privacyMode('blur_click');

    $formatted = $column->formatState('user@example.com');

    // Non-export context returns HtmlString with blur classes (client-side blur)
    expect($formatted)->toBeInstanceOf(HtmlString::class);
    expect($formatted->toHtml())->toContain('user@example.com');
    expect($formatted->toHtml())->toContain('fi-privacy-blur');
});
