<h1 align="center">🔐 Filament Privacy Blur</h1>

<p align="center">
  <a href="https://packagist.org/packages/arseno25/filament-privacy-blur">
    <img src="https://img.shields.io/packagist/v/arseno25/filament-privacy-blur.svg?style=flat-square" alt="Latest Version on Packagist">
  </a>
  <a href="https://packagist.org/packages/arseno25/filament-privacy-blur">
    <img src="https://img.shields.io/packagist/dt/arseno25/filament-privacy-blur.svg?style=flat-square" alt="Total Downloads">
  </a>
  <a href="https://github.com/arseno25/filament-privacy-blur/blob/main/LICENSE.md">
    <img src="https://img.shields.io/packagist/l/arseno25/filament-privacy-blur.svg?style=flat-square" alt="License">
  </a>
</p>

<p align="center">
  A Filament plugin for <strong>v4.x and v5.x</strong> that provides visual privacy protection for sensitive data. Apply blur and masking effects to table columns, form inputs, and infolist entries to prevent accidental exposure during screen sharing or shoulder surfing. 👁️
</p>

## ✨ Features

- **🔒 Visual Blur Protection** — CSS-based blur prevents casual observation during screen shares
- **👆 Click to Reveal** — Click a blurred field to temporarily reveal it (auto re-blurs after 5 seconds)
- **🖱️ Hover to Reveal** — Quick-peek by hovering your mouse over the field
- **👁️ Global Reveal Toggle** — An eye icon in the topbar lets authorized users reveal all blurred fields instantly
- **🎭 Data Masking** — Built-in mask strategies for email, phone, NIK, full name, API key, address, and generic text
- **📝 Form Input Protection** — Apply blur to `TextInput`, `Textarea`, and other form fields (auto-clears on focus)
- **🔐 Authorization-First** — Control who can reveal data using Laravel Gates, Policies, Filament Shield, or custom closures
- **📊 Audit Logging** — Track which user revealed which field, with IP address, user agent, and timestamp
- **📤 Export Safety** — Automatically masks blurred data during Filament exports
- **🏢 Multi-Tenant Support** — Captures tenant context for audit logs in multi-tenant applications

## 📋 Requirements

- **PHP**: 8.2 or higher
- **Laravel**: 11 or higher
- **Filament**: v4.x or v5.x
- **Alpine.js**: Bundled with Filament

## 🚀 Installation

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

## ⚙️ Setup

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
                ->exceptPanels(['public']) // Optional: exclude specific panels
                ->enableAudit()
        );
}
```

## 💡 Usage

### 📊 Table Columns

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

### 📝 Form Inputs

```php
use Filament\Forms\Components\TextInput;

// Blurred until the user focuses the input
TextInput::make('email')->private(),

TextInput::make('salary')
    ->numeric()
    ->prefix('Rp')
    ->private(),
```

### 📋 Infolist Entries

```php
use Filament\Infolists\Components\TextEntry;

// Click to reveal
TextEntry::make('email')
    ->private()
    ->revealOnClick(),

// Mask with custom strategy
TextEntry::make('phone')
    ->private()
    ->privacyMode('mask')
    ->maskUsing('phone'),

// With authorization (recommended)
TextEntry::make('ssn')
    ->private()
    ->revealIfCan('view_sensitive_ssn'),

// Never reveal (maximum security)
TextEntry::make('api_key')
    ->private()
    ->revealNever()
    ->privacyMode('mask')
    ->maskUsing('api_key'),
```

## 🔐 Authorization

### Ability-First Approach (Recommended)

The plugin uses Laravel's built-in authorization system as its primary mechanism:

```php
// Using a Laravel Gate or Policy ability (recommended)
TextColumn::make('ssn')
    ->private()
    ->revealIfCan('view_sensitive_ssn'),

// Using a Model Policy
TextColumn::make('notes')
    ->private()
    ->revealIfCan('view', $record),

// Custom closure for complex logic
TextColumn::make('salary')
    ->private()
    ->authorizeRevealUsing(fn ($user, $record) =>
        $user?->is_admin || $record?->department_id === $user?->department_id
    ),
