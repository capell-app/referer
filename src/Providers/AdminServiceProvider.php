<?php

declare(strict_types=1);

namespace Capell\Referer\Providers;

use Capell\Admin\Enums\DashboardEnum;
use Capell\Admin\Facades\CapellAdmin;
use Capell\Core\Facades\CapellCore;
use Capell\Referer\Filament\Pages\RefererPage;
use Capell\Referer\Filament\Widgets\TopRefererSourcesFilamentWidget;
use Illuminate\Support\ServiceProvider;

final class AdminServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (! CapellCore::isPackageInstalled(RefererServiceProvider::$packageName)) {
            return;
        }

        CapellAdmin::registerExtensionPage(RefererServiceProvider::$packageName, RefererPage::class);
        CapellAdmin::registerDashboardFilamentWidget(TopRefererSourcesFilamentWidget::class, DashboardEnum::Main, DashboardEnum::MarketingStudio);
    }
}
