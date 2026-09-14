<?php

declare(strict_types=1);

namespace Capell\Referer\Manifest;

use Capell\Core\Contracts\Extensions\ExtensionContribution;

final class RefererModelsContribution implements ExtensionContribution
{
    public static function compatibleCapellApiVersion(): string
    {
        return '^1.0';
    }
}
