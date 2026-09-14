<?php

declare(strict_types=1);

namespace Capell\Referer\Manifest;

use Capell\Core\Contracts\Extensions\ExtensionContribution;
use Capell\Core\Contracts\Extensions\RunsScheduledExtensionJob;

final class RefererRetentionScheduleContribution implements ExtensionContribution, RunsScheduledExtensionJob
{
    public static function compatibleCapellApiVersion(): string
    {
        return '^1.0';
    }
}
