<?php

namespace App\Pages;

use App\Model\Stock;
use PageController;
use SilverStripe\ORM\ManyManyList;
use SilverStripe\Security\Security;

/**
 * @extends PageController<AccountPage>
 */
class AccountPageController extends PageController
{
    protected function init()
    {
        parent::init();

        if (!Security::getCurrentUser()) {
            Security::permissionFailure($this, 'Log in to see your account.');
        }
    }

    /**
     * @return ManyManyList<Stock>
     */
    public function getWatchlist(): ManyManyList
    {
        // AddedAt is a column on the join table (many_many_extraFields); it can be sorted on like a field
        return Security::getCurrentUser()->Watchlist()->sort('AddedAt', 'DESC');
    }
}
