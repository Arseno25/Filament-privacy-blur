<?php

use Arseno25\FilamentPrivacyBlur\Models\PrivacyRevealLog;
use Illuminate\Foundation\Auth\User;
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
    ], [
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

it('validates that column is required', function () {
    Config::set('filament-privacy-blur.audit_enabled', true);

    $this->withoutMiddleware();

    postJson('/filament-privacy-blur/audit', [
        'mode' => 'blur_click',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['column']);
});

it('validates that mode is required', function () {
    Config::set('filament-privacy-blur.audit_enabled', true);

    $this->withoutMiddleware();

    postJson('/filament-privacy-blur/audit', [
        'column' => 'email',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['mode']);
});

it('allows optional fields to be nullable', function () {
    Config::set('filament-privacy-blur.audit_enabled', true);

    $this->withoutMiddleware();

    postJson('/filament-privacy-blur/audit', [
        'column' => 'salary',
        'mode' => 'blur_click',
        // record_id, resource, panel are optional
    ])->assertSuccessful();

    $log = PrivacyRevealLog::first();
    expect($log)->not->toBeNull()
        ->and($log->column_name)->toBe('salary')
        ->and($log->record_key)->toBeNull()
        ->and($log->resource)->toBeNull()
        ->and($log->panel_id)->toBeNull();
});

it('captures authenticated user_id correctly', function () {
    Config::set('filament-privacy-blur.audit_enabled', true);

    $user = new class extends User
    {
        protected $fillable = ['id', 'name'];

        public $id = 42;

        public $name = 'Test User';
    };

    $this->actingAs($user);
    $this->withoutMiddleware();

    postJson('/filament-privacy-blur/audit', [
        'column' => 'email',
        'mode' => 'blur_click',
    ])->assertSuccessful();

    $log = PrivacyRevealLog::first();
    expect($log)->not->toBeNull()
        ->and($log->user_id)->toBe(42);
});

it('stores null user_id when not authenticated', function () {
    Config::set('filament-privacy-blur.audit_enabled', true);

    auth()->logout();
    $this->withoutMiddleware();

    postJson('/filament-privacy-blur/audit', [
        'column' => 'email',
        'mode' => 'blur_click',
    ])->assertSuccessful();

    $log = PrivacyRevealLog::first();
    expect($log)->not->toBeNull()
        ->and($log->user_id)->toBeNull();
});

it('captures page url from referrer', function () {
    Config::set('filament-privacy-blur.audit_enabled', true);

    $this->withoutMiddleware();

    postJson('/filament-privacy-blur/audit', [
        'column' => 'salary',
        'mode' => 'blur_click',
    ], ['HTTP_REFERER' => 'https://example.com/admin/employees'])->assertSuccessful();

    $log = PrivacyRevealLog::first();
    expect($log)->not->toBeNull()
        ->and($log->page)->toBe('https://example.com/admin/employees');
});

it('stores all core fields in database correctly', function () {
    Config::set('filament-privacy-blur.audit_enabled', true);

    $user = new class extends User
    {
        protected $fillable = ['id', 'name'];

        public $id = 123;

        public $name = 'Admin User';
    };

    $this->actingAs($user);
    $this->withoutMiddleware();

    postJson('/filament-privacy-blur/audit', [
        'column' => 'ssn',
        'record_id' => 'employee-456',
        'mode' => 'blur_click',
        'resource' => 'App\\Filament\\Resources\\EmployeeResource',
        'panel' => 'admin-panel',
    ], [
        'REMOTE_ADDR' => '192.168.100.50',
        'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
    ])->assertSuccessful();

    $log = PrivacyRevealLog::first();
    expect($log)->not->toBeNull()
        ->and($log->user_id)->toBe(123)
        ->and($log->tenant_id)->toBeNull() // No tenant in this test
        ->and($log->panel_id)->toBe('admin-panel')
        ->and($log->resource)->toBe('App\\Filament\\Resources\\EmployeeResource')
        ->and($log->column_name)->toBe('ssn')
        ->and($log->record_key)->toBe('employee-456')
        ->and($log->reveal_mode)->toBe('blur_click')
        ->and($log->ip_address)->toBe('192.168.100.50')
        ->and($log->user_agent)->toBe('Mozilla/5.0 (Windows NT 10.0; Win64; x64)')
        ->and($log->created_at)->not->toBeNull();
});

it('validates required fields and returns proper validation errors', function () {
    Config::set('filament-privacy-blur.audit_enabled', true);
    $this->withoutMiddleware();

    // Test missing 'column' field
    postJson('/filament-privacy-blur/audit', [
        'mode' => 'blur_click',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['column']);

    // Test missing 'mode' field
    postJson('/filament-privacy-blur/audit', [
        'column' => 'email',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['mode']);

    // Test both missing
    postJson('/filament-privacy-blur/audit', [])->assertUnprocessable()
        ->assertJsonValidationErrors(['column', 'mode']);

    // Verify no audit logs were created for invalid requests
    expect(PrivacyRevealLog::count())->toBe(0);
});

it('proves audit logging only happens when audit is enabled', function () {
    // Test with audit DISABLED
    Config::set('filament-privacy-blur.audit_enabled', false);
    $this->withoutMiddleware();

    postJson('/filament-privacy-blur/audit', [
        'column' => 'salary',
        'mode' => 'blur_click',
    ])->assertSuccessful()
        ->assertJson(['status' => 'skipped']);

    expect(PrivacyRevealLog::count())->toBe(0);

    // Test with audit ENABLED
    Config::set('filament-privacy-blur.audit_enabled', true);

    postJson('/filament-privacy-blur/audit', [
        'column' => 'salary',
        'mode' => 'blur_click',
    ])->assertSuccessful()
        ->assertJson(['status' => 'success']);

    expect(PrivacyRevealLog::count())->toBe(1);

    $log = PrivacyRevealLog::first();
    expect($log->column_name)->toBe('salary')
        ->and($log->reveal_mode)->toBe('blur_click');
});

it('proves audit log stores complete request context', function () {
    // Comprehensive test proving all fields are correctly persisted
    Config::set('filament-privacy-blur.audit_enabled', true);

    $user = new class extends User
    {
        protected $fillable = ['id', 'name'];

        public $id = 999;

        public $name = 'Test User';
    };

    $this->actingAs($user);
    $this->withoutMiddleware();

    $testData = [
        'column' => 'customer_email',
        'record_id' => 'cust-789',
        'mode' => 'blur_hover',
        'resource' => 'App\\Filament\\Resources\\CustomerResource',
        'panel' => 'sales-panel',
    ];

    $testServer = [
        'REMOTE_ADDR' => '10.20.30.40',
        'HTTP_USER_AGENT' => 'Mozilla/5.0 (Test Browser)',
        'HTTP_REFERER' => 'https://example.com/admin/customers',
    ];

    postJson('/filament-privacy-blur/audit', $testData, $testServer)->assertSuccessful();

    $log = PrivacyRevealLog::first();

    // Verify all request data is persisted
    expect($log)->not->toBeNull()
        ->and($log->user_id)->toBe(999)
        ->and($log->column_name)->toBe('customer_email')
        ->and($log->record_key)->toBe('cust-789')
        ->and($log->reveal_mode)->toBe('blur_hover')
        ->and($log->resource)->toBe('App\\Filament\\Resources\\CustomerResource')
        ->and($log->panel_id)->toBe('sales-panel')
        ->and($log->ip_address)->toBe('10.20.30.40')
        ->and($log->user_agent)->toBe('Mozilla/5.0 (Test Browser)')
        ->and($log->page)->toBe('https://example.com/admin/customers')
        ->and($log->tenant_id)->toBeNull()
        ->and($log->created_at)->not->toBeNull()
        ->and($log->updated_at)->not->toBeNull();

    // Verify only ONE log entry was created
    expect(PrivacyRevealLog::count())->toBe(1);
});