```

### Package Compatibility

| Package | Status |
|---------|--------|
| **Filament Shield** | ✅ Works naturally via Laravel's Gate integration |
| **Spatie Laravel Permission** | ✅ Works via `can()` method |
| **Custom Auth Systems** | ✅ Use `authorizeRevealUsing()` for full control |

### Authorization Methods Summary

| Method | Description | Priority |
|--------|-------------|----------|
| `revealIfCan($ability)` | Laravel Gate/Policy ability (recommended) | 1 (Primary) |
| `authorizeRevealWith($ability)` | Same as revealIfCan | 1 |
| `authorizeRevealUsing(Closure)` | Custom closure with full context | 1 |
| `permission($perm)` | Single permission via `can()` | 2 |
| `visibleToPermissions([$a, $b])` | Multiple permissions (any match) | 3 |
| `visibleToRoles(['admin'])` | Role helper (optional, degrades gracefully) | 4 |

### Role Helpers (Optional)

Role helpers are convenience APIs that degrade gracefully if role methods don't exist:

```php
// Single permission
TextColumn::make('data')
    ->private()
    ->permission('view_data'),

// Multiple permissions (any match)
TextColumn::make('admin_field')
    ->private()
    ->visibleToPermissions(['view_admin', 'edit_admin']),

// Roles (convenience, optional)
TextColumn::make('secret')
    ->private()
    ->visibleToRoles(['admin']),

// Force blur for specific roles (always see blur, cannot reveal)
TextColumn::make('internal')
    ->private()
    ->hiddenFromRoles(['guest', 'customer']),
```

> **Important:** Roles are convenience helpers only. The core safety mechanism relies on Laravel's Gate/Policy system. Projects without role methods will still be secure.

## 🎨 Privacy Modes

### Mode Overview

| Mode | Description |
|------|-------------|
| `blur` | 🔒 Always blurred, cannot be revealed |
| `mask` | 🎭 Data is masked server-side (e.g., `j***e@example.com`) |
| `blur_hover` | 🖱️ Blurred, reveals on hover |
| `blur_click` | 👆 Blurred, reveals on click (auto re-blurs after 5s) |
| `blur_auth` | 🛡️ Blurred, only authorized users can reveal interactively |
| `hybrid` | 🔐 Both blur + mask for maximum protection |
| `disabled` | ⚪ Privacy disabled for this field |

### Mode Behavior Matrix

| Mode | Authorized User | Unauthorized User | `revealNever()` |
|------|----------------|-------------------|-----------------|
| `disabled` | 👁️ Plain text | 👁️ Plain text | Plain text |
| `blur` | 👁️ Plain text | 🔒 Blurred, no reveal | Blurred, no reveal |
| `mask` | 👁️ Plain text | 🎭 Masked text | Masked text |
| `blur_hover` | 🔒 Blur, hover reveals | 🔒 Blurred, no hover | Blurred, no hover |
| `blur_click` | 🔒 Blur, click reveals | 🔒 Blurred, no click | Blurred, no click |
| `blur_auth` | 👁️ Plain text | 🔒 Blurred, no reveal | Blurred, no reveal |
| `hybrid` | 🎭 Masked text | 🎭 Masked + blurred | Masked + blurred |

### Advanced Mode Examples

```php
use Filament\Tables\Columns\TextColumn;

// blur_auth - Only authorized users can see clearly
TextColumn::make('internal_notes')
    ->private()
    ->privacyMode('blur_auth')
    ->revealIfCan('view_internal'),

// hybrid - Maximum protection for highly sensitive data
TextColumn::make('ssn')
    ->private()
    ->privacyMode('hybrid')
    ->revealIfCan('view_ssn')
    ->revealNever(),
