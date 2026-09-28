<?php

namespace App\Pages;

use App\MarketData\QuoteProvider;
use App\Model\MarketSession;
use App\Model\MoverEntry;
use PageController;
use SilverStripe\Model\ArrayData;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\FieldType\DBField;

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

    private static $dependencies = [
        'quoteProvider' => '%$' . QuoteProvider::class,
    ];

    private QuoteProvider $quoteProvider;

    public function setQuoteProvider(QuoteProvider $quoteProvider): static
    {
        $this->quoteProvider = $quoteProvider;
        return $this;
    }

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

        $entries = MoverEntry::get()->filter('Session', $current->value);
        $tradingDate = $entries->max('TradingDate');
        $limit = max(1, (int) $this->data()->RowLimit);

        return [
            'SessionTabs' => $tabs,
            'SessionTitle' => $current->label(),
            'SessionHours' => $current->hours(),
            'HasDataSource' => $this->quoteProvider->supportsSession($current),
            'SourceName' => $this->quoteProvider->getSourceName(),
            // max() returns plain strings; wrap them as DB fields so templates can use .Nice
            'TradingDate' => DBField::create_field('Date', $tradingDate),
            'UpdatedAt' => DBField::create_field(
                'Datetime',
                $tradingDate ? $entries->filter('TradingDate', $tradingDate)->max('Created') : null
            ),
            'Gainers' => $this->moversList($entries, $tradingDate, 'ChangePercent:GreaterThan', $limit),
            'Losers' => $this->moversList($entries, $tradingDate, 'ChangePercent:LessThan', $limit),
        ];
    }

    /**
     * @return DataList<MoverEntry>
     */
    private function moversList(DataList $entries, ?string $tradingDate, string $filter, int $limit): DataList
    {
        return $entries
            ->filter(['TradingDate' => $tradingDate, $filter => 0])
            ->sort('Rank')
            ->limit($limit)
            // Load all the stocks in one query instead of one query per row (N+1)
            ->eagerLoad('Stock');
    }
}
