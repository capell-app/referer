<?php

declare(strict_types=1);

namespace Capell\Referer\Enums;

enum RefererPermission: string
{
    case ViewPage = 'View:RefererPage';

    /** @return list<string> */
    public static function names(): array
    {
        return array_map(
            static fn (self $permission): string => $permission->value,
            self::cases(),
        );
    }
}
