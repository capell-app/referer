<?php

declare(strict_types=1);

namespace Capell\Referer\Data;

use Spatie\LaravelData\Data;

final class RefererReportRowData extends Data
{
    public function __construct(
        public readonly string $sourceKey,
        public readonly string $label,
        public readonly int $count,
        public readonly float $share,
    ) {}
}
