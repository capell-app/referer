<?php

declare(strict_types=1);

namespace Capell\Referer\Actions;

use Capell\Core\Models\Site;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;
use RuntimeException;

final class SeedRefererScreenshotFixtureAction
{
    use AsFake;
    use AsObject;

    public function handle(): int
    {
        $configuredPath = getenv('CAPELL_SCREENSHOT_APP_PATH');
        $appPath = is_string($configuredPath) ? realpath($configuredPath) : false;

        throw_unless(
            in_array(config('app.env'), ['local', 'testing'], true)
                && in_array(getenv('CAPELL_SCREENSHOT_FIXTURE'), ['1', 'true', 'record-state'], true)
                && is_string($appPath)
                && $appPath === realpath(base_path()),
            RuntimeException::class,
            'Referer screenshot fixtures require the explicit disposable local screenshot environment.',
        );

        $sites = Site::query()->get();
        throw_if($sites->isEmpty(), RuntimeException::class, 'Create a site in the disposable screenshot App before seeding Referers.');
        $date = now('UTC')->toDateString();

        // Seed every disposable site so the report works with the capture
        // administrator's actual site scope, without changing permissions.
        DB::transaction(static function () use ($sites, $date): void {
            foreach ($sites as $site) {
                foreach (['google' => 128, 'bing' => 48, 'linkedin' => 32, 'other_external' => 16] as $source => $count) {
                    DB::table('referer_daily_counts')->updateOrInsert(
                        ['site_id' => $site->getKey(), 'date' => $date, 'source_key' => $source],
                        ['count' => $count],
                    );
                    DB::table('referer_source_totals')->updateOrInsert(
                        ['site_id' => $site->getKey(), 'source_key' => $source],
                        ['count' => $count, 'collection_started_on' => $date],
                    );
                }
            }
        });

        foreach ($sites as $site) {
            Cache::forget(BuildRefererReportAction::cacheKey($site->id, ResolveRefererWindowAction::run('last-30-days')));
        }

        return $sites->count();
    }
}
