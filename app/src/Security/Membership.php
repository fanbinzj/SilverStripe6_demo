<?php

namespace App\Security;

use SilverStripe\Security\Group;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;

/**
 * Website membership: the "Members" group and the permissions it grants.
 *
 * Implementing PermissionProvider makes the codes appear in the CMS under
 * Security > Groups > Permissions, where they can be given to any group.
 */
class Membership implements PermissionProvider
{
    public const string GROUP_CODE = 'members';

    public const string VIEW_ARCHIVE = 'VIEW_MOVERS_ARCHIVE';

    public function providePermissions(): array
    {
        return [
            self::VIEW_ARCHIVE => [
                'name' => 'View the movers archive',
                'category' => 'PennyMirror',
                'help' => 'Access to previous days\' movers lists when the archive is set to members only.',
            ],
        ];
    }

    /**
     * The group registered members join, created with its permissions if it doesn't exist yet.
     */
    public static function group(): Group
    {
        $group = Group::get()->filter('Code', self::GROUP_CODE)->first();
        if (!$group) {
            $group = Group::create([
                'Title' => 'Members',
                'Code' => self::GROUP_CODE,
                'Description' => 'People who registered on the website',
            ]);
            $group->write();
            Permission::grant($group->ID, self::VIEW_ARCHIVE);
        }
        return $group;
    }
}
