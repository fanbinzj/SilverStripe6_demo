<?php

namespace App\Model;

use SilverStripe\ORM\DataObject;

/**
 * An SEC filing. Offering details (shares, price, proceeds) are extracted
 * from dilution-related filings; ExtractedAt is empty until that has run.
 *
 * @property string $FormType
 * @property string $AccessionNumber
 * @property string $FiledDate
 * @property string $PrimaryDocument
 * @property string $Description
 * @property int $SharesOffered
 * @property float $OfferingPrice
 * @property int $GrossProceeds
 * @property string $ExtractedAt
 * @property int $StockID
 * @method Stock Stock()
 */
class Filing extends DataObject
{
    use MarketDataPermissions;

    /**
     * Registration statements and prospectuses that can lead to new shares being issued.
     */
    public const array DILUTION_FORMS = [
        'S-1', 'S-1/A', 'S-3', 'S-3/A', 'F-1', 'F-1/A', 'F-3', 'F-3/A',
        '424B1', '424B2', '424B3', '424B4', '424B5', '424B7',
    ];

    private static string $table_name = 'Filing';

    private static array $db = [
        'FormType' => 'Varchar(20)',
        'AccessionNumber' => 'Varchar(25)',
        'FiledDate' => 'Date',
        'PrimaryDocument' => 'Varchar(255)',
        'Description' => 'Varchar(255)',
        'SharesOffered' => 'BigInt',
        'OfferingPrice' => 'Decimal(14,4)',
        'GrossProceeds' => 'BigInt',
        'ExtractedAt' => 'Datetime',
    ];

    private static array $has_one = [
        'Stock' => Stock::class,
    ];

    private static array $indexes = [
        // A filing can belong to more than one stock: several share classes under one CIK,
        // or a filing made jointly by more than one company. Unique per stock, not globally.
        'StockAccession' => ['type' => 'unique', 'columns' => ['StockID', 'AccessionNumber']],
        'AccessionNumber' => true,
        'FormType' => true,
        'FiledDate' => true,
    ];

    private static string $default_sort = '"FiledDate" DESC';

    private static array $summary_fields = [
        'FiledDate' => 'Filed',
        'Stock.Ticker' => 'Ticker',
        'FormType' => 'Form',
        'SharesOffered' => 'Shares offered',
        'OfferingPrice' => 'Price',
    ];

    private static array $searchable_fields = [
        'Stock.Ticker' => [
            'title' => 'Ticker',
            'filter' => 'ExactMatchFilter',
        ],
        'FormType' => 'StartsWithFilter',
        'FiledDate',
    ];

    public function getTitle(): string
    {
        return trim("{$this->FormType} {$this->FiledDate}");
    }

    public function isDilutionForm(): bool
    {
        return in_array($this->FormType, self::DILUTION_FORMS, true);
    }

    public function getOfferingPriceNice(): string
    {
        if (!$this->ExtractedAt || !$this->OfferingPrice) {
            return '';
        }
        return '$' . number_format((float) $this->OfferingPrice, $this->OfferingPrice < 1 ? 4 : 2);
    }

    /**
     * Link to the filing document on EDGAR.
     */
    public function getUrl(): string
    {
        $cik = (int) $this->Stock()->CIK;
        $folder = str_replace('-', '', (string) $this->AccessionNumber);
        return "https://www.sec.gov/Archives/edgar/data/{$cik}/{$folder}/{$this->PrimaryDocument}";
    }
}