```

## 🎭 Mask Strategies

| Strategy | Example Output |
|----------|---------------|
| `email` | `j***e@example.com` |
| `phone` | `0812****7890` |
| `nik` | `3173********9012` |
| `full_name` | `Jo** Do*` |
| `api_key` | `sk_***_key` |
| `address` | `Jl. Sudirma***` |
| `generic` | `J***h` |

## 📚 Available Methods

### Enable Privacy
```php
->private()              // Enable privacy with default settings
->privacyMode('mask')    // Override privacy mode
```

### Convenience Aliases
```php
->revealOnHover()        // Shortcut for privacyMode('blur_hover')
->revealOnClick()        // Shortcut for privacyMode('blur_click')
->revealNever()          // Shortcut for privacyMode('blur') with never-reveal flag
```

### Appearance
```php
->blurAmount(6)          // CSS blur intensity (1–10)
```

### Masking
```php
->maskUsing('email')     // Mask strategy or Closure
```

### Authorization (Ability-First - Recommended)
```php
->revealIfCan($ability)                    // Laravel Gate/Policy ability (primary)
->authorizeRevealWith($ability)          // Same as revealIfCan
->authorizeRevealUsing(Closure)           // Custom closure
```

### Authorization (Convenience)
```php
->visibleToRoles([$role])                  // Role-based (convenience)
->visibleToPermissions([$perm])           // Multiple permissions (any match)
->permission($perm)                        // Single permission
->hiddenFromRoles([$role])                // These roles always see blur
```

### Audit
```php
->auditReveal(true)      // Log reveal actions to database (default: true)
->withoutAuditReveal()   // Disable audit for this field
```

## ⚙️ Configuration

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

## 📊 Audit Logging

When enabled, reveal actions are logged to the `privacy_reveal_logs` table with:

| Field | Description |
|-------|-------------|
| `user_id` | ID of the user who revealed the data |
| `column_name` | Name of the column that was revealed |
| `record_key` | Primary key of the record |
| `reveal_mode` | Privacy mode used (e.g., `blur_click`, `blur_hover`) |
| `ip_address` | IP address of the user |
| `user_agent` | Browser/user agent string |
| `panel_id` | Filament panel ID (for multi-panel apps) |
| `resource` | Resource identifier (e.g., UserResource) |
| `tenant_id` | Tenant ID (for multi-tenant apps) |

> **💡 Multi-Tenant Support:** The plugin automatically captures tenant context when using `stancl/tenancy` or similar packages.

## ⚠️ Security Notice

> **This plugin provides a visual privacy layer only.** It is designed to prevent casual observation (shoulder surfing, screen sharing) and is **not** a substitute for:
>
> - Backend data encryption
> - Proper access control
> - API-level data redaction
>
> Blur modes keep the original data in the DOM. For highly sensitive fields, use `mask` or `hybrid` mode, or implement data redaction at the model/API layer.

### Secure by Default

> **Breaking Notice:** This plugin is **secure by default**. Fields with `->private()` but **no explicit authorization method** (like `visibleToRoles()`, `permission()`, `revealIfCan()`, etc.) will **NOT allow reveal** for any user.
>
> You must explicitly specify who can reveal the data:
>
> ```php
> // This will blur for everyone, NO reveal allowed
> TextColumn::make('email')->private()
>
> // Only admins can reveal
> TextColumn::make('email')->private()->visibleToRoles(['admin'])
>
> // Users with 'view_email' permission can reveal
> TextColumn::make('email')->private()->permission('view_email')
> ```

### Global Reveal Toggle

The global reveal toggle (eye icon in topbar) only reveals fields that:
1. The current user is authorized to view (via `revealIfCan()`, `permission()`, etc.)
2. The field is not marked as `revealNever()`
3. The field is not in `hiddenFromRoles()` for the current user

The toggle automatically hides itself when there are no globally revealable fields on the current page, providing a cleaner user interface.

You can control the toggle visibility using:
- `showGlobalRevealToggle()` - Show the toggle (default)
- `hideGlobalRevealToggle()` - Hide the toggle entirely

This ensures that global reveal cannot bypass any authorization rules.

## 🧪 Compatibility

| Component | Supported Versions |
|-----------|-------------------|
| **PHP** | 8.2, 8.3, 8.4 |
| **Laravel** | 11, 12 |
| **Filament** | v4.x, v5.x |
| **Filament Shield** | ✅ Compatible via Laravel Gates |
| **Spatie Permission** | ✅ Compatible via `can()` method |

## 📝 Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## 🤝 Contributing

Thank you for considering contributing to Filament Privacy Blur!

### Development Setup

1. **Fork the repository**
2. **Clone your fork**
3. **Install dependencies**
   ```bash
   composer install
   ```
4. **Run the test suite**
   ```bash
   composer test
   composer analyse
   composer test:lint
   ```

### Coding Standards

- **PHPStan** - Static analysis at level 4
- **Pint** - Laravel code style fixer
- **Pest** - Testing framework

## 📜 License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

---

<div align="center">

**Made with ❤️ for the Filament community**

</div>
