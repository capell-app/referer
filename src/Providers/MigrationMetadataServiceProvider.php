<?php

declare(strict_types=1);

namespace Capell\Referer\Providers;

use Capell\Core\Support\Migration\MigrationRootRegistry;
use Illuminate\Support\ServiceProvider;
use Override;

final class MigrationMetadataServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {

        $this->callAfterResolving(MigrationRootRegistry::class, static function (MigrationRootRegistry $registry): void {
            $registry->register('capell-app/referer', __DIR__ . '/../../database/migrations', [
                '2026_09_14_000001_create_referer_counts_tables' => ['referer_daily_counts', 'referer_source_totals', 'referer_retention_state'],
            ]);
        });
    }
}
