# Referers

<!-- prettier-ignore-start -->

## What This Plugin Adds

Referers is a **Preview** Capell package in the **Capell Marketing & Growth** product group. It is intended to be a free acquisition, but is not yet enabled for stable distribution. It ships as `capell-app/referer` and extends these surfaces: admin, frontend, console.

Referers adds privacy-preserving aggregate referral reporting to Capell. It keeps daily and lifetime counters for finite source keys without storing raw URLs or request identifiers.

Administrators review site-scoped referral counts in a cached Referrers report and a small dashboard widget, while origin frontend renders record successful HTML responses best-effort.

Evidence: [`src/Actions/ResolveReferralSourceAction.php`](src/Actions/ResolveReferralSourceAction.php), [`src/Actions/RecordRefererCountAction.php`](src/Actions/RecordRefererCountAction.php), [`database/migrations/2026_09_14_000001_create_referer_counts_tables.php`](database/migrations/2026_09_14_000001_create_referer_counts_tables.php), [`src/Filament/Pages/RefererPage.php`](src/Filament/Pages/RefererPage.php), [`src/Filament/Widgets/TopRefererSourcesFilamentWidget.php`](src/Filament/Widgets/TopRefererSourcesFilamentWidget.php), [`src/Http/Middleware/RecordRefererMiddleware.php`](src/Http/Middleware/RecordRefererMiddleware.php).

Status details:

- Status: Preview — not distribution-ready
- Tier: premium; acquisition: free
- Bundle: marketing-growth
- Composer package: `capell-app/referer`
- Namespace: `Capell\Referer`
- Theme key: not applicable

## Why It Matters

**For developers:** Typed Actions, bounded validation, atomic upserts, and focused tests keep the privacy contract explicit at each boundary.

**For teams:** Site teams can see which configured external sources contribute successful page requests without receiving a browsing-event log.

Evidence: [`src/Actions/BuildRefererReportAction.php`](src/Actions/BuildRefererReportAction.php), [`src/Actions/PruneRefererCountsAction.php`](src/Actions/PruneRefererCountsAction.php), [`tests/Feature/RefererActionsTest.php`](tests/Feature/RefererActionsTest.php), [`docs/admin-guide.md`](docs/admin-guide.md), [`resources/views/filament/pages/referer.blade.php`](resources/views/filament/pages/referer.blade.php).

## Screens And Workflow

Docs gap: add `docs/screenshots.json` before promoting this package with visual workflow claims.

- Admin index screen if the package has a Filament resource.
- Create/edit screen if editors create records.
- Settings/configuration screen when settings exist.
- Frontend output when the package renders public pages.
- Package detail or install intent screen when marketplace-owned.

## Technical Shape

### Service providers

- `Capell\Referer\Providers\RefererServiceProvider`
- `Capell\Referer\Providers\AdminServiceProvider`

### Config files

- `packages/referer/config/capell-referer.php`

### Migrations

- `packages/referer/database/migrations/2026_09_14_000001_create_referer_counts_tables.php`

### Models

- `RefererDailyCount`
- `RefererSourceTotal`

### Filament classes

- `RefererPage`
- `TopRefererSourcesFilamentWidget`

### Actions

- `BuildRefererReportAction`
- `PruneRefererCountsAction`
- `RecordRefererCountAction`
- `RefererHealthSignal`
- `ResolveRefererWindowAction`
- `ResolveReferralSourceAction`

### Data objects

- `RefererReportData`
- `RefererReportRowData`
- `RefererWindowData`
- `ReferralSourceData`

### Command signatures

- `capell:referer:prune`

### Scheduled commands

- `capell:referer:prune (daily; package registered)`

### Console command classes

- `PruneRefererCountsCommand`

### Manifest contributions

- `admin-page: Capell\Referer\Manifest\RefererAdminPageContribution`
- `console-command: Capell\Referer\Manifest\RefererConsoleCommandsContribution`
- `dashboard-widget: Capell\Referer\Manifest\RefererDashboardFilamentWidgetsContribution`
- `health-check: Capell\Referer\Manifest\RefererHealthContribution`
- `migration: Capell\Referer\Manifest\RefererMigrationsContribution`
- `model: Capell\Referer\Manifest\RefererModelsContribution`
- `permission: Capell\Referer\Manifest\RefererPermissionsContribution`
- `scheduled-job: Capell\Referer\Manifest\RefererRetentionScheduleContribution`

