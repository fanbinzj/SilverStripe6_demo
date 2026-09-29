<?php

namespace App\Controllers;

use App\Model\Stock;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Security\Security;
use SilverStripe\Security\SecurityToken;

/**
 * POST /watchlist/add      StockID=..  add a stock to the member's watchlist
 * POST /watchlist/remove   StockID=..  remove it
 *
 * Not a page: routed directly in app/_config/routes.yml. Both actions change data,
 * so they only accept POST with a valid CSRF token, and require a logged-in member.
 */
class WatchlistController extends Controller
{
    private static $url_segment = 'watchlist';

    private static $allowed_actions = [
        'add',
        'remove',
    ];

    public function index()
    {
        return $this->httpError(404);
    }

    public function add(HTTPRequest $request): HTTPResponse
    {
        return $this->change($request, true);
    }

    public function remove(HTTPRequest $request): HTTPResponse
    {
        return $this->change($request, false);
    }

    private function change(HTTPRequest $request, bool $add): HTTPResponse
    {
        if (!$request->isPOST()) {
            return $this->httpError(405, 'Use POST');
        }
        if (!SecurityToken::inst()->checkRequest($request)) {
            return $this->httpError(400, 'Your session expired. Please go back and try again.');
        }

        $member = Security::getCurrentUser();
        if (!$member) {
            return Security::permissionFailure($this, 'Log in to use your watchlist.');
        }

        $stock = Stock::get()->byID((int) $request->postVar('StockID'));
        if (!$stock) {
            return $this->httpError(404, 'Stock not found');
        }

        if ($add) {
            if (!$member->addToWatchlist($stock)) {
                return $this->httpError(400, 'Your watchlist is full. Remove a stock before adding another.');
            }
        } else {
            $member->Watchlist()->remove($stock);
        }

        // BackURL (if given) or the referring page; redirectBack() only allows URLs on this site
        return $this->redirectBack();
    }
}
