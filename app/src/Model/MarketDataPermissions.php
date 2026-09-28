<?php

namespace App\Model;

use App\Admin\MarketDataAdmin;
use SilverStripe\Security\Permission;

/**
 * Shared permissions for market data records.
 *
 * Anyone can view (the data is public); only users with access to the
 * Market data admin section can change it. A trait rather than a common
 * parent class, because a parent DataObject would become a shared base table.
 */
trait MarketDataPermissions
{
    public function canView($member = null)
    {
        return true;
    }

    public function canEdit($member = null)
    {
        return Permission::check('CMS_ACCESS_' . MarketDataAdmin::class, 'any', $member);
    }

    public function canDelete($member = null)
    {
        return $this->canEdit($member);
    }

    public function canCreate($member = null, $context = [])
    {
        return $this->canEdit($member);
    }
}
