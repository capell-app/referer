<?php

declare(strict_types=1);

namespace Capell\Referer\Manifest;

use Capell\Core\Contracts\Extensions\DeclaresPermissions;
use Capell\Core\Contracts\Extensions\ExtensionContribution;
use Capell\Core\Contracts\Extensions\RegistersExtensionPermission;
use Capell\Core\Data\Permissions\PermissionData;
use Capell\Core\Data\Permissions\PermissionSetData;

final class RefererPermissionsContribution implements DeclaresPermissions, ExtensionContribution, RegistersExtensionPermission
{
    /** The keys capell.json `permissions` lists, which its checks name literally. */
    public const array KEYS = [
        'View:RefererPage',
    ];

    public static function compatibleCapellApiVersion(): string
    {
        return '^1.0';
    }

    public function permissions(): PermissionSetData
    {
        return new PermissionSetData(array_map(
            static fn (string $key): PermissionData => new PermissionData(
                key: $key,
                label: (string) __('capell-referer::permissions.labels.' . $key),
                group: (string) __('capell-referer::permissions.groups.' . $key),
            ),
            self::KEYS,
        ));
    }
}
