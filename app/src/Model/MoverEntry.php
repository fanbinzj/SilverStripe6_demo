<?php

namespace App\Model;

use SilverStripe\ORM\DataObject;

/**
 * One row of a movers list: a stock's price move in one session on one trading day.
 *
 * @property string $TradingDate
 * @property string $Session
 * @property int $Rank
 * @property float $Price
 * @property float $ChangePercent
 * @property int $Volume
 * @property int $StockID
 * @method Stock Stock()
 */
class MoverEntry extends DataObject
{
    use MarketDataPermissions;

    private static string $table_name = 'MoverEntry';

    private static array $db = [
        'TradingDate' => 'Date',
        // Values match App\Model\MarketSession
        'Session' => "Enum('pre-market,market-hours,after-hours', 'market-hours')",
        'Rank' => 'Int',
        'Price' => 'Decimal(14,4)',
        'ChangePercent' => 'Decimal(8,2)',
        'Volume' => 'BigInt',
    ];

    private static array $has_one = [
        'Stock' => Stock::class,
    ];

    // No explicit $indexes needed: CMS 6 creates indexes for $default_sort columns,
    // including a composite (TradingDate, Session, Rank) that covers movers page queries.
    private static string $default_sort = '"TradingDate" DESC, "Session" ASC, "Rank" ASC';

    private static array $summary_fields = [
        'TradingDate' => 'Date',
        'SessionLabel' => 'Session',
        'Rank',
        'Stock.Ticker' => 'Ticker',
        'ChangePercent' => 'Change %',
        'Price',
    ];

    private static array $searchable_fields = [
        'TradingDate',
        'Session',
        'Stock.Ticker' => [
            'title' => 'Ticker',
            'filter' => 'ExactMatchFilter',
        ],
    ];

    public function getPriceNice(): string
    {
        return '$' . number_format((float) $this->Price, $this->Price < 1 ? 4 : 2);
    }

    public function getChangePercentNice(): string
    {
        return sprintf('%+.2f%%', $this->ChangePercent);
    }

    public function getMarketSession(): MarketSession
    {
        return MarketSession::from($this->Session);
    }

    public function getSessionLabel(): string
    {
        return $this->getMarketSession()->label();
    }
}
