# Referers reference

<!-- prettier-ignore-start -->

Developer reference for `capell-app/referer`. Start with the [README](../README.md) for what the extension does and how to install it.

## Package Details

Referers is an **Available**, **Schema-owning** Capell package in the **Capell Marketing & Growth** product group. It ships as `capell-app/referer` and extends these surfaces: admin, frontend, console.

- Status: Available
- Tier: free
- Bundle: marketing-growth
- Composer package: `capell-app/referer`
- Namespace: `Capell\Referer`
- Theme key: not applicable

## Technical Shape

### Service providers

- `Capell\Referer\Providers\RefererServiceProvider`
- `Capell\Referer\Providers\MigrationMetadataServiceProvider`
- `Capell\Referer\Providers\ConsoleServiceProvider`
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
- `SeedRefererScreenshotFixtureAction`

### Data objects

- `RefererReportData`
- `RefererReportRowData`
- `RefererWindowData`
- `ReferralSourceData`

### Command signatures

- `capell:referer:prune`
- `capell:referer:screenshot-fixture`

### Scheduled commands

- `capell:referer:prune (daily; package registered)`

### Console command classes

- `PruneRefererCountsCommand`
- `SeedRefererScreenshotFixtureCommand`

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

<!-- prettier-ignore-end -->
