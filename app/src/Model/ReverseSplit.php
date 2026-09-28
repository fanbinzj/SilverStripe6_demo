<?php

namespace App\Model;

use SilverStripe\Core\Validation\ValidationResult;
use SilverStripe\ORM\DataObject;

/**
 * A reverse stock split, e.g. 1-for-10 (SplitFrom = 10, SplitTo = 1).
 *
 * @property string $EffectiveDate
 * @property int $SplitFrom
 * @property int $SplitTo
 * @property string $SourceUrl
 * @property int $StockID
 * @method Stock Stock()
 */
class ReverseSplit extends DataObject
{
    use MarketDataPermissions;

    private static string $table_name = 'ReverseSplit';

    private static array $db = [
        'EffectiveDate' => 'Date',
        'SplitFrom' => 'Int',
        'SplitTo' => 'Int',
        'SourceUrl' => 'Varchar(500)',
    ];

    private static array $has_one = [
        'Stock' => Stock::class,
    ];

    private static array $defaults = [
        'SplitTo' => 1,
    ];

    private static string $default_sort = '"EffectiveDate" DESC';

    private static array $summary_fields = [
        'EffectiveDate' => 'Effective',
        'Ratio' => 'Ratio',
    ];

    private static array $field_labels = [
        'SplitFrom' => 'Old shares',
        'SplitTo' => 'New shares',
    ];

    public function getTitle(): string
    {
        return trim("{$this->getRatio()} {$this->EffectiveDate}");
    }

    public function getRatio(): string
    {
        return "{$this->SplitTo}-for-{$this->SplitFrom}";
    }

    public function validate(): ValidationResult
    {
        $result = parent::validate();
        if ($this->SplitTo < 1 || $this->SplitFrom <= $this->SplitTo) {
            $result->addFieldError('SplitFrom', 'In a reverse split, old shares must be more than new shares');
        }
        return $result;
    }
}
