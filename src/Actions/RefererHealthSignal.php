<?php

declare(strict_types=1);

namespace Capell\Referer\Actions;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

final class RefererHealthSignal
{
    private const string FAILURE_KEY = 'capell-referer.health.write-failure';

    public function recordWriteFailure(): void
    {
        try {
            $ttl = now()->addSeconds($this->positiveConfigInteger('capell-referer.health_signal_ttl_seconds', 300));
            if (Cache::add(self::FAILURE_KEY, true, $ttl)) {
                Log::warning('Capell Referer collection is temporarily unavailable.');
            }
        } catch (Throwable) {
            // A measurement failure must never become a page failure.
        }
    }

    public function hasRecentWriteFailure(): bool
    {
        try {
            return Cache::has(self::FAILURE_KEY);
        } catch (Throwable) {
            return false;
        }
    }

    private function positiveConfigInteger(string $key, int $fallback): int
    {
        $value = config($key, $fallback);

        return is_int($value) && $value > 0 ? $value : $fallback;
    }
}
