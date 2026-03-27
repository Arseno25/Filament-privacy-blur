# Changelog

All notable changes to `filament-privacy-blur` will be documented in this file.

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
