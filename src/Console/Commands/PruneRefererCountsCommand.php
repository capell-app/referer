<?php

declare(strict_types=1);

namespace Capell\Referer\Console\Commands;

use Capell\Referer\Actions\PruneRefererCountsAction;
use Illuminate\Console\Command;

final class PruneRefererCountsCommand extends Command
{
    protected $signature = 'capell:referer:prune {--days= : Retain this many UTC days} {--batch=500 : Maximum rows per delete batch} {--dry-run : Count eligible rows without deleting them}';

    protected $description = 'Prune expired daily referral counters while preserving lifetime totals.';

    public function handle(): int
    {
        $days = $this->option('days');
        $batch = $this->option('batch');
        $retentionDays = $days === null ? null : (int) $days;
        $batchSize = $batch === null ? 500 : (int) $batch;

        if (($retentionDays !== null && $retentionDays < 1) || $batchSize < 1) {
            $this->error('Days and batch must be positive integers.');

            return self::FAILURE;
        }

        $deleted = PruneRefererCountsAction::run($retentionDays, $batchSize, (bool) $this->option('dry-run'));
        $verb = $this->option('dry-run') ? 'eligible' : 'deleted';
        $this->info(sprintf('%d daily referral rows %s.', $deleted, $verb));

        return self::SUCCESS;
    }
}
