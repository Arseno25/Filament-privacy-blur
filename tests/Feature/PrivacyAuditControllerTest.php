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

it('captures IP address from request', function () {
    Config::set('filament-privacy-blur.audit_enabled', true);

    $this->withoutMiddleware();

    postJson('/filament-privacy-blur/audit', [
        'column' => 'salary',
        'mode' => 'blur_click',
    ], [
        'REMOTE_ADDR' => '192.168.1.100',
    ])->assertSuccessful();

    $log = PrivacyRevealLog::first();
    expect($log)->not->toBeNull()
        ->and($log->ip_address)->toBe('192.168.1.100');
});

it('captures user agent from request', function () {
    Config::set('filament-privacy-blur.audit_enabled', true);

    $this->withoutMiddleware();

    postJson('/filament-privacy-blur/audit', [
        'column' => 'email',
        'mode' => 'blur_click',
    ], [], [
        'HTTP_USER_AGENT' => 'Mozilla/5.0 (Test Browser)',
    ])->assertSuccessful();

    $log = PrivacyRevealLog::first();
    expect($log)->not->toBeNull()
        ->and($log->user_agent)->toBe('Mozilla/5.0 (Test Browser)');
});

it('logs all metadata including IP and user agent', function () {
    Config::set('filament-privacy-blur.audit_enabled', true);

    $this->withoutMiddleware();

    postJson('/filament-privacy-blur/audit', [
        'column' => 'ssn',
        'record_id' => '789',
        'mode' => 'blur_click',
        'resource' => 'App\\Filament\\Resources\\EmployeeResource',
        'panel' => 'admin',
    ], [
        'REMOTE_ADDR' => '10.0.0.50',
    ], [
        'HTTP_USER_AGENT' => 'Mozilla/5.0 (Test Agent)',
    ])->assertSuccessful();

    $log = PrivacyRevealLog::first();
    expect($log)->not->toBeNull()
        ->and($log->column_name)->toBe('ssn')
        ->and($log->record_key)->toBe('789')
        ->and($log->reveal_mode)->toBe('blur_click')
        ->and($log->resource)->toBe('App\\Filament\\Resources\\EmployeeResource')
        ->and($log->panel_id)->toBe('admin')
        ->and($log->ip_address)->toBe('10.0.0.50')
        ->and($log->user_agent)->toBe('Mozilla/5.0 (Test Agent)');
});
