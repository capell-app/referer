<?php

declare(strict_types=1);

namespace Capell\Referer\Providers;

use Capell\Core\Facades\CapellCore;
use Capell\Core\Support\Packages\AbstractPackageServiceProvider;
use Capell\Frontend\Support\Routing\FrontendRouteMiddlewareRegistry;
use Capell\Referer\Actions\RefererHealthSignal;
use Capell\Referer\Console\Commands\PruneRefererCountsCommand;
use Capell\Referer\Http\Middleware\RecordRefererMiddleware;
use Capell\Referer\Models\RefererDailyCount;
use Capell\Referer\Models\RefererSourceTotal;
use Illuminate\Console\Scheduling\Schedule;
use Override;
use Spatie\LaravelPackageTools\Package;

final class RefererServiceProvider extends AbstractPackageServiceProvider
{
    public static string $name = 'capell-referer';

    public static string $packageName = 'capell-app/referer';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(self::$name)
            ->hasConfigFile(self::$name)
            ->hasTranslations()
            ->hasViews(self::$name)
            ->hasCommand(PruneRefererCountsCommand::class)
            ->hasMigrations(['2026_09_14_000001_create_referer_counts_tables']);
    }

    #[Override]
    public function registeringPackage(): void
    {
        parent::registeringPackage();

        $this->app->singleton(RefererHealthSignal::class);
        $this->app->register(AdminServiceProvider::class);
    }

    #[Override]
    protected function isPackageInstalled(): bool
    {
        return CapellCore::isPackageInstalled(self::$packageName);
    }

    #[Override]
    protected function bootInstalledPackage(): self
    {
        return $this
            ->registerModels()
            ->registerProtectedTables()
            ->registerFrontendMiddleware()
            ->registerRetentionSchedule();
    }

    private function registerModels(): self
    {
        $models = [RefererDailyCount::class, RefererSourceTotal::class];

        $this->surface()->models($models);
        CapellCore::registerModels($models);

        return $this;
    }

    private function registerProtectedTables(): self
    {
        CapellCore::registerProtectedTable('referer_daily_counts');
        CapellCore::registerProtectedTable('referer_source_totals');
        CapellCore::registerProtectedTable('referer_retention_state');

        return $this;
    }

    private function registerFrontendMiddleware(): self
    {
        if (! class_exists(FrontendRouteMiddlewareRegistry::class)) {
            return $this;
        }

        $configure = static fn (FrontendRouteMiddlewareRegistry $registry): FrontendRouteMiddlewareRegistry => $registry->insertAfter(
            'frontend.anonymous_cacheable_render',
            [RecordRefererMiddleware::class],
        );

        $this->app->afterResolving(FrontendRouteMiddlewareRegistry::class, $configure);

        if ($this->app->resolved(FrontendRouteMiddlewareRegistry::class)) {
            $configure($this->app->make(FrontendRouteMiddlewareRegistry::class));
        }

        return $this;
    }

    private function registerRetentionSchedule(): self
    {
        $this->callAfterResolving(Schedule::class, static function (Schedule $schedule): void {
            $schedule->command('capell:referer:prune')
                ->daily()
                ->withoutOverlapping();
        });

        return $this;
    }
}
