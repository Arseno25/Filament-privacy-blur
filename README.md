# Filament Privacy Blur

[![Latest Version on Packagist](https://img.shields.io/packagist/v/arseno25/filament-privacy-blur.svg?style=flat-square)](https://packagist.org/packages/arseno25/filament-privacy-blur)
[![Total Downloads](https://img.shields.io/packagist/dt/arseno25/filament-privacy-blur.svg?style=flat-square)](https://packagist.org/packages/arseno25/filament-privacy-blur)
[![License](https://img.shields.io/packagist/l/arseno25/filament-privacy-blur.svg?style=flat-square)](https://packagist.org/packages/arseno25/filament-privacy-blur)

A Filament v3 plugin that provides visual privacy protection for sensitive data. Apply blur and masking effects to table columns, form inputs, and infolist entries to prevent accidental exposure during screen sharing or shoulder surfing.

## Features

- **Visual Blur Protection** — CSS-based blur prevents casual observation during screen shares
- **Click to Reveal** — Click a blurred field to temporarily reveal it (auto re-blurs after 5 seconds)
- **Hover to Reveal** — Quick-peek by hovering your mouse over the field
- **Global Reveal Toggle** — An eye icon in the topbar lets authorized users reveal all blurred fields instantly
- **Data Masking** — Built-in mask strategies for email, phone, NIK, full name, API key, address, and generic text
- **Form Input Protection** — Apply blur to `TextInput`, `Textarea`, and other form fields (auto-clears on focus)
- **Authorization Gates** — Control who can reveal data using Spatie roles, permissions, Laravel policies, or custom closures
- **Audit Logging** — Track which user revealed which field, with IP address and timestamp
- **Export Safety** — Automatically masks blurred data during Filament exports

## Requirements

- PHP 8.2 or higher (8.3+ required for Filament v5.x)
- Laravel 11 or higher
- Filament v4.x or v5.x
- Alpine.js (bundled with Filament)

## Installation

Install the package via Composer:

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
                ->enableAudit()
        );
}
```

## Usage

### Table Columns

```php
use Filament\Tables\Columns\TextColumn;

// Click to reveal (default)
TextColumn::make('email')
    ->private()
    ->revealOnClick(),

// Mask email: j***e@example.com
TextColumn::make('email')
    ->private()
    ->privacyMode('mask')
    ->maskUsing('email'),

// Mask phone: 0812****7890
TextColumn::make('phone')
    ->private()
    ->privacyMode('mask')
    ->maskUsing('phone'),

// Custom masking via Closure
TextColumn::make('account_number')
    ->private()
    ->privacyMode('mask')
    ->maskUsing(fn (string $state) => substr($state, 0, 4) . ' **** ****'),

// Hover to reveal
TextColumn::make('address')
    ->private()
    ->revealOnHover(),

// Custom blur intensity
TextColumn::make('salary')
    ->money('IDR')
    ->private()
    ->blurAmount(6),
```

### Form Inputs

```php
use Filament\Forms\Components\TextInput;

// Blurred until the user focuses the input
TextInput::make('email')->private(),

TextInput::make('salary')
    ->numeric()
    ->prefix('Rp')
    ->private(),
```

### Authorization

Control who can see unblurred data:

```php
// Spatie role-based
TextColumn::make('ssn')
    ->private()
    ->visibleToRoles(['admin', 'hr-manager']),

// Permission-based
TextColumn::make('salary')
    ->private()
    ->permission('view_salary'),

// Laravel Gate / Policy
TextColumn::make('notes')
    ->private()
    ->policy('view_sensitive_notes'),

// Custom closure
TextColumn::make('internal_code')
    ->private()
    ->authorizeUsing(fn ($user) => $user?->is_admin === true),

// Force blur for specific roles (overrides other permissions)
TextColumn::make('customer_notes')
    ->private()
    ->hiddenFromRoles(['customer', 'guest']),
```

## Privacy Modes

| Mode | Behavior |
|------|----------|
| `blur` | Always blurred, cannot be revealed |
| `mask` | Data is masked server-side (e.g., `j***e@example.com`) |
| `blur_hover` | Blurred, reveals on hover |
| `blur_click` | Blurred, reveals on click (auto re-blurs after 5s) |
| `blur_auth` | Blurred, only authorized users can reveal |
| `hybrid` | Both blur + mask for maximum protection |
| `disabled` | Privacy disabled for this field |

## Mask Strategies

| Strategy | Example Output |
|----------|---------------|
| `email` | `j***e@example.com` |
| `phone` | `0812****7890` |
| `nik` | `3173********9012` |
| `full_name` | `Jo** Do*` |
| `api_key` | `sk_***_key` |
| `address` | `Jl. Sudirma***` |
| `generic` | `J***h` |

## Available Methods

```php
// Enable privacy
->private()                         // Enable privacy with default settings
->privacyMode('mask')               // Override privacy mode

// Convenience aliases
->revealOnHover()                   // Shortcut for privacyMode('blur_hover')
->revealOnClick()                   // Shortcut for privacyMode('blur_click')
->revealNever()                     // Shortcut for privacyMode('blur')

// Appearance
->blurAmount(6)                     // CSS blur intensity (1–10)

// Masking
->maskUsing('email')                // Mask strategy or Closure

// Authorization
->visibleToRoles(['admin'])         // Spatie roles that bypass blur
->visibleToPermissions(['edit'])    // Spatie permissions that bypass blur
->permission('view_salary')         // Single permission check
->policy('viewSensitive')           // Laravel Gate authorization
->authorizeUsing(fn () => ...)      // Custom closure
->hiddenFromRoles(['customer'])     // These roles always see blur

// Audit
->auditReveal(true)                 // Log reveal actions to database
->withoutAuditReveal()              // Disable audit for this field
```

## Configuration

After publishing, edit `config/filament-privacy-blur.php`:

```php
return [
    'default_mode'          => 'blur_click',
    'default_blur_amount'   => 4,
    'default_mask_strategy' => 'generic',
    'except_columns'        => ['id', 'created_at', 'updated_at'],
    'except_resources'      => [],
    'except_panels'         => [],
    'audit_enabled'         => false,
    'icon_trigger_enabled'  => true,
];
```

## Audit Logging

When enabled, reveal actions are logged to the `privacy_reveal_logs` table with:

- User ID
- Column name
- Record key
- Reveal mode
- IP address
- User agent
- Timestamp

## Security Notice

> **This plugin provides a visual privacy layer only.** It is designed to prevent casual observation (shoulder surfing, screen sharing) and is **not** a substitute for:
>
> - Backend data encryption
> - Proper access control
> - API-level data redaction
>
> Blur modes keep the original data in the DOM. For highly sensitive fields, use `mask` or `hybrid` mode, or implement data redaction at the model/API layer.

## Compatibility

- **PHP**: 8.2, 8.3, and 8.4 (Filament v5.x requires 8.3+)
- **Laravel**: 11 and 12
- **Filament**: v4.x and v5.x

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
