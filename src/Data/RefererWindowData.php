<?php

declare(strict_types=1);

namespace Capell\Referer\Data;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Spatie\LaravelData\Data;

final class RefererWindowData extends Data
{
    public function __construct(
        public readonly ?CarbonImmutable $startsOn,
        public readonly ?CarbonImmutable $endsOn,
        public readonly bool $allTime = false,
    ) {
        if ($this->allTime && ($this->startsOn instanceof CarbonImmutable || $this->endsOn instanceof CarbonImmutable)) {
            throw new InvalidArgumentException('An all-time referral window cannot contain dates.');
        }

        if (! $this->allTime && (! $this->startsOn instanceof CarbonImmutable || ! $this->endsOn instanceof CarbonImmutable)) {
            throw new InvalidArgumentException('A dated referral window requires both dates.');
        }

        if ($this->startsOn instanceof CarbonImmutable && $this->endsOn instanceof CarbonImmutable && $this->startsOn->greaterThan($this->endsOn)) {
            throw new InvalidArgumentException('A referral window cannot be reversed.');
        }
    }

    public function cacheKey(): string
    {
        return $this->allTime
            ? 'all-time'
            : sprintf('%s:%s', $this->startsOn?->toDateString(), $this->endsOn?->toDateString());
    }
}
