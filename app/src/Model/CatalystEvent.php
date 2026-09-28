<?php

namespace App\Model;

use SilverStripe\ORM\DataObject;

/**
 * A scheduled date that may matter to a stock: earnings, FDA decision,
 * lock-up expiry or shareholder meeting.
 *
 * @property string $Type
 * @property string $EventDate
 * @property bool $IsDateConfirmed
 * @property string $Title
 * @property string $Details
 * @property string $SourceUrl
 * @property int $StockID
 * @method Stock Stock()
 */
class CatalystEvent extends DataObject
{
    use MarketDataPermissions;

    public const array TYPE_LABELS = [
        'Earnings' => 'Earnings',
        'FDA' => 'FDA date',
        'LockupExpiry' => 'Lock-up expiry',
        'ShareholderMeeting' => 'Shareholder meeting',
        'Other' => 'Other',
    ];

    private static string $table_name = 'CatalystEvent';

    private static array $db = [
        'Type' => "Enum('Earnings,FDA,LockupExpiry,ShareholderMeeting,Other', 'Other')",
        'EventDate' => 'Date',
        'IsDateConfirmed' => 'Boolean',
        'Title' => 'Varchar(255)',
        'Details' => 'Text',
        'SourceUrl' => 'Varchar(500)',
    ];

    private static array $has_one = [
        'Stock' => Stock::class,
    ];

    private static array $indexes = [
        'EventDate' => true,
    ];

    private static string $default_sort = '"EventDate" ASC';

    private static array $summary_fields = [
        'EventDate' => 'Date',
        'Stock.Ticker' => 'Ticker',
        'TypeLabel' => 'Type',
        'Title',
    ];

    private static array $searchable_fields = [
        'Stock.Ticker' => [
            'title' => 'Ticker',
            'filter' => 'ExactMatchFilter',
        ],
        'Type',
        'EventDate',
    ];

    public function getTypeLabel(): string
    {
        return self::TYPE_LABELS[$this->Type] ?? (string) $this->Type;
    }
}
