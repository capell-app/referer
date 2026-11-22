<?php

declare(strict_types=1);

namespace Capell\Referer\Actions;

use Capell\Core\Support\TableName;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

final class PruneRefererCountsAction
{
    use AsFake;
    use AsObject;

    public function handle(?int $retentionDays = null, int $batchSize = 500, bool $dryRun = false): int
    {
        $days = $retentionDays ?? $this->positiveConfigInteger('capell-referer.retention_days', 400);
        $batchSize = max(1, min($batchSize, 5000));
        $cutoff = CarbonImmutable::today('UTC')->subDays(max(1, $days) - 1)->toDateString();

        if ($dryRun) {
            return (int) DB::table(TableName::resolve('referer_daily_counts'))->where('date', '<', $cutoff)->count()
                + (int) $this->staleTotalsQuery($cutoff)->count();
        }

        return DB::transaction(function () use ($batchSize, $cutoff): int {
            $deleted = 0;

            do {
                $ids = DB::table(TableName::resolve('referer_daily_counts'))
                    ->where('date', '<', $cutoff)
                    ->orderBy('id')
                    ->limit($batchSize)
                    ->pluck('id');

                if ($ids->isEmpty()) {
                    break;
                }

                $deleted += DB::table(TableName::resolve('referer_daily_counts'))->whereIn('id', $ids->all())->delete();
            } while (true);

            do {
                $ids = $this->staleTotalsQuery($cutoff)
                    ->orderBy('id')
                    ->limit($batchSize)
                    ->pluck('id');

                if ($ids->isEmpty()) {
                    break;
                }

                $deleted += DB::table(TableName::resolve('referer_source_totals'))->whereIn('id', $ids->all())->delete();
            } while (true);

            $this->recordRetentionBoundary($cutoff);

            return $deleted;
        });
    }

    /**
     * All-time totals are kept only while their source still has a daily count inside the retained period,
     * so a source that stops sending visitors is removed once its last daily count expires.
     */
    private function staleTotalsQuery(string $cutoff): Builder
    {
        $totals = TableName::resolve('referer_source_totals');
        $daily = TableName::resolve('referer_daily_counts');

        return DB::table($totals)->whereNotExists(static function (Builder $query) use ($cutoff, $daily, $totals): void {
            $query->selectRaw('1')
                ->from($daily)
                ->whereColumn($daily . '.site_id', $totals . '.site_id')
                ->whereColumn($daily . '.source_key', $totals . '.source_key')
                ->where($daily . '.date', '>=', $cutoff);
        });
    }

    private function recordRetentionBoundary(string $cutoff): void
    {
        $current = DB::table(TableName::resolve('referer_retention_state'))->where('id', 1)->value('daily_available_from');

        if (! is_string($current)) {
            DB::table(TableName::resolve('referer_retention_state'))->insert([
                'id' => 1,
                'daily_available_from' => $cutoff,
            ]);

            return;
        }

        if ($current < $cutoff) {
            DB::table(TableName::resolve('referer_retention_state'))->where('id', 1)->update([
                'daily_available_from' => $cutoff,
            ]);
        }
    }

    private function positiveConfigInteger(string $key, int $fallback): int
    {
        $value = config($key, $fallback);

        return is_int($value) && $value > 0 ? $value : $fallback;
    }
}
