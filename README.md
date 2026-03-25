# Filament Privacy Blur Plugin

A Filament plugin providing visual privacy layers on sensitive column data (e.g. NIK, addresses, emails, phone numbers). Easily configure default behavior (blur / mask), set specific constraints based on roles or permissions, and allow reveal interactions (click or hover).

## Features

| Feature | Description |
|---------|-------------|
| **Blur Visual Filter** | Protects data from being completely readable on screen during screen shares or in busy environments. |
| **Form Inputs Protection** | Apply `->private()` to `TextInput` and other forms so they stay blurred until focused. |
| **Click to Reveal (Auto Re-blur)** | Click on the field to temporarily disable the blur. The text will automatically re-blur after 5 seconds to prevent accidental exposure. |
| **Hover to Reveal** | Provide quick access by simply hovering the mouse. |
| **Global "Reveal All" Toggle** | A handy eye-icon in the Filament topbar allows authorized users to reveal all blurred fields on the page instantly. |
| **Screen Reader Security** | Blurred text uses `aria-hidden` and `select-none` to prevent screen readers and accidental copy-pasting from leaking data. |
| **Masking Engine & Export Fallback** | Mask formats natively for `email`, `phone`, `nik`, etc. Automatically masks blurred column data during Filament Exports to maintain safety. |
| **Gate/Role Authorization** | Only allow certain roles/permissions to reveal data. |
| **Audit Logging** | Logs which user clicked to reveal a specific protected record to the database via frontend AJAX requests. |
| **Role Exclusion** | Force blur on specific roles regardless of other permissions. |
| **Custom Blur Amount** | Adjust blur intensity per field. |

## Requirements

- PHP 8.2+
- Filament v3.0+
- Alpine JS (included in Filament)
- Tailwind CSS

## Installation

You can install the package via composer:

```bash
composer require arseno25/filament-privacy-blur
```

### Publish Config & Migrations

```bash
# Publish configuration file
php artisan vendor:publish --tag="filament-privacy-blur-config"

# Publish migration for audit logging
php artisan vendor:publish --tag="filament-privacy-blur-migrations"

# Run migrations
php artisan migrate
```

## Configuration

You can globally modify the default settings from `config/filament-privacy-blur.php`:

```php
return [
    'default_mode' => 'blur_click',     // blur, mask, blur_hover, blur_click, blur_auth, hybrid
    'default_blur_amount' => 4,
    'default_mask_strategy' => 'generic',
    'except_columns' => ['id', 'created_at', 'updated_at'],
    'except_resources' => [],
    'except_panels' => [],
    'audit_enabled' => false,
    'icon_trigger_enabled' => true,
];
```

## Usage

### 1. Panel Registration

Add the plugin to your panel configuration:

```php
use Arseno25\FilamentPrivacyBlur\FilamentPrivacyBlurPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugin(
            FilamentPrivacyBlurPlugin::make()
                ->defaultMode('blur_click')
                ->blurAmount(5)
                ->exceptColumns(['id', 'created_at'])
                ->enableAudit(true)
        );
}
```

### 2. Apply to Columns

The plugin injects macros into the core Filament Column, Entry, and Field classes, extending `TextColumn`, `TextInput`, `BadgeColumn`, etc.

```php
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;

// Form Input Example (Blurs unless focused)
TextInput::make('salary')
    ->private();

// Table Column - Use default mode
TextColumn::make('email')
    ->private();

// Table Column - Hover to reveal
TextColumn::make('phone')
    ->private()
    ->revealOnHover();

// Table Column - Mask the data
TextColumn::make('nik')
    ->private()
    ->privacyMode('mask')
    ->maskUsing('nik'); // 3173********9012

// Table Column - Role-based visibility
TextColumn::make('salary')
    ->private()
    ->visibleToRoles(['admin', 'hr']); // Regular users see blur, these roles see full plaintext
```

