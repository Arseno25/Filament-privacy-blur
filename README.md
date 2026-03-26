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
  A Filament v4/v5 plugin that provides <strong>visual privacy protection</strong> for sensitive data. Apply blur and masking effects to table columns, form inputs, and infolist entries to prevent accidental exposure during screen sharing or shoulder surfing. 👁️
</p>

## ✨ Features

- **🔒 Visual Blur Protection** — CSS-based blur prevents casual observation during screen shares
- **👆 Click to Reveal** — Click a blurred field to temporarily reveal it (auto re-blurs after 5 seconds)
- **🖱️ Hover to Reveal** — Quick-peek by hovering your mouse over the field
- **👁️ Global Reveal Toggle** — An eye icon in the topbar lets authorized users reveal all blurred fields instantly
- **🎭 Data Masking** — Built-in mask strategies for email, phone, NIK, full name, API key, address, and generic text
- **📝 Form Input Protection** — Apply blur to `TextInput`, `Textarea`, and other form fields (auto-clears on focus)
- **🛡️ Authorization Gates** — Control who can reveal data using Laravel Gates, Policies, Filament Shield, or custom closures
- **📊 Audit Logging** — Track which user revealed which field, with IP address and timestamp
- **📤 Export Safety** — Automatically masks blurred data during Filament exports
- **🏢 Multi-Tenant Support** — Captures tenant context for audit logs in multi-tenant applications

## 📋 Requirements

- **PHP**: 8.2 or higher (8.3+ required for Filament v5.x)
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

// With authorization
TextEntry::make('ssn')
    ->private()
    ->authorizeRevealWith('view_sensitive_ssn'),

// Never reveal (maximum security)
TextEntry::make('api_key')
    ->private()
    ->revealNever()
    ->privacyMode('mask')
    ->maskUsing('api_key'),
```

## 🔐 Authorization

### 🎯 Ability-First Approach (Recommended)

The plugin uses Laravel's built-in authorization system as its primary mechanism:

```php
// Using a Laravel Gate or Policy ability
TextColumn::make('ssn')
    ->private()
    ->authorizeRevealWith('view_sensitive_ssn'),

// Using a Model Policy
TextColumn::make('notes')
    ->private()
    ->authorizeRevealWith('view', $record),

// Alternative syntax with clearer semantics
TextColumn::make('email')
    ->private()
    ->revealIfCan('view_email'),

// Custom closure for complex logic
TextColumn::make('salary')
    ->private()
    ->authorizeRevealUsing(fn ($user, $record) =>
        $user?->is_admin || $record?->department_id === $user?->department_id
    ),
```

### 🤝 Compatibility

| Package | Status |
|---------|--------|
| **Filament Shield** | ✅ Works naturally via Laravel's Gate integration |
| **Spatie Laravel Permission** | ✅ Works via `can()` method |
| **Custom Auth Systems** | ✅ Use `authorizeRevealUsing()` for full control |

### 🔧 Convenience Methods

Role and permission helpers are available as convenience APIs:

```php
// Single permission (uses $user->can())
TextColumn::make('data')
    ->private()
    ->permission('view_data'),

// Multiple permissions
TextColumn::make('admin_field')
    ->private()
    ->visibleToPermissions(['view_admin', 'edit_admin']),

// Roles (convenience, optional)
TextColumn::make('secret')
    ->private()
    ->visibleToRoles(['admin']),

// Legacy Gate/Policy support
TextColumn::make('notes')
    ->private()
    ->policy('view_sensitive_notes'),

// Force blur for specific roles
TextColumn::make('internal')
    ->private()
    ->hiddenFromRoles(['guest', 'customer']),
```

> **Important:** Roles are convenience helpers only. The core safety mechanism
> relies on Laravel's Gate/Policy system. Projects without role methods will
> still be secure.

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

| Mode | Authorized User | Unauthorized User | Hidden Roles | `revealNever()` |
|------|----------------|-------------------|--------------|-----------------|
| `disabled` | 👁️ Plain text | 👁️ Plain text | 👁️ Plain text | 👁️ Plain text |
| `blur` | 👁️ Plain text | 🔒 Blurred, no reveal | 🔒 Blurred, no reveal | 🔒 Blurred, no reveal |
| `mask` | 👁️ Plain text | 🎭 Masked text | 🎭 Masked text | 🎭 Masked text |
| `blur_hover` | 🔒 Blur, hover reveals | 🔒 Blurred, no hover | 🔒 Blurred, no hover | 🔒 Blurred, no hover |
| `blur_click` | 🔒 Blur, click reveals | 🔒 Blurred, no click | 🔒 Blurred, no click | 🔒 Blurred, no click |
| `blur_auth` | 👁️ Plain text (authorized) | 🔒 Blurred, no reveal | 🔒 Blurred, no reveal | 🔒 Blurred, no reveal |
| `hybrid` | 🎭 Masked text | 🎭 Masked + blurred | 🎭 Masked + blurred | 🎭 Masked + blurred |

### Advanced Mode Examples

```php
use Filament\Tables\Columns\TextColumn;

