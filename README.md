<h1 align="center">
  <img src="https://banners.beyondco.de/Filament%20Privacy%20Blur.png?theme=light&packageManager=composer+require&packageName=Arseno25%2Ffilament-privacy-blur&pattern=temple&style=style_2&description=&md=1&showWatermark=0&fontSize=100px&images=eye-off&widths=800&heights=800" alt="Filament Privacy Blur">
</h1>

<p align="center">
  <a href="https://github.com/arseno25/filament-privacy-blur/releases">
    <img src="https://img.shields.io/github/v/release/arseno25/filament-privacy-blur?style=flat-square" alt="Latest Release">
  </a>
  <a href="https://packagist.org/packages/arseno25/filament-privacy-blur">
    <img src="https://img.shields.io/packagist/dt/arseno25/filament-privacy-blur?style=flat-square" alt="Total Downloads">
  </a>
  <a href="https://github.com/arseno25/filament-privacy-blur/blob/main/LICENSE.md">
    <img src="https://img.shields.io/github/license/arseno25/filament-privacy-blur?style=flat-square" alt="License">
  </a>
</p>

## About

**Filament Privacy Blur** provides visual privacy protection for sensitive data in Filament admin panels. It helps prevent accidental exposure during screen sharing, shoulder surfing, or working in public spaces.

### What It Does

- **Visual Blur** — CSS-based blur that requires user interaction to reveal
- **Data Masking** — Server-side redaction using configurable strategies (email, phone, NIK, etc.)
- **Interactive Reveal** — Click-to-reveal or hover-to-reveal with automatic re-blur
- **Global Reveal Toggle** — Eye icon in topbar to reveal all authorized fields instantly
- **Authorization-First** — Full integration with Laravel Gates, Policies, and permissions
- **Audit Logging** — Optional tracking of reveal actions with user, IP, and context
- **Export Safety** — Automatic masking during Filament exports

### What It Does NOT Do

This is a **visual privacy layer only**. It does NOT provide:
- Data encryption at rest or in transit
- Backend access control or API-level data redaction
- Database-level security
- Protection against determined attackers with developer tools

**Blur modes keep original data in the DOM.** For highly sensitive fields, use **mask mode** (server-side redaction) or implement data masking at your model/API layer.

## Why This Package Exists

When building Filament admin panels, you often need to display sensitive data (emails, salaries, phone numbers) while reducing the risk of accidental exposure during:
- Screen sharing in meetings or presentations
- Working in public spaces (cafes, coworking spaces)
- Pair programming sessions
- Client demonstrations

This package provides a convenient, authorization-aware way to add visual privacy without manually implementing blur logic for every field.

## Features

- **🔒 7 Privacy Modes** — `blur`, `blur_click`, `blur_hover`, `blur_auth`, `mask`, `hybrid`, `disabled`
- **👆 Interactive Reveal** — Click-to-reveal (auto re-blurs after 5s) or hover-to-reveal
- **👁️ Global Reveal Toggle** — Topbar button to reveal all authorized fields instantly
- **🎭 8 Mask Strategies** — email, phone, NIK, full name, API key, address, currency, generic
- **🛡️ Authorization-First** — Secure-by-default using Laravel Gates, Policies, and abilities
- **📊 Audit Logging** — Track reveal actions with user, IP address, user agent, and resource context
- **📤 Export Safety** — Automatic masking during Filament exports
- **🎛️ Per-Panel Config** — Exclude specific panels or customize settings per panel

## Requirements

- **PHP**: 8.2 or higher
- **Laravel**: 11 or higher
- **Filament**: v4.x or v5.x

## Installation

```bash
composer require arseno25/filament-privacy-blur
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag="filament-privacy-blur-config"
```

Publish and run the migration (for audit logging):

```bash
php artisan vendor:publish --tag="filament-privacy-blur-migrations"
php artisan migrate
```

## Setup

Register the plugin in your Filament panel provider:

```php
use Arseno25\FilamentPrivacyBlur\FilamentPrivacyBlurPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugin(
            FilamentPrivacyBlurPlugin::make()
                ->defaultMode('blur_click')
                ->blurAmount(4)
                ->exceptColumns(['id', 'created_at', 'updated_at'])
                ->exceptPanels(['public'])
                ->enableAudit()
        );
}
```

### Plugin Configuration Options

