<?php

namespace App\Pages;

use App\Model\MarketSession;
use PageController;
use SilverStripe\Model\ArrayData;
use SilverStripe\Model\List\ArrayList;

/**
 * /movers                -> market hours (default)
 * /movers/pre-market     -> pre-market
 * /movers/after-hours    -> after-hours
 *
 * @extends PageController<MoversPage>
 */
class MoversPageController extends PageController
{
    private static $allowed_actions = [
        'session',
    ];

    // Map the hyphenated URL segments onto a single action
    private static $url_handlers = [
        '$Session!' => 'session',
    ];

    public function index()
    {
        return $this->renderSession(MarketSession::Regular);
    }

    public function session()
    {
        $session = MarketSession::tryFrom((string) $this->getRequest()->param('Session'));
        if (!$session) {
            return $this->httpError(404);
        }
        if ($session === MarketSession::Regular) {
            // Market hours is the default view; keep a single canonical URL
            return $this->redirect($this->Link(), 301);
        }
        return $this->renderSession($session);
    }

    private function renderSession(MarketSession $current): array
    {
        $tabs = ArrayList::create();
        foreach (MarketSession::cases() as $session) {
            $tabs->push(ArrayData::create([
                'Title' => $session->label(),
                'Hours' => $session->hours(),
                'Link' => $session === MarketSession::Regular
                    ? $this->Link()
                    : $this->Link($session->value),
                'IsCurrent' => $session === $current,
            ]));
        }

        return [
            'SessionTabs' => $tabs,
            'SessionTitle' => $current->label(),
            'SessionHours' => $current->hours(),
            // Populated from market data in stage 4
            'Movers' => ArrayList::create(),
        ];
    }
}
