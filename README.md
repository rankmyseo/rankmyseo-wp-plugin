# RankMySEO WordPress Plugin

A WordPress.org-compliant on-page SEO plugin built for both WordPress beginners (Simple Guided Mode + Ask AI assistant drawer) and experienced SEO professionals (instant 1-click Advanced Mode).

## Requirements

- WordPress 6.4+
- PHP 8.0+
- Node.js 20+ (for building TypeScript/React assets)
- Composer 2+ (for PHP dev/QA tools; not required at runtime in packaged releases)

## Architecture Overview

- `rankmyseo.php` — Plugin header, constants, PSR-4 autoloader (with zero-Composer runtime fallback), lifecycle hooks.
- `uninstall.php` — Safe cleanup respecting the `keep_data_on_uninstall` setting.
- `includes/` — Core PHP 8.0+ PSR-4 (`RankMySEO\`) architecture:
  - `Plugin.php`, `Container.php` — Singleton bootstrap & dependency container.
  - `Core/` — `Settings`, `Capabilities`, `Activator`, `Deactivator`.
  - `Database/` — `Schema` & `Migrator` (`dbDelta()` versioned tables).
  - `Modules/` — `ModuleInterface`, `AbstractModule`, `ModuleManager`, and module registrations.
  - `Rest/` — `RestRegistry`, `AbstractController`, `SettingsController`, `StatusController`, `UiPreferencesController` under `rankmyseo/v1`.
  - `Admin/` — `AdminMenu` (WP Admin menu/submenus + scoped asset enqueueing).
- `src/` — TypeScript + React admin SPA compiled with `@wordpress/scripts` to `build/`:
  - `src/shared/` — Scoped `.rms-root` design tokens, API client, glossary `<Term>` component, `ModeToggle`, state primitives.
  - `src/admin/` — Dual-mode Admin SPA shell, 3-step Setup Wizard shell, persistent Ask AI drawer, Dashboard, Settings, and module ready states.
- `tests/` — PHPUnit (`tests/php/`) and Jest (`tests/js/`) unit tests.

## Development Commands

```bash
# Install dependencies
composer install
npm install

# Build admin assets
npm run build

# Watch mode
npm run start

# Quality checks
composer lint:php
composer phpcs
composer phpstan
composer test
npm run typecheck
npm run lint:js
npm run lint:css
npm run test:js
```

## Local WordPress Environment

```bash
npm run env:start
npm run env:stop
```

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
