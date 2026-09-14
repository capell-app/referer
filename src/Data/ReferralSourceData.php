<?php

declare(strict_types=1);

namespace Capell\Referer\Data;

use Spatie\LaravelData\Data;

final class ReferralSourceData extends Data
{
    public function __construct(public readonly string $key) {}
}