### 3. Privacy Modes

| Mode | Description |
|------|-------------|
| `blur` | Always blurred, no way to reveal |
| `mask` | Data is masked at render time (not in DOM) |
| `blur_hover` | Blurred, reveals on hover |
| `blur_click` | Blurred, reveals on click (auto re-blur after 5s) |
| `blur_auth` | Blurred, only authorized users can reveal |
| `hybrid` | Both blur + mask (extra security) |
| `disabled` | Privacy disabled for this field |

### 4. Available Methods

```php
->private()                        // Enables privacy logic for the column using defaults
->privacyMode('mask')              // Overrides the mode (see modes above)

// Convenience aliases
->revealOnHover()                  // Alias for ->privacyMode('blur_hover')
->revealOnClick()                  // Alias for ->privacyMode('blur_click')
->revealNever()                    // Alias for ->privacyMode('blur')

// Appearance
->blurAmount(6)                    // Set custom CSS blur strength (1-10)

// Masking
->maskUsing('email')               // Sets masking strategy (email, phone, nik, full_name, api_key, address, generic)

// Authorization (show unblurred to...)
->visibleToRoles(['admin'])        // Spatie roles that can view unblurred
->visibleToPermissions(['edit'])   // Spatie permissions that can view unblurred
->permission('view_salary')        // Single permission string
->policy('viewSensitive')          // Use standard Gate authorization check per record
->authorizeUsing(fn () => ...)     // Custom closure to determine if user can bypass blur

// Force blur for...
->hiddenFromRoles(['customer'])    // These roles ALWAYS see blur, regardless of other permissions

// Audit logging
->auditReveal(true)                // Log reveal actions to database
->withoutAuditReveal()             // Disable audit for this field
```

### 5. Mask Strategies

| Strategy | Example Output |
|----------|---------------|
| `email` | `j***e@example.com` |
| `phone` | `0812****7890` |
| `nik` | `3173********9012` |
| `full_name` | `Jo** Do*` |
| `api_key` | `sk_***_key` |
| `address` | `Jl. Sudirman ***` |
| `generic` | `J***h` |

### 6. Authorization Examples

```php
use Filament\Tables\Columns\TextColumn;

// Role-based (Spatie Laravel Permission)
TextColumn::make('ssn')
    ->private()
    ->visibleToRoles(['admin', 'hr-manager']);

// Permission-based
TextColumn::make('salary')
    ->private()
    ->permission('view_salary');

// Policy-based (Laravel Gates)
TextColumn::make('notes')
    ->private()
    ->policy('view_sensitive_notes');

// Custom closure
TextColumn::make('internal_code')
    ->private()
    ->authorizeUsing(fn ($user) => $user?->is_admin === true);

// Force blur for specific roles
TextColumn::make('customer_notes')
    ->private()
    ->hiddenFromRoles(['customer', 'guest']);
```

### 7. Audit Logging

When audit is enabled, all reveal actions are logged:

```php
// Enable audit in panel config
FilamentPrivacyBlurPlugin::make()
    ->enableAudit(true);

// Enable per-field
TextColumn::make('ssn')
    ->private()
    ->revealOnClick()
    ->auditReveal(true);
```

Audit logs include:
- User ID
- Column name
- Record key
- Reveal mode used
- IP address
- User agent
- Timestamp

## Security Disclaimers

⚠️ **Important Security Notice:**

This plugin offers a **visual privacy layer** intended to shield sensitive data from casual observers (over-the-shoulder surfing or screen sharing).

- Data is **still technically present in the DOM** when using blur modes (unless you use `mask` or `hybrid` modes)
- Do not mistake visual CSS blurring for cryptographic security
- For high security requirements, perform data redaction directly in the model layer or API responses
- Always combine with proper backend authorization and data encryption

## Changelog

Please see CHANGELOG for more information on what has changed recently.

## License

The MIT License (MIT). Please see License File for more information.
