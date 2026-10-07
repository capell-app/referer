<?php

declare(strict_types=1);

namespace Capell\Referer\Providers;

use Capell\Admin\Enums\DashboardEnum;
use Capell\Admin\Facades\CapellAdmin;
use Capell\Core\Facades\CapellCore;
use Capell\Referer\Filament\Pages\RefererPage;
use Capell\Referer\Filament\Widgets\TopRefererSourcesFilamentWidget;
use Illuminate\Support\ServiceProvider;
use Override;

final class AdminServiceProvider extends ServiceProvider
{
    private bool $installedRuntimeBooted = false;

    #[Override]
    public function register(): void
    {
        $this->booted(function (): void {
            $this->boot();
        });
    }

    public function boot(): void
    {
        if ($this->installedRuntimeBooted || ! CapellCore::isPackageInstalled(RefererServiceProvider::$packageName)) {
            return;
        }

        CapellAdmin::registerExtensionPage(RefererServiceProvider::$packageName, RefererPage::class);
        CapellAdmin::registerDashboardFilamentWidget(TopRefererSourcesFilamentWidget::class, DashboardEnum::Main, DashboardEnum::MarketingStudio);
        $this->installedRuntimeBooted = true;
    }
}
