<?php

declare(strict_types=1);

namespace Capell\Referer\Providers;

use Capell\Referer\Console\Commands\SeedRefererScreenshotFixtureCommand;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\ServiceProvider;
use Override;

final class ConsoleServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([SeedRefererScreenshotFixtureCommand::class]);

        // Composer can add this provider to an already running installer.
        if ($this->app instanceof Application && $this->app->isBooted()) {
            Artisan::registerCommand($this->app->make(SeedRefererScreenshotFixtureCommand::class));
        }
    }
}
