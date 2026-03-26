# Changelog

All notable changes to `filament-privacy-blur` will be documented in this file.

## [Unreleased] - 2026-03-26

### Added - Ability-First Authorization Architecture

- **New API Methods:**
  - `authorizeRevealWith($ability)` - Primary ability-first authorization using Laravel Gate/Policy
  - `revealIfCan($ability)` - Alias with clearer semantics for ability checks
  - Full support for Filament Shield via standard Laravel Gates
  - `PrivacyDecision` DTO for explicit decision modeling
  - `AuthorizationResult` DTO for authorization context

- **Enhanced Authorization Engine:**
  - Gate/Policy checks now take priority over role/permission methods
  - Support for `hasPermissionTo()` Spatie method (in addition to `hasAnyPermission()`)
  - Multi-tenant context capture in audit logs (`data-privacy-tenant-id`)
  - Improved fallback chain for role checking methods

- **Enhanced Frontend:**
  - New explicit data attributes for security
  - `data-privacy-can-reveal-interactively` - Server decision on click/hover reveal
  - `data-privacy-can-globally-reveal` - Server decision on global toggle reveal
  - `data-privacy-never-reveal` - Override flag for non-revealable fields
  - Backward compatibility with legacy `data-privacy-reveal-allowed` attribute

### Changed

- Authorization priority order now follows ability-first approach:
  1. `authorizeRevealUsing()` / `authorizeUsing()` - Custom closure
  2. `authorizeRevealWith()` / `revealIfCan()` - Laravel Gate/Policy (NEW PRIMARY)
  3. `permission()` - Single permission via can()
  4. `visibleToPermissions()` - Multiple permissions via can()
  5. `visibleToRoles()` - Role helper (optional, degrades gracefully)
  6. `policy()` - Legacy policy string

### Internal Changes

- Refactored `PrivacyAuthorizationService` with ability-first methods
- Refactored `PrivacyDecisionResolver` to use `PrivacyDecision` DTO
- Added `checkAuthorization()` method for metadata-based authorization
- Improved role detection with multiple method fallbacks
- Better Spatie/Shield compatibility via `can()` integration

## v1.0.0 - 2026-03-26

### Added

- Initial stable release
- Visual blur protection for table columns, form inputs, and infolist entries
- Click-to-reveal interaction with auto re-blur after 5 seconds
- Hover-to-reveal interaction for quick peek
- Global reveal toggle button in Filament topbar
- Data masking with built-in strategies: email, phone, NIK, full_name, api_key, address, and generic
- Custom masking via Closure
- Authorization support: Spatie roles, permissions, Laravel gates/policies, and custom closures
- Role exclusion (force blur for specific roles)
- Audit logging for reveal actions (optional) with IP address and user agent
- Export safety: automatic masking during Filament exports
- Configuration options for default mode, blur amount, and excluded columns/resources/panels

### Security

- **Secure by default:** Fields with `->private()` but no explicit authorization will NOT allow reveal
- Global reveal now only affects fields the user is authorized to see
- `revealNever()` now truly prevents all reveal methods (click, hover, global toggle)
- `hiddenFromRoles()` now prevents any bypass of the blur
- CSS blur keeps original data in DOM (use mask mode for sensitive data)
- Audit logging tracks reveal actions with user, IP, user agent, and timestamp

### What's Changed

- Bump ramsey/composer-install from 3 to 4 by @dependabot[bot] in https://github.com/Arseno25/Filament-privacy-blur/pull/1
- Main by @Arseno25 in https://github.com/Arseno25/Filament-privacy-blur/pull/2
- Bump ramsey/composer-install to v4 and update privacy blur styles by @Arseno25 in https://github.com/Arseno25/Filament-privacy-blur/pull/3
- Enhance privacy blur feature with resource exceptions and refined auth logic by @Arseno25 in https://github.com/Arseno25/Filament-privacy-blur/pull/4

### New Contributors

- @dependabot[bot] made their first contribution in https://github.com/Arseno25/Filament-privacy-blur/pull/1
- @Arseno25 made their first contribution in https://github.com/Arseno25/Filament-privacy-blur/pull/2

**Full Changelog**: https://github.com/Arseno25/Filament-privacy-blur/commits/v1.0.0
