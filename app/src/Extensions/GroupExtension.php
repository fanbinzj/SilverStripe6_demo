<?php

namespace App\Extensions;

use App\Security\Membership;
use SilverStripe\Core\Extension;
use SilverStripe\Security\Group;

/**
 * Creates the "Members" group on db:build.
 *
 * @extends Extension<Group>
 */
class GroupExtension extends Extension
{
    protected function onRequireDefaultRecords(): void
    {
        Membership::group();
    }
}
