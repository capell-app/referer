<?php

declare(strict_types=1);

namespace Capell\Referer\Tests;

use Capell\Admin\Providers\AdminServiceProvider;
use Capell\Core\Facades\CapellCore;
use Capell\Frontend\Providers\FrontendServiceProvider;
use Capell\Referer\Providers\RefererServiceProvider;
use Capell\Tests\AbstractTestCase;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Application;
use Livewire\LivewireServiceProvider;
use Override;

class RefererTestCase extends AbstractTestCase
{
    protected function getPackageServiceName(): string
    {
        return 'capell-referer';
    }

    /** @return class-string[] */
    #[Override]
    protected function getPackageProviders(mixed $app): array
    {
        return [
            ...parent::getPackageProviders($app),
            AdminServiceProvider::class,
            FrontendServiceProvider::class,
            LivewireServiceProvider::class,
            RefererServiceProvider::class,
        ];
    }

    #[Override]
    protected function getEnvironmentSetUp(mixed $app): void
    {
        parent::getEnvironmentSetUp($app);

        CapellCore::forcePackageInstalled(AdminServiceProvider::$packageName);
        CapellCore::forcePackageInstalled(FrontendServiceProvider::$packageName);
        CapellCore::forcePackageInstalled(RefererServiceProvider::$packageName);

        if ($app instanceof Application) {
            $app->make(Repository::class)->set('cache.default', 'array');
            $app->make(Repository::class)->set('queue.default', 'sync');
        }
    }
}
