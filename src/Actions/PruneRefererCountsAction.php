<?php

declare(strict_types=1);

namespace Capell\Referer\Actions;

use Carbon\CarbonImmutable;
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
            return (int) DB::table('referer_daily_counts')->where('date', '<', $cutoff)->count();
        }

        return DB::transaction(function () use ($batchSize, $cutoff): int {
            $deleted = 0;

            do {
                $ids = DB::table('referer_daily_counts')
                    ->where('date', '<', $cutoff)
                    ->orderBy('id')
                    ->limit($batchSize)
                    ->pluck('id');

                if ($ids->isEmpty()) {
                    break;
                }

                $deleted += DB::table('referer_daily_counts')->whereIn('id', $ids->all())->delete();
            } while (true);

            $this->recordRetentionBoundary($cutoff);

            return $deleted;
        });
    }

    private function recordRetentionBoundary(string $cutoff): void
    {
        $current = DB::table('referer_retention_state')->where('id', 1)->value('daily_available_from');

        if (! is_string($current)) {
            DB::table('referer_retention_state')->insert([
                'id' => 1,
                'daily_available_from' => $cutoff,
            ]);

            return;
        }

        if ($current < $cutoff) {
            DB::table('referer_retention_state')->where('id', 1)->update([
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