| Method | Description | Default |
|--------|-------------|---------|
| `defaultMode(string $mode)` | Default privacy mode | `blur_click` |
| `blurAmount(int $amount)` | CSS blur intensity (1-10) | `4` |
| `exceptColumns(array $columns)` | Columns to exclude from privacy | `[]` |
| `exceptResources(array $resources)` | Resource classes to exclude | `[]` |
| `exceptPanels(array $panels)` | Panel IDs to exclude | `[]` |
| `enableAudit()` | Enable audit logging | disabled |
| `showGlobalRevealToggle()` | Show global reveal toggle | enabled |
| `hideGlobalRevealToggle()` | Hide global reveal toggle | - |

## Quick Start

### Blur a Table Column

```php
use Filament\Tables\Columns\TextColumn;

TextColumn::make('email')->private(),
```

### Mask a Table Column

```php
TextColumn::make('email')
    ->private()
    ->privacyMode('mask')
    ->maskUsing('email'),
```

### Reveal with Authorization

```php
TextColumn::make('salary')
    ->private()
    ->revealIfCan('view_sensitive_data'),
```

### Protect Form Inputs

```php
use Filament\Forms\Components\TextInput;

TextInput::make('email')->private(),
```

### Protect Infolist Entries

```php
use Filament\Infolists\Components\TextEntry;

TextEntry::make('phone')
    ->private()
    ->maskUsing('phone'),
```

### Never Reveal (Maximum Security)

```php
TextEntry::make('api_key')
    ->private()
    ->revealNever()
    ->privacyMode('mask')
    ->maskUsing('api_key'),
```

## How It Works

The package makes privacy decisions server-side and renders HTML data attributes that guide frontend behavior:

- **Server-side decisions** — Authorization, mode, and reveal capability are determined server-side
- **Client-side execution** — Alpine.js respects server-rendered attributes and cannot override them
- **Secure-by-default** — Fields with `->private()` but no explicit authorization will blur for everyone and allow NO reveal
- **Blur vs Mask** — Blur modes keep original data in DOM (hidden by CSS), mask modes replace data server-side

## Authorization

This package is **ability-first** — it prioritizes Laravel's built-in authorization system.

### Recommended: Ability-Based Authorization

Use `revealIfCan()` with Laravel Gates or Policies:

```php
// Using a Gate ability
Gate::define('view_ssn', function ($user, $record) {
    return $user->isAdmin() || $user->id === $record->user_id;
});

TextColumn::make('ssn')
    ->private()
    ->revealIfCan('view_ssn', $record),
```

### Custom Closure Authorization

```php
TextColumn::make('salary')
    ->private()
    ->authorizeRevealUsing(function ($user, $record) {
        return $user?->is_admin
            || $user?->department_id === $record?->department_id;
    }),
```

### Permission-Based Authorization

```php
// Single permission
TextColumn::make('data')->private()->permission('view_data'),

// Multiple permissions (any match)
TextColumn::make('admin_field')
    ->private()
    ->visibleToPermissions(['view_admin', 'edit_admin']),
```

### Role-Based Authorization (Optional)

```php
// Single role
TextColumn::make('secret')->private()->visibleToRoles(['admin']),

// Multiple roles
TextColumn::make('internal')
    ->private()
    ->visibleToRoles(['admin', 'manager']),
```

### Force Blur for Specific Roles

```php
TextColumn::make('internal_notes')
    ->private()
    ->revealIfCan('view_internal')
    ->hiddenFromRoles(['guest', 'contractor']),
```

Users in these roles will always see blur, even if authorized via other means.

### Secure-by-Default in Practice

```php
// ❌ This will blur for EVERYONE, NO reveal allowed
TextColumn::make('email')->private()

// ✅ Only admins can reveal
TextColumn::make('email')
    ->private()
    ->visibleToRoles(['admin'])

// ✅ Users with 'view_email' permission can reveal
TextColumn::make('email')
    ->private()
    ->permission('view_email')
```

## Privacy Modes

### `blur` — Always Blurred

- **Authorized**: Plain text
- **Unauthorized**: 🔒 Blurred, no reveal
- **Raw data in DOM**: Yes

```php
TextColumn::make('notes')->private()->privacyMode('blur'),
```

### `blur_click` — Click to Reveal (Default)

- **Authorized**: 🔒 Blurred, click to reveal (auto re-blurs after 5s)
- **Unauthorized**: 🔒 Blurred, no click
- **Raw data in DOM**: Yes

```php
TextColumn::make('email')->private()->privacyMode('blur_click'),
```

### `blur_hover` — Hover to Reveal

- **Authorized**: 🔒 Blurred, hover to reveal
- **Unauthorized**: 🔒 Blurred, no hover
- **Raw data in DOM**: Yes

