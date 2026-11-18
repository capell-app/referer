<?php

declare(strict_types=1);

namespace Capell\Referer\Providers;

use Capell\Core\Actions\Install\RegisterCommandsInCurrentProcessAction;
use Capell\Referer\Console\Commands\SeedRefererDemoCommand;
use Capell\Referer\Console\Commands\SeedRefererScreenshotFixtureCommand;
use Illuminate\Foundation\Application;
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

        $this->commands([SeedRefererDemoCommand::class, SeedRefererScreenshotFixtureCommand::class]);

        // Composer can add this provider to an already running installer.
        if ($this->app instanceof Application && $this->app->isBooted()) {
            RegisterCommandsInCurrentProcessAction::run([SeedRefererDemoCommand::class, SeedRefererScreenshotFixtureCommand::class]);
        }
    }
}
