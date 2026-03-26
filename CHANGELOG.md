# Changelog

All notable changes to `filament-privacy-blur` will be documented in this file.

## v1.0.0 - 2026-03-26

### What's Changed

* Bump ramsey/composer-install from 3 to 4 by @dependabot[bot] in https://github.com/Arseno25/Filament-privacy-blur/pull/1
* Main by @Arseno25 in https://github.com/Arseno25/Filament-privacy-blur/pull/2
* Bump ramsey/composer-install to v4 and update privacy blur styles by @Arseno25 in https://github.com/Arseno25/Filament-privacy-blur/pull/3
* Enhance privacy blur feature with resource exceptions and refined auth logic by @Arseno25 in https://github.com/Arseno25/Filament-privacy-blur/pull/4

### New Contributors

* @dependabot[bot] made their first contribution in https://github.com/Arseno25/Filament-privacy-blur/pull/1
* @Arseno25 made their first contribution in https://github.com/Arseno25/Filament-privacy-blur/pull/2

**Full Changelog**: https://github.com/Arseno25/Filament-privacy-blur/commits/v1.0.0

## [1.0.0] - 2026-03-26

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
- Audit logging for reveal actions (optional)
- Export safety: automatic masking during Filament exports
- Configuration options for default mode, blur amount, and excluded columns/resources/panels

### Security

- CSS blur keeps original data in DOM (use mask mode for sensitive data)
- Audit logging tracks reveal actions with user, IP, and timestamp

## [Unreleased]

### Planned

- Additional mask strategies based on community feedback
- Enhanced integration with Filament's native permissions system