```php
TextColumn::make('address')->private()->revealOnHover(),
```

### `blur_auth` — Blur for Unauthorized Only

- **Authorized**: Plain text
- **Unauthorized**: 🔒 Blurred, no reveal
- **Raw data in DOM**: Yes

```php
TextColumn::make('internal_notes')
    ->private()
    ->privacyMode('blur_auth')
    ->revealIfCan('view_internal'),
```

### `mask` — Server-Side Masking

- **Authorized**: Plain text
- **Unauthorized**: 🎭 Masked text (e.g., `j***e@example.com`)
- **Raw data in DOM**: No

```php
TextColumn::make('email')
    ->private()
    ->privacyMode('mask')
    ->maskUsing('email'),
```

### `hybrid` — Maximum Protection

- **Authorized**: 🎭 Masked text
- **Unauthorized**: 🎭 Masked + 🔒 blurred
- **Raw data in DOM**: Masked only

```php
TextColumn::make('ssn')
    ->private()
    ->privacyMode('hybrid')
    ->maskUsing('nik'),
```

### `disabled` — No Privacy

- **All users**: Plain text
- **Privacy effect**: None

```php
TextColumn::make('public_field')->private()->privacyMode('disabled'),
```

### Mode Behavior Table

| Mode | Authorized | Unauthorized | Interactive Reveal | Global Reveal | Raw Data in DOM |
|------|------------|--------------|-------------------|--------------|-----------------|
| `disabled` | Plain | Plain | N/A | N/A | Yes |
| `blur` | Plain | 🔒 Blur | No | No | Yes |
| `blur_click` | 🔒 Blur → Click | 🔒 Blur | Yes (auth only) | Yes (auth only) | Yes |
| `blur_hover` | 🔒 Blur → Hover | 🔒 Blur | Yes (auth only) | Yes (auth only) | Yes |
| `blur_auth` | Plain | 🔒 Blur | No | No | Yes |
| `mask` | Plain | 🎭 Masked | No | No | No |
| `hybrid` | 🎭 Masked | 🎭 Masked + 🔒 Blur | No | No | Masked only |

## Global Reveal Toggle

The global reveal toggle appears as an eye icon button in the Filament topbar.

### When Does the Toggle Appear?

- **Shows**: When at least one field on the page can be globally revealed
- **Hides**: When no globally revealable fields exist on the current page

This happens automatically via Alpine.js — no configuration needed.

### What Can Be Globally Revealed?

The toggle can only reveal fields that:
1. The current user is authorized to view (via `revealIfCan()`, `permission()`, etc.)
2. The field is NOT marked as `revealNever()`
3. The field is NOT in `hiddenFromRoles()` for the current user
4. The privacy mode supports global reveal (`blur_click`, `blur_hover`)

### What Cannot Be Globally Revealed?

The toggle will NEVER reveal:
- ❌ Unauthorized fields (user lacks ability/permission)
- ❌ `revealNever()` fields
- ❌ `mask` mode fields (masked server-side, no blur to remove)
- ❌ `hybrid` mode fields
- ❌ Fields where user is in `hiddenFromRoles()`

### Security Guarantee

The global reveal toggle **cannot bypass authorization**. Only fields where the server explicitly sets `data-privacy-can-globally-reveal="true"` will be revealed. The frontend Alpine.js code only respects server decisions.

## Mask Strategies

| Strategy | Example Output | Description |
|----------|---------------|-------------|
| `email` | `j***e@example.com` | Shows first char, masks middle, shows domain |
| `phone` | `0812****7890` | Shows prefix, masks middle digits, shows suffix |
| `nik` | `3173********9012` | Shows first 4 and last 4 digits |
| `full_name` | `Jo** Do*` | Shows first 2 and last 2 chars of each word |
| `api_key` | `sk_***_key` | Shows first 3 and last 3 chars |
| `address` | `Jl. Sudirma***` | Shows first 12 characters |
| `currency` | `***` | Masks currency values entirely |
| `generic` | `J***h` | Shows first and last character |

### Using Mask Strategies

```php
// Built-in strategy
TextColumn::make('email')->private()->maskUsing('email'),

// Custom closure
TextColumn::make('account_number')
    ->private()
    ->maskUsing(fn ($state) => substr($state, 0, 4) . ' ****'),
```

## Audit Logging

When enabled, the package logs reveal actions to the `privacy_reveal_logs` table.

### When Does Audit Logging Happen?

