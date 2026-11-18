<?php

declare(strict_types=1);

namespace Capell\Referer\Actions;

use Capell\Core\Models\Site;
use Capell\Core\Support\TableName;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;
use RuntimeException;

/**
 * Gives every site today's aggregate referral counts, so the report has something to show. Idempotent.
 *
 * @method static int run()
 */
final class SeedRefererDemoCountsAction
{
    use AsFake;
    use AsObject;

    public function handle(): int
    {
        $sites = Site::query()->get();
        throw_if($sites->isEmpty(), RuntimeException::class, 'Create a site before seeding Referers.');
        $date = now('UTC')->toDateString();

        DB::transaction(static function () use ($sites, $date): void {
            foreach ($sites as $site) {
                foreach (['google' => 128, 'bing' => 48, 'linkedin' => 32, 'other_external' => 16] as $source => $count) {
                    DB::table(TableName::resolve('referer_daily_counts'))->updateOrInsert(
                        ['site_id' => $site->getKey(), 'date' => $date, 'source_key' => $source],
                        ['count' => $count],
                    );
                    DB::table(TableName::resolve('referer_source_totals'))->updateOrInsert(
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