// blur_auth - Only authorized users can see clearly
TextColumn::make('internal_notes')
    ->private()
    ->privacyMode('blur_auth')
    ->authorizeRevealWith('view_internal'),

// hybrid - Maximum protection for highly sensitive data
TextColumn::make('ssn')
    ->private()
    ->privacyMode('hybrid')
    ->authorizeRevealWith('view_ssn')
    ->revealNever(),  // Never allow interactive reveal
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

### 🎯 Convenience Aliases
```php
->revealOnHover()        // Shortcut for privacyMode('blur_hover')
->revealOnClick()        // Shortcut for privacyMode('blur_click')
->revealNever()          // Shortcut for privacyMode('blur') with never-reveal flag
```

### 🎨 Appearance
```php
->blurAmount(6)          // CSS blur intensity (1–10)
```

### 🎭 Masking
```php
->maskUsing('email')     // Mask strategy or Closure
```

### 🔐 Authorization (Ability-First - Recommended)
```php
->authorizeRevealWith('view_ssn')       // Laravel Gate/Policy ability
->revealIfCan('view_email')              // Alias with clearer semantics
->authorizeRevealUsing(fn () => ...)     // Custom closure with full context
```

### 🔐 Authorization (Convenience)
```php
->visibleToRoles(['admin'])              // Role-based (convenience)
->visibleToPermissions(['edit'])         // Multiple permissions
->permission('view_salary')              // Single permission check
->policy('viewSensitive')                // Legacy Gate/Policy string
->authorizeUsing(fn () => ...)           // Alias for authorizeRevealUsing
->hiddenFromRoles(['customer'])          // These roles always see blur
```

### 📊 Audit
```php
->auditReveal(true)      // Log reveal actions to database
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
| `record_key` | Primary key of the record (if available) |
| `reveal_mode` | Privacy mode used (e.g., `blur_click`, `blur_hover`) |
| `ip_address` | IP address of the user |
| `user_agent` | Browser/user agent string |
| `panel_id` | Filament panel ID (for multi-panel apps) |
| `resource_class` | Fully qualified resource class name |
| `tenant_id` | Tenant ID (for multi-tenant apps) |

> **💡 Multi-Tenant Support:** The plugin automatically captures tenant context when using `stancl/tenancy` or similar packages. The `tenant_id` is logged for audit trails in multi-tenant applications.

## ⚠️ Security Notice

> **This plugin provides a visual privacy layer only.** It is designed to prevent casual observation (shoulder surfing, screen sharing) and is **not** a substitute for:
>
> - Backend data encryption
> - Proper access control
> - API-level data redaction
>
> Blur modes keep the original data in the DOM. For highly sensitive fields, use `mask` or `hybrid` mode, or implement data redaction at the model/API layer.

### Secure by Default (v1.0.0+)

> **Breaking Change:** As of v1.0.0, the plugin is **secure by default**. Fields with `->private()` but **no authorization method** (like `visibleToRoles()`, `permission()`, `policy()`, etc.) will **NOT allow reveal** for any user.
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

## 🧪 Compatibility

| Component | Supported Versions |
|-----------|-------------------|
| **PHP** | 8.2, 8.3, 8.4 (Filament v5.x requires 8.3+) |
| **Laravel** | 11, 12 |
| **Filament** | v4.x, v5.x |
| **Filament Shield** | ✅ Compatible via Laravel Gates |
| **Spatie Permission** | ✅ Compatible via `can()` method |

## 📝 Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## 🤝 Contributing

Thank you for considering contributing to Filament Privacy Blur! You can contribute in several ways:

### Reporting Bugs

Use the [GitHub Issues](https://github.com/arseno25/filament-privacy-blur/issues) to:
- Report bugs
- Suggest new features
- Ask questions

### Development Setup

1. **Fork the repository**
   ```bash
   # Click the "Fork" button on GitHub
   ```

2. **Clone your fork**
   ```bash
   git clone https://github.com/YOUR_USERNAME/filament-privacy-blur.git
   cd filament-privacy-blur
   ```

3. **Install dependencies**
   ```bash
   composer install
   npm install
   ```

4. **Run the test suite**
   ```bash
   composer test
   ```

### Coding Standards

This project follows the coding standards of the Filament ecosystem:

- **PHPStan** - Static analysis
  ```bash
  composer analyse
  ```

- **Pint** - Code style fixer
  ```bash
  composer format
  ```

- **Pest** - Testing framework
  ```bash
  composer test
  ```

### Submitting a Pull Request

1. Create a new branch for your feature/fix
   ```bash
   git checkout -b feature/your-feature-name
   ```

2. Make your changes and commit them
   ```bash
   git add .
   git commit -m "Description of your changes"
   ```

3. Push to your fork
   ```bash
   git push origin feature/your-feature-name
   ```

4. Create a Pull Request on GitHub

### Development Guidelines

- Write tests for new features
- Follow PSR-12 coding standards
- Add documentation for new features
- Keep pull requests focused and atomic
- Ensure all tests pass before submitting

## 📜 License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

---

<div align="center">

**Made with ❤️ for the Filament community**

Need help? Found a bug? Have a feature request?

Please visit our [GitHub Issues](https://github.com/arseno25/filament-privacy-blur/issues)

</div>
