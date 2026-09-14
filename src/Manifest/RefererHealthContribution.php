<?php

declare(strict_types=1);

namespace Capell\Referer\Manifest;

use Capell\Core\Contracts\Extensions\ChecksExtensionHealth;

final class RefererHealthContribution implements ChecksExtensionHealth
{
    public static function compatibleCapellApiVersion(): string
    {
        return '^1.0';
    }
}
