<?php

declare(strict_types=1);

namespace Capell\Referer\Providers;

use Capell\Admin\Enums\DashboardEnum;
use Capell\Admin\Facades\CapellAdmin;
use Capell\Core\Support\Packages\RegistersInstalledRuntime;
use Capell\Referer\Filament\Pages\RefererPage;
use Capell\Referer\Filament\Widgets\TopRefererSourcesFilamentWidget;
use Illuminate\Support\ServiceProvider;
use Override;

final class AdminServiceProvider extends ServiceProvider
{
    use RegistersInstalledRuntime;

    #[Override]
    public function register(): void
    {
        $this->registerInstalledRuntime(RefererServiceProvider::$packageName, 'admin');
    }

    protected function bootInstalledRuntime(): void
    {
        CapellAdmin::registerExtensionPage(RefererServiceProvider::$packageName, RefererPage::class);
        CapellAdmin::registerDashboardFilamentWidget(TopRefererSourcesFilamentWidget::class, DashboardEnum::Main, DashboardEnum::MarketingStudio);
    }
}
