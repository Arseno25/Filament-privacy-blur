# Changelog

All notable changes to `filament-privacy-blur` will be documented in this file.

## [Unreleased] - 2026-03-27

## v1.1.0 - 2026-03-27

### Added

- **Comprehensive Testing Coverage**
  - 181 tests with 404 assertions
  - Real `exceptPanels([...])` behavior proven through resolver flow
  - Real export-context masking with actual server-side masking
  - Real audit end-to-end with validation and database persistence
  - Real toggle visibility backed by decision output

### Changed

- **Enhanced Test Suite**
  - Strengthened panel exclusion tests proving real resolver behavior
  - Strengthened export-context tests proving actual server-side masking
  - Strengthened audit tests with validation and database field verification
  - Strengthened toggle visibility tests proving real decision output

- **Improved Workflow**
  - GitHub Actions changelog workflow now supports both release and tag push events
  - Better error handling for version detection

### Security

- **Proven Security Model**
  - All 4 critical security behaviors now verified by real behavior tests
  - Panel exclusion verified through actual PrivacyDecisionResolver output
  - Export masking verified through actual applyMasking() method calls
  - Audit logging verified through real controller/route/database behavior
  - Toggle visibility verified through actual canBeGloballyRevealed decision attributes

### Tested On

- PHP: 8.2, 8.3, 8.4
- Laravel: 11, 12
- Filament: v4.x, v5.x
- Pest: 181 tests passing with 404 assertions
- PHPStan: Level 4, no errors (when memory limit allows)
- Laravel Pint: Passing

**Full Changelog**: https://github.com/Arseno25/filament-privacy-blur/commits/v1.1.0

## [Unreleased] - 2026-03-26

### Added

- **Authorization-First Architecture**
  - `revealIfCan($ability)` - Primary API for Laravel Gate/Policy authorization
  - `authorizeRevealWith($ability)` - Alias for revealIfCan with explicit semantics
  - `authorizeRevealUsing($closure)` - Custom closure with full context (user, record)
  - `PrivacyDecision` DTO - Explicit decision modeling with clear property names
  - `AuthorizationResult` DTO - Authorization context with method and reason

- **Enhanced Authorization Engine**
  - Gate/Policy checks now take priority as the primary authorization mechanism
  - Support for passing Model records to Gate/Policy for context-aware authorization
  - Multi-tenant context capture in audit logs (`data-privacy-tenant-id` attribute)
  - Improved fallback chain for role checking methods (hasAnyRole, hasRole, hasExactRole)

- **New Data Attributes for Security**
  - `data-privacy-can-reveal-interactively` - Server decision on click/hover reveal
  - `data-privacy-can-globally-reveal` - Server decision on global toggle reveal
  - `data-privacy-privacy-never-reveal` - Override flag for non-revealable fields

- **Plugin Configuration Options**
  - `showGlobalRevealToggle()` - Control visibility of global reveal toggle
  - `hideGlobalRevealToggle()` - Hide the toggle entirely
  - `exceptPanels([$panels])` - Exclude specific panels from privacy

### Changed

- **Authorization Priority Order** - The new ability-first approach:
  1. `authorizeRevealUsing()` / `authorizeUsing()` - Custom closure (highest priority)
  2. `revealIfCan()` / `authorizeRevealWith()` - Laravel Gate/Policy (PRIMARY)
  3. `permission()` - Single permission via `can()`
  4. `visibleToPermissions()` - Multiple permissions (any match)
  5. `visibleToRoles()` - Role helper (optional, degrades gracefully)
  6. `policy()` - Legacy policy string

- **Global Reveal Toggle** - Now always rendered (when enabled), but JavaScript only reveals fields where:
  - The current user is authorized to view the field
  - The field is not marked as `revealNever()`
  - The user is not in `hiddenFromRoles()` for that field

- **Frontend-Driven Security** - All privacy decisions are now server-rendered as HTML data attributes. The JavaScript only respects these decisions and cannot override them.

### Fixed

- **Global Reveal Toggle Logic** - Fixed issue where toggle never appeared due to `isAuthorized()` returning false without context. Toggle now always renders (when enabled) and respects individual field authorization.
- **Simplified Alpine.js Script** - Removed dead legacy fallback code, cleaner global reveal logic
- **README Documentation** - Fixed inconsistencies in composer commands, aligned examples with actual API, corrected audit field naming

### Security

- **Secure-by-Default Behavior Reinforced** - Fields with `->private()` but no explicit authorization will NOT allow reveal for any user, including in global reveal
- **Global Reveal Safety** - Global toggle can only reveal fields where the current user has explicit authorization
- **Export Safety** - Masking is automatically applied in export contexts for all blur modes

## v1.0.0 - 2026-03-26

### Added

- Initial stable release
- Visual blur protection for table columns, form inputs, and infolist entries
- Click-to-reveal interaction with auto re-blur after 5 seconds
- Hover-to-reveal interaction for quick peek
- Global reveal toggle button in Filament topbar
- Data masking with built-in strategies: email, phone, NIK, full_name, api_key, address, and generic
- Custom masking via Closure
- Authorization support: Laravel Gates, Policies, Spatie roles/permissions, and custom closures
- Role exclusion (force blur for specific roles)
- Audit logging for reveal actions (optional) with IP address and user agent
- Export safety: automatic masking during Filament exports
- Configuration options for default mode, blur amount, and excluded columns/resources/panels

### Security

- **Secure by default:** Fields with `->private()` but no explicit authorization will NOT allow reveal
- Global reveal now only affects fields the user is authorized to see
- `revealNever()` truly prevents all reveal methods (click, hover, global toggle)
- `hiddenFromRoles()` prevents any bypass of the blur
- CSS blur keeps original data in DOM (use mask mode for sensitive data)
- Audit logging tracks reveal actions with user, IP, user agent, and timestamp

### Tested On

- PHP: 8.2, 8.3, 8.4
- Laravel: 11, 12
- Filament: v5.x
- Pest: 107 tests passing with 190 assertions
- PHPStan: Level 4, no errors
- Laravel Pint: Passing

**Full Changelog**: https://github.com/Arseno25/filament-privacy-blur/commits/v1.0.0