Audit logging occurs when:
1. Audit is enabled at plugin level (`->enableAudit()`)
2. Audit is not disabled for the specific field (`->withoutAuditReveal()`)
3. A reveal action is performed (click, hover, or global toggle)
4. The user is authorized to perform the reveal

### Stored Audit Fields

| Field | Description |
|-------|-------------|
| `user_id` | ID of the user who revealed the data |
| `tenant_id` | Tenant ID for multi-tenant apps |
| `panel_id` | Filament panel ID |
| `resource` | Resource identifier (e.g., `App\Filament\Resources\UserResource`) |
| `page` | Full URL of the page where reveal occurred |
| `column_name` | Name of the column that was revealed |
| `record_key` | Primary key of the record |
| `reveal_mode` | Privacy mode used (e.g., `blur_click`) |
| `ip_address` | IP address of the user |
| `user_agent` | Browser/user agent string |
| `created_at` | Timestamp of the reveal action |

### Enabling Audit Logging

```php
FilamentPrivacyBlurPlugin::make()->enableAudit(),
```

### Per-Field Audit Control

```php
// Disable audit for specific field
TextColumn::make('public_field')
    ->private()
    ->withoutAuditReveal(),
```

## Export Safety

### Why Visual Blur is Not Safe for Exports

CSS blur only affects visual rendering in the browser. When exporting data to CSV/Excel, the original data is included unless explicitly masked.

### How Export Context is Handled

The package automatically detects Filament export contexts and:
1. **Replaces blur with masking** — `blur_click` fields are masked using the configured strategy
2. **Preserves mask strategies** — Custom masking strategies are applied during export
3. **Protects sensitive data** — Original values are never included in exports

### Export Behavior Examples

```php
// In browser: Shows blurred with click-to-reveal
// In export: Shows as "j***e@example.com"
TextColumn::make('email')->private()->maskUsing('email'),

// In browser: Shows blurred with click-to-reveal
// In export: Shows as "0812****7890"
TextColumn::make('phone')->private()->maskUsing('phone'),
```

## Security Limitations

### Important: What This Package Does NOT Provide

1. **Not Encryption** — Blur modes keep original data in the DOM
2. **Not Backend Access Control** — Does not replace API authentication
3. **Not API-Level Redaction** — Does not protect JSON API endpoints
4. **Not Database Security** — Does not encrypt data at rest

### Recommended Usage by Data Sensitivity

| Data Type | Recommended Mode | Reason |
|-----------|-----------------|---------|
| **Email** | `mask` or `blur_click` | Mask for high sensitivity, blur for medium |
| **Phone** | `mask` or `blur_click` | Mask for high sensitivity, blur for medium |
| **Salary** | `blur_click` with auth | Blur with strict authorization |
| **SSN/Tax ID** | `mask` + `revealNever()` | Always mask, never reveal |
| **API Keys** | `mask` + `revealNever()` | Maximum security |
| **Internal Notes** | `blur_auth` with auth | Blur for unauthorized, clear for authorized |

### For Highly Sensitive Data

Consider implementing **backend data redaction**:
- Mask data in model accessors
- Use API resources with conditional field inclusion
- Implement field-level encryption in your database
- Use Laravel's `Hidden` attribute on Eloquent models

## API Reference

### Column/Entry/Field Macros

#### `private()`

Enable privacy with default settings (equivalent to `privacyMode('blur_click')`):

```php
TextColumn::make('email')->private()
```

#### `privacyMode(string $mode)`

Set the privacy mode:

```php
TextColumn::make('salary')
    ->private()
    ->privacyMode('blur_click'),
```

Available modes: `'blur'`, `'blur_click'`, `'blur_hover'`, `'blur_auth'`, `'mask'`, `'hybrid'`, `'disabled'`

#### `revealOnClick()` / `revealOnHover()`

Convenience methods for common modes:

```php
TextColumn::make('email')->revealOnClick(),
TextColumn::make('address')->revealOnHover(),
```

#### `revealNever()`

Prevent all reveal methods:

```php
TextColumn::make('api_key')->private()->revealNever(),
```

#### `blurAmount(int $amount)`

Set CSS blur intensity (1-10):

```php
TextColumn::make('salary')->private()->blurAmount(8),
```

#### `maskUsing(string|Closure $strategy)`

Set masking strategy:

```php
TextColumn::make('email')->private()->maskUsing('email'),
TextColumn::make('custom')->private()->maskUsing(fn ($s) => $s[0] . '***'),
```

### Authorization Methods

#### `revealIfCan(string $ability, Model $record = null)`

Authorize via Laravel Gate or Policy (recommended):