### Health checks

- `Capell\Referer\Health\RefererHealthCheck`

### Blade views

- `packages/referer/resources/views/filament/pages/referer.blade.php`

### Cache tags

- `referer`


## Data Model

- Required tables: `referer_daily_counts`, `referer_source_totals`, `referer_retention_state`.
- Models: `RefererDailyCount`, `RefererSourceTotal`.
- Core record references in migrations: `sites via site_id`.
- Migration files: `2026_09_14_000001_create_referer_counts_tables.php`.
- Migration impact: run host migrations through the package install flow before opening package surfaces.
- Deletion/retention behaviour: migrations declare cascade-on-delete relationships; retention is scheduled through `capell:referer:prune` (daily; registered by the package provider).

## Install Impact

- Required packages: `capell-app/admin`, `capell-app/core`, `capell-app/frontend`.
- Admin navigation: declares `admin-page: RefererAdminPageContribution`; each Filament page or resource controls its own navigation visibility.
- Admin/editor extensions: `dashboard-widget: RefererDashboardFilamentWidgetsContribution`.
- Permissions: `View:RefererPage`; Shield-generated page permissions for `Capell\Referer\Filament\Pages\RefererPage` (names and grants depend on host Shield configuration).
- Public routes: none declared.
- Database changes: package migrations are declared.
- Config: `config/capell-referer.php`.
- Settings: no package settings declared.
- Queues or schedules: scheduled commands `capell:referer:prune (daily; package registered)`.
- Cache tags: `referer`.
- Commands: `capell:referer:prune`.

## Common Pitfalls

- Keep required Capell packages on compatible v4 releases: `capell-app/admin`, `capell-app/core`, `capell-app/frontend`.
- Run migrations before opening package resources or public routes.
- Review package configuration before production-like verification: `config/capell-referer.php`.
- Keep the host Laravel scheduler running so package-registered schedules can execute: `capell:referer:prune (daily; package registered)`.
- Keep public Blade and cached HTML free of authoring markers, model IDs, permissions, signed editor URLs, and lazy database queries.
- Custom write integrations must preserve invalidation for `referer` cache tags.

## Troubleshooting

| Symptom | Likely cause | Check | Fix |
| --- | --- | --- | --- |
| Package surface is missing after install | Provider or manifest is not loaded | Confirm `capell.json`, package `composer.json`, and provider registration | Reinstall the package, refresh Composer autoload, and clear host caches |
| Admin screen or command fails on missing table | Package migrations have not run | Check the tables listed in `Data Model` | Run host migrations and rerun the focused package test |
| Background work does not run | Queue worker or declared schedule is not active | Check the jobs and scheduled commands listed in `Technical Shape` | Start the queue worker or host scheduler, then run the focused command or package test |
| Public output leaks unexpected state | Render data, cache variation, or authoring boundary has regressed | Check public Blade, cache tags, and public-output safety tests | Move data loading out of Blade and rerun the package public-output tests |

## Quick Start

1. For development evaluation only, install the moving development branch: `composer require capell-app/referer:dev-main`.
2. Do not use this development listing as a stable production dependency. A tagged release remains gated on installed-App/cache-hit integration and the package's release evidence.

## Next Steps

- [Package docs](docs/README.md)
- [Overview](docs/overview.md)
- [Admin guide](docs/admin-guide.md)
- Configuration files: [`config/capell-referer.php`](config/capell-referer.php).
- [Troubleshooting](#troubleshooting)
- [Screenshot contract](docs/screenshots.json)
- [Capell content language plan](../../docs/CONTENT_LANGUAGE_PLAN.md)
- [Capell documentation design system](../../docs/DESIGN_SYSTEM.md)
- [Capell and package ERD notes](../../docs/erd/capell-and-package-erds.md)
- Focused tests: `vendor/bin/pest packages/referer/tests --configuration=phpunit.xml`.

<!-- prettier-ignore-end -->
