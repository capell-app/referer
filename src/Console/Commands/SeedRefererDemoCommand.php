<?php

declare(strict_types=1);

namespace Capell\Referer\Console\Commands;

use Capell\Referer\Actions\SeedRefererDemoCountsAction;
use Illuminate\Console\Command;
use RuntimeException;

final class SeedRefererDemoCommand extends Command
{
    protected $signature = 'capell:referer:demo';

    protected $description = 'Give every site sample referral counts for the demo.';

    public function handle(): int
    {
        // Demo counts overwrite today's real counts and the all-time totals, so they never run on a live site.
        if ($this->laravel->environment('production')) {
            $this->error((string) __('capell-referer::package.demo.production_refused'));

            return self::FAILURE;
        }

        try {
            $sites = SeedRefererDemoCountsAction::run();
        } catch (RuntimeException $runtimeException) {
            $this->error($runtimeException->getMessage());

            return self::FAILURE;
        }

        $this->info((string) __('capell-referer::package.demo.seeded', ['count' => $sites]));

        return self::SUCCESS;
    }
}
