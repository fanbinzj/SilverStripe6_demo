<?php

namespace App\Extensions;

use App\Model\Stock;
use SilverStripe\Core\Extension;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\ManyManyList;
use SilverStripe\Security\Member;

/**
 * Adds a watchlist of stocks to each member.
 *
 * many_many creates a join table (Member_Watchlist) with MemberID and StockID;
 * many_many_extraFields adds columns to that join table, here the date added.
 *
 * @method ManyManyList<Stock> Watchlist()
 * @extends Extension<Member>
 */
class MemberExtension extends Extension
{
    private static array $many_many = [
        'Watchlist' => Stock::class,
    ];

    private static array $many_many_extraFields = [
        'Watchlist' => [
            'AddedAt' => 'Datetime',
        ],
    ];

    // Keep the list a reasonable size. Config declared on an extension is merged into the
    // owner class's config, so this is read as Member::config()->get('watchlist_limit').
    private static int $watchlist_limit = 100;

    /**
     * Deleting a record doesn't remove its many_many join rows, so clear them first.
     * (Not $cascade_deletes: that would delete the stocks themselves.)
     */
    protected function onBeforeDelete(): void
    {
        $this->getOwner()->Watchlist()->removeAll();
    }

    public function isWatching(Stock $stock): bool
    {
        return $this->getOwner()->Watchlist()->filter('ID', $stock->ID)->exists();
    }

    /**
     * @return bool false if the watchlist is full
     */
    public function addToWatchlist(Stock $stock): bool
    {
        $owner = $this->getOwner();
        if ($owner->isWatching($stock)) {
            return true;
        }
        if ($owner->Watchlist()->count() >= $owner->config()->get('watchlist_limit')) {
            return false;
        }
        $owner->Watchlist()->add($stock, ['AddedAt' => DBDatetime::now()->getValue()]);
        return true;
    }
}
