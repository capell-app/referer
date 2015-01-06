<?php

declare(strict_types=1);

namespace Capell\Referer\Console\Commands;

use Capell\Referer\Actions\SeedRefererScreenshotFixtureAction;
use Illuminate\Console\Command;
use RuntimeException;

final class SeedRefererScreenshotFixtureCommand extends Command
{
    protected $signature = 'capell:referer:screenshot-fixture {--force : Confirm an intentional disposable screenshot seed}';

    protected $description = 'Seed aggregate referral counts in an explicit disposable screenshot App.';

    public function handle(): int
    {
        if (! $this->option('force')) {
            $this->error('Refusing to seed screenshot fixtures without --force.');

            return self::FAILURE;
        }

        try {
            $sites = SeedRefererScreenshotFixtureAction::run();
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf('Referer screenshot fixture initialised for %d sites.', $sites));

        return self::SUCCESS;
    }
}
