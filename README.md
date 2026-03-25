# Filament Privacy Blur

[![Latest Version on Packagist](https://img.shields.io/packagist/v/arseno25/filament-privacy-blur.svg?style=flat-square)](https://packagist.org/packages/arseno25/filament-privacy-blur)
[![Total Downloads](https://img.shields.io/packagist/dt/arseno25/filament-privacy-blur.svg?style=flat-square)](https://packagist.org/packages/arseno25/filament-privacy-blur)

A Filament plugin that adds visual privacy layers to sensitive data in tables, infolists, and forms. Protect fields like emails, phone numbers, national IDs, and salaries from shoulder surfing and screen sharing with configurable blur and masking effects.

## Features

- 🔒 **Visual Blur Protection** — CSS-based blur prevents casual observation during screen shares or in busy environments
- 🖱 **Click to Reveal** — Click a blurred field to temporarily reveal it (auto re-blurs after 5 seconds)
- 👆 **Hover to Reveal** — Quick-peek by hovering your mouse over the field
- 👁 **Global Reveal Toggle** — An eye icon in the topbar lets authorized users reveal all blurred fields instantly
- 🎭 **Data Masking** — Built-in mask strategies for `email`, `phone`, `nik`, `full_name`, `api_key`, `address`, and `generic`
- 📝 **Form Input Protection** — Apply blur to `TextInput`, `Textarea`, and other form fields (auto-clears on focus)
- 🛡 **Role & Permission Gates** — Control who can reveal data using Spatie roles, permissions, Laravel policies, or custom closures
- 🕵️ **Audit Logging** — Track which user revealed which field, with IP address and timestamp
- 🚫 **Role Exclusion** — Force-blur specific roles regardless of other permissions
- 📤 **Export Safety** — Automatically masks blurred data during Filament Exports

## Requirements

- PHP 8.2+
- Filament v3.0+ / v4.0+ / v5.0+
- Alpine.js (bundled with Filament)

## Installation

Install the package via Composer:

```bash
composer require arseno25/filament-privacy-blur
```

Publish the configuration and migration files:

```bash
php artisan vendor:publish --tag="filament-privacy-blur-config"
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
                ->blurAmount(3)
                ->exceptColumns(['id', 'created_at', 'updated_at'])
                ->enableAudit(true)
        );
}
```

## Usage

### Table Columns

Add `->private()` to any column to enable privacy protection:

```php
use Filament\Tables\Columns\TextColumn;

// Click to reveal (default mode)
TextColumn::make('name')
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

// Hover to reveal
TextColumn::make('address')
    ->private()
    ->revealOnHover(),

// Custom blur intensity + audit logging
TextColumn::make('salary')
    ->money('IDR')
    ->private()
    ->revealOnClick()
    ->blurAmount(6)
    ->auditReveal(true),
```

### Form Inputs

```php
use Filament\Forms\Components\TextInput;

// Blurred until the user focuses the input
TextInput::make('email')
    ->private(),

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
| `mask` | Data is masked server-side (e.g. `j***e@example.com`) |
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
->maskUsing('email')                // Mask strategy: email, phone, nik, full_name, api_key, address, generic

// Authorization
->visibleToRoles(['admin'])         // Spatie roles that bypass blur
->visibleToPermissions(['edit'])    // Spatie permissions that bypass blur
->permission('view_salary')         // Single permission check
->policy('viewSensitive')           // Laravel Gate authorization
->authorizeUsing(fn () => ...)      // Custom closure

// Force blur
->hiddenFromRoles(['customer'])     // These roles always see blur

// Audit
->auditReveal(true)                 // Log reveal actions to database
->withoutAuditReveal()              // Disable audit for this field
```

## Configuration

After publishing, edit `config/filament-privacy-blur.php`:

```php
return [
    'default_mode'          => 'blur_click',   // Default privacy mode
    'default_blur_amount'   => 3,              // CSS blur in px
    'default_mask_strategy' => 'generic',      // Fallback mask strategy
    'except_columns'        => ['id', 'created_at', 'updated_at'],
    'except_resources'      => [],
    'except_panels'         => [],
    'audit_enabled'         => false,          // Global audit toggle
    'icon_trigger_enabled'  => true,           // Show global reveal button
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

> ⚠️ This plugin provides a **visual privacy layer** to shield sensitive data from casual observation (shoulder surfing, screen sharing). It is **not** a substitute for backend data encryption or access control.
>
> - Blur modes keep the original data in the DOM — use `mask` or `hybrid` mode for sensitive fields
> - Always combine with proper backend authorization and data encryption
> - For high-security requirements, perform data redaction at the model or API layer

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
