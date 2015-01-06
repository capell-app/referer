<?php

declare(strict_types=1);

namespace Capell\Referer\Actions;

use Capell\Core\Models\Site;
use Capell\Referer\Data\RefererReportData;
use Capell\Referer\Data\RefererReportRowData;
use Capell\Referer\Data\RefererWindowData;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;
use Throwable;
use UnexpectedValueException;

final class BuildRefererReportAction
{
    use AsFake;
    use AsObject;

    public function __construct(private readonly RefererHealthSignal $healthSignal) {}

    public static function cacheKey(int|string $siteId, RefererWindowData $window): string
    {
        return 'capell-referer.report.' . $siteId . '.' . $window->cacheKey();
    }

    public static function lockKey(int|string $siteId, RefererWindowData $window): string
    {
        return 'capell-referer.refresh.' . $siteId . '.' . $window->cacheKey();
    }

    public function handle(Site $site, RefererWindowData $window): RefererReportData
    {
        if ($this->healthSignal->hasRecentWriteFailure()) {
            return $this->unavailableReport($window);
        }

        $siteId = $this->siteKey($site);
        $key = self::cacheKey($siteId, $window);
        $ttl = $this->positiveConfigInteger('capell-referer.cache_ttl_seconds', 60);

        try {
            $report = Cache::lock(self::lockKey($siteId, $window), 5)->get(
                function () use ($key, $siteId, $ttl, $window): mixed {
                    return Cache::remember($key, now()->addSeconds($ttl), function () use ($siteId, $window): RefererReportData {
                        try {
                            return $this->build($siteId, $window);
                        } catch (Throwable) {
                            return $this->unavailableReport($window);
                        }
                    });
                },
            );
        } catch (Throwable) {
            return $this->unavailableReport($window);
        }

        return $report instanceof RefererReportData ? $report : $this->unavailableReport($window);
    }

    private function build(int|string $siteId, RefererWindowData $window): RefererReportData
    {
        $query = $window->allTime
            ? DB::table('referer_source_totals')->where('site_id', $siteId)
            : DB::table('referer_daily_counts')
                ->where('site_id', $siteId)
                ->whereBetween('date', [$window->startsOn?->toDateString(), $window->endsOn?->toDateString()]);

        $rows = $query
            ->select('source_key', DB::raw('SUM(count) as count'))
            ->groupBy('source_key')
            ->get()
            ->map(static fn (object $row): array => [
                'source_key' => (string) $row->source_key,
                'count' => (int) $row->count,
            ])
            ->sort(static fn (array $left, array $right): int => $left['count'] === $right['count']
                ? $left['source_key'] <=> $right['source_key']
                : $right['count'] <=> $left['count'])
            ->values();
        $total = 0;
        foreach ($rows as $row) {
            $total += (int) $row['count'];
        }

        $collectionStartedOn = DB::table('referer_source_totals')->where('site_id', $siteId)->min('collection_started_on');
        $dates = DB::table('referer_daily_counts')->where('site_id', $siteId);

        $availableThroughOn = $dates->max('date');

        /** @var Collection<int, RefererReportRowData> $reportRows */
        $reportRows = $rows->map(static fn (array $row): RefererReportRowData => new RefererReportRowData(
            sourceKey: $row['source_key'],
            label: (string) __('capell-referer::sources.' . $row['source_key']),
            count: $row['count'],
            share: $total > 0 ? ($row['count'] / $total) * 100 : 0.0,
        ));

        return new RefererReportData(
            rows: $reportRows,
            measuredRequests: $total,
            window: $window,
            generatedAt: CarbonImmutable::now('UTC'),
            collectionStartedOn: $this->dateOrNull($collectionStartedOn),
            availableThroughOn: $this->dateOrNull($availableThroughOn),
        );
    }

    private function dateOrNull(mixed $value): ?CarbonImmutable
    {
        return is_string($value) && $value !== '' ? CarbonImmutable::parse($value, 'UTC') : null;
    }

    private function siteKey(Site $site): int|string
    {
        $key = $site->getKey();

        if (! is_int($key) && ! is_string($key)) {
            throw new UnexpectedValueException('A site must have an integer or string key.');
        }

        return $key;
    }

    private function positiveConfigInteger(string $key, int $fallback): int
    {
        $value = config($key, $fallback);

        return is_int($value) && $value > 0 ? $value : $fallback;
    }

    private function unavailableReport(RefererWindowData $window): RefererReportData
    {
        return new RefererReportData(
            rows: collect(),
            measuredRequests: 0,
            window: $window,
            generatedAt: CarbonImmutable::now('UTC'),
            collectionStartedOn: null,
            availableThroughOn: null,
            available: false,
        );
    }
}