```php
TextColumn::make('ssn')->private()->revealIfCan('view_ssn', $record),
```

#### `authorizeRevealWith(string $ability, Model $record = null)`

Alias for `revealIfCan()` with explicit semantics:

```php
TextColumn::make('notes')->private()->authorizeRevealWith('view_notes', $record),
```

#### `authorizeRevealUsing(Closure $callback)`

Custom authorization closure:

```php
TextColumn::make('salary')
    ->private()
    ->authorizeRevealUsing(fn ($user, $record) => $user?->id === $record->manager_id),
```

#### `permission(string $permission)`

Require specific permission:

```php
TextColumn::make('data')->private()->permission('view_data'),
```

#### `visibleToPermissions(array $permissions)`

Require any of the specified permissions:

```php
TextColumn::make('admin_field')
    ->private()
    ->visibleToPermissions(['view_admin', 'edit_admin']),
```

#### `visibleToRoles(array $roles)`

Require any of the specified roles:

```php
TextColumn::make('secret')->private()->visibleToRoles(['admin']),
```

#### `hiddenFromRoles(array $roles)`

Force blur for specific roles:

```php
TextColumn::make('internal')->private()->hiddenFromRoles(['guest']),
```

### Audit Methods

#### `auditReveal(bool $enabled = true)`

Enable audit for this field:

```php
TextColumn::make('salary')->private()->auditReveal(true),
```

#### `withoutAuditReveal()`

Disable audit for this field:

```php
TextColumn::make('public_field')->private()->withoutAuditReveal(),
```

### Plugin Configuration Methods

#### `defaultMode(string $mode)`

Set default privacy mode:

```php
FilamentPrivacyBlurPlugin::make()->defaultMode('blur_click'),
```

#### `blurAmount(int $amount)`

Set default blur intensity:

```php
FilamentPrivacyBlurPlugin::make()->blurAmount(6),
```

#### `exceptColumns(array $columns)`

Exclude specific columns:

```php
FilamentPrivacyBlurPlugin::make()->exceptColumns(['id', 'created_at']),
```

#### `exceptResources(array $resources)`

Exclude specific resources:

```php
FilamentPrivacyBlurPlugin::make()->exceptResources([App\Filament\Resources\PublicResource::class]),
```

#### `exceptPanels(array $panels)`

Exclude specific panels:

```php
FilamentPrivacyBlurPlugin::make()->exceptPanels(['public', 'reports']),
```

#### `enableAudit()`

Enable audit logging globally:

```php
FilamentPrivacyBlurPlugin::make()->enableAudit(),
```

#### `showGlobalRevealToggle()` / `hideGlobalRevealToggle()`

Control toggle visibility:

```php
FilamentPrivacyBlurPlugin::make()->showGlobalRevealToggle(),
FilamentPrivacyBlurPlugin::make()->hideGlobalRevealToggle(),
```

## Configuration

After publishing, edit `config/filament-privacy-blur.php`:

```php
return [
    'default_mode' => 'blur_click',
    'default_blur_amount' => 4,
    'default_mask_strategy' => 'generic',
    'except_columns' => ['id', 'created_at', 'updated_at'],
    'except_resources' => [],
    'except_panels' => [],
    'audit_enabled' => false,
    'icon_trigger_enabled' => true,
];
```

## Compatibility

| Component | Supported Versions |
|-----------|-------------------|
| **PHP** | 8.2, 8.3, 8.4 |
| **Laravel** | 11, 12 |
| **Filament** | v4.x, v5.x |
| **Alpine.js** | Bundled with Filament |

### Package Integration

| Package | Status | Notes |
|---------|--------|-------|
| **Filament Shield** | ✅ Compatible | Works via Laravel Gates |
| **Spatie Laravel Permission** | ✅ Compatible | Works via `can()` method |
| **Spatie Tenancy** | ✅ Compatible | Tenant ID captured in audit logs |

## Contributing

1. **Install dependencies**:
   ```bash
   composer install
   ```

2. **Run tests**:
   ```bash
   composer test
   ```

3. **Run static analysis**:
   ```bash
   composer analyse
   ```

4. **Run code style checks**:
   ```bash
   composer test:lint
   ```

5. **Fix code style**:
   ```bash
   composer lint
   ```

### Development Standards

- **PHPStan** — Static analysis at Level 4
- **Laravel Pint** — Laravel code style fixer
- **Pest** — Testing framework

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for recent changes.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

---

<div align="center">

**Made with ❤️ for the Filament community**

</div>
