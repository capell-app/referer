<?php

declare(strict_types=1);

namespace Capell\Referer\Actions;

use Capell\Referer\Data\RefererWindowData;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsObject;
use Throwable;

final class ResolveRefererWindowAction
{
    use AsObject;

    /** @var list<string> */
    public const array PRESETS = [
        'today',
        'yesterday',
        'last-7-days',
        'last-30-days',
        'last-90-days',
        'this-month',
        'previous-month',
        'all-time',
        'custom',
    ];

    public function handle(string $preset, ?string $startsOn = null, ?string $endsOn = null): RefererWindowData
    {
        $today = CarbonImmutable::today('UTC');

        if ($preset === 'all-time') {
            return new RefererWindowData(null, null, true);
        }

        if ($preset === 'custom') {
            $start = $this->parseDate($startsOn, 'startsOn');
            $end = $this->parseDate($endsOn, 'endsOn');
        } else {
            [$start, $end] = $this->presetDates($preset, $today);
        }

        $retentionDays = $this->positiveConfigInteger('capell-referer.retention_days', 400);
        $oldestAvailable = $today->subDays($retentionDays - 1);
        $recordedBoundary = $this->recordedRetentionBoundary();

        if ($recordedBoundary instanceof CarbonImmutable && $recordedBoundary->greaterThan($oldestAvailable)) {
            $oldestAvailable = $recordedBoundary;
        }

        if ($start->lessThan($oldestAvailable)) {
            throw ValidationException::withMessages([
                'startsOn' => __('capell-referer::report.window_expired', ['date' => $oldestAvailable->toDateString()]),
            ]);
        }

        if ($end->greaterThan($today)) {
            throw ValidationException::withMessages([
                'endsOn' => __('capell-referer::report.window_future'),
            ]);
        }

        if ($start->greaterThan($end)) {
            throw ValidationException::withMessages([
                'endsOn' => __('capell-referer::report.window_reversed'),
            ]);
        }

        return new RefererWindowData($start, $end);
    }

    /** @return array{CarbonImmutable, CarbonImmutable} */
    private function presetDates(string $preset, CarbonImmutable $today): array
    {
        return match ($preset) {
            'today' => [$today, $today],
            'yesterday' => [$today->subDay(), $today->subDay()],
            'last-7-days' => [$today->subDays(6), $today],
            'last-30-days' => [$today->subDays(29), $today],
            'last-90-days' => [$today->subDays(89), $today],
            'this-month' => [$today->startOfMonth(), $today],
            'previous-month' => [$today->startOfMonth()->subMonth(), $today->startOfMonth()->subMonth()->endOfMonth()],
            default => throw ValidationException::withMessages([
                'preset' => __('capell-referer::report.invalid_window'),
            ]),
        };
    }

    private function parseDate(?string $value, string $field): CarbonImmutable
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw ValidationException::withMessages([$field => __('capell-referer::report.invalid_date')]);
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, 'UTC');

        if (! $date instanceof CarbonImmutable || $date->format('Y-m-d') !== $value) {
            throw ValidationException::withMessages([$field => __('capell-referer::report.invalid_date')]);
        }

        return $date;
    }

    private function positiveConfigInteger(string $key, int $fallback): int
    {
        $value = config($key, $fallback);

        return is_int($value) && $value > 0 ? $value : $fallback;
    }

    private function recordedRetentionBoundary(): ?CarbonImmutable
    {
        try {
            $value = DB::table('referer_retention_state')->where('id', 1)->value('daily_available_from');

            return is_string($value) && $value !== '' ? CarbonImmutable::parse($value, 'UTC') : null;
        } catch (Throwable) {
            return null;
        }
    }
}
