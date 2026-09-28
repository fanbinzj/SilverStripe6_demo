<?php

namespace App\Model;

use SilverStripe\ORM\DataObject;

/**
 * A name the company used before, as reported by the SEC.
 *
 * @property string $FormerName
 * @property string $UsedFrom
 * @property string $UsedTo
 * @property int $StockID
 * @method Stock Stock()
 */
class NameChange extends DataObject
{
    use MarketDataPermissions;

    private static string $table_name = 'NameChange';

    private static array $db = [
        'FormerName' => 'Varchar(255)',
        'UsedFrom' => 'Date',
        'UsedTo' => 'Date',
    ];

    private static array $has_one = [
        'Stock' => Stock::class,
    ];

    private static string $default_sort = '"UsedTo" DESC';

    private static array $summary_fields = [
        'FormerName' => 'Former name',
        'UsedFrom' => 'From',
        'UsedTo' => 'To',
    ];

    public function getTitle(): string
    {
        return (string) $this->FormerName;
    }
}
