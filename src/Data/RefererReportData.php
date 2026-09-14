<?php

declare(strict_types=1);

namespace Capell\Referer\Data;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

final class RefererReportData extends Data
{
    /** @param Collection<int, RefererReportRowData> $rows */
    public function __construct(
        public readonly Collection $rows,
        public readonly int $measuredRequests,
        public readonly RefererWindowData $window,
        public readonly CarbonImmutable $generatedAt,
        public readonly ?CarbonImmutable $collectionStartedOn,
        public readonly ?CarbonImmutable $availableThroughOn,
        public readonly bool $available = true,
    ) {}

    public function isEmpty(): bool
    {
        return $this->available && $this->measuredRequests === 0;
    }
}
