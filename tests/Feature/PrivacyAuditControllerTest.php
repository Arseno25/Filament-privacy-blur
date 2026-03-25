<?php

use Arseno25\FilamentPrivacyBlur\Models\PrivacyRevealLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;

use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

it('logs audit reveal request', function () {
    Config::set('filament-privacy-blur.audit_enabled', true);

    $this->withoutMiddleware();

    postJson('/filament-privacy-blur/audit', [
        'column' => 'salary',
        'record_id' => '123',
        'mode' => 'blur_click',
    ])->assertSuccessful();

    $log = PrivacyRevealLog::first();
    expect($log)->not->toBeNull()
        ->and($log->column_name)->toBe('salary')
        ->and($log->record_key)->toBe('123')
        ->and($log->reveal_mode)->toBe('blur_click');
});

it('does not log if audit is disabled', function () {
    Config::set('filament-privacy-blur.audit_enabled', false);

    $this->withoutMiddleware();

    postJson('/filament-privacy-blur/audit', [
        'column' => 'salary',
        'record_id' => '123',
        'mode' => 'blur_click',
    ])->assertSuccessful();

    expect(PrivacyRevealLog::count())->toBe(0);
});

it('accepts resource and panel context in audit request', function () {
    Config::set('filament-privacy-blur.audit_enabled', true);

    $this->withoutMiddleware();

    postJson('/filament-privacy-blur/audit', [
        'column' => 'email',
        'record_id' => '456',
        'mode' => 'blur_hover',
        'resource' => 'App\\Filament\\Resources\\EmployeeResource',
        'panel' => 'admin',
    ])->assertSuccessful();

    $log = PrivacyRevealLog::first();
    expect($log)->not->toBeNull()
        ->and($log->column_name)->toBe('email')
        ->and($log->reveal_mode)->toBe('blur_hover')
        ->and($log->resource)->toBe('App\\Filament\\Resources\\EmployeeResource');
});

it('returns skipped status when audit is disabled at plugin level', function () {
    Config::set('filament-privacy-blur.audit_enabled', false);

    $this->withoutMiddleware();

    $response = postJson('/filament-privacy-blur/audit', [
        'column' => 'salary',
        'record_id' => '123',
        'mode' => 'blur_click',
    ]);

    $response->assertSuccessful()
        ->assertJson(['status' => 'skipped']);
});
