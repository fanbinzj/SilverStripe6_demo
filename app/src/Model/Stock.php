<?php

namespace App\Model;

use App\Pages\StockDirectoryPage;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HeaderField;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\HasManyList;
use SilverStripe\ORM\ManyManyList;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;

/**
 * A US exchange-listed company, keyed by ticker.
 *
 * Numeric SS fields cannot be NULL, so each fact has an "as of" date:
 * an empty date means the value is unknown, not zero.
 *
 * @property string $Ticker
 * @property string $Name
 * @property int $CIK
 * @property string $Exchange
 * @property bool $IsListed
 * @property bool $InScope
 * @property float $LastPrice
 * @property int $MarketCap
 * @property string $PriceAsOf
 * @property int $SharesOutstanding
 * @property string $SharesOutstandingAsOf
 * @property int $PublicFloat
 * @property string $PublicFloatAsOf
 * @property int $Cash
 * @property string $CashAsOf
 * @property int $OperatingCashFlow3M
 * @property string $OperatingCashFlowAsOf
 * @property float $InsiderPercent
 * @property float $InstitutionalPercent
 * @property string $OwnershipAsOf
 * @property string $StateOfIncorporation
 * @property string $Auditor
 * @property string $Industry
 * @property string $LastImportedAt
 * @property string $SecDataImportedAt
 * @method HasManyList<Filing> Filings()
 * @method HasManyList<ReverseSplit> ReverseSplits()
 * @method HasManyList<NameChange> NameChanges()
 * @method HasManyList<CatalystEvent> CatalystEvents()
 * @method HasManyList<MoverEntry> MoverEntries()
 * @method HasManyList<DataIssueReport> DataIssueReports()
 * @method ManyManyList<Member> Watchers()
 */
class Stock extends DataObject
{
    use MarketDataPermissions;

    private static string $table_name = 'Stock';

    private static string $singular_name = 'Stock';

    private static string $plural_name = 'Stocks';

    private static array $db = [
        'Ticker' => 'Varchar(10)',
        'Name' => 'Varchar(255)',
        'CIK' => 'Int',
        'Exchange' => 'Varchar(20)',
        // Still in the SEC's list of exchange-listed companies
        'IsListed' => 'Boolean(1)',
        // Within the site's scope (e.g. market cap limit); set by the market data import
        'InScope' => 'Boolean(0)',

        // Fundamentals snapshot
        'LastPrice' => 'Decimal(14,4)',
        'MarketCap' => 'BigInt',
        'PriceAsOf' => 'Datetime',
        'SharesOutstanding' => 'BigInt',
        'SharesOutstandingAsOf' => 'Date',
        'PublicFloat' => 'BigInt', // USD value of shares held by non-affiliates, as reported to the SEC
        'PublicFloatAsOf' => 'Date',
        'Cash' => 'BigInt',
        'CashAsOf' => 'Date',
        'OperatingCashFlow3M' => 'BigInt', // most recent quarter; negative means cash going out
        'OperatingCashFlowAsOf' => 'Date',

        // Ownership
        'InsiderPercent' => 'Decimal(6,2)',
        'InstitutionalPercent' => 'Decimal(6,2)',
        'OwnershipAsOf' => 'Date',

        // Company background
        'StateOfIncorporation' => 'Varchar(50)',
        'Auditor' => 'Varchar(255)',
        'Industry' => 'Varchar(255)',

        'LastImportedAt' => 'Datetime',
        'SecDataImportedAt' => 'Datetime',
    ];

    private static array $has_many = [
        'Filings' => Filing::class,
        'ReverseSplits' => ReverseSplit::class,
        'NameChanges' => NameChange::class,
        'CatalystEvents' => CatalystEvent::class,
        'MoverEntries' => MoverEntry::class,
        'DataIssueReports' => DataIssueReport::class,
    ];

    // The other side of Member.Watchlist (MemberExtension); no extra table
    private static array $belongs_many_many = [
        'Watchers' => Member::class . '.Watchlist',
    ];

    // Deleting a stock deletes its related records
    private static array $cascade_deletes = [
        'Filings',
        'ReverseSplits',
        'NameChanges',
        'CatalystEvents',
        'MoverEntries',
        'DataIssueReports',
    ];

    private static array $indexes = [
        'Ticker' => ['type' => 'unique'],
        'CIK' => true,
        'InScope' => true,
    ];

    private static string $default_sort = '"Ticker" ASC';

    private static array $summary_fields = [
        'Ticker',
        'Name',
        'Exchange',
        'MarketCapNice' => 'Market cap',
        'InScope.Nice' => 'In scope',
    ];

    private static array $searchable_fields = [
        'Ticker' => 'StartsWithFilter',
        'Name' => 'PartialMatchFilter',
        'Exchange' => 'ExactMatchFilter',
        'InScope',
    ];

    private static array $field_labels = [
        'CIK' => 'SEC CIK',
        'IsListed' => 'Listed on an exchange',
        'InScope' => 'In scope',
        'OperatingCashFlow3M' => 'Operating cash flow (quarter)',
        'PublicFloat' => 'Public float (USD)',
    ];

    protected function onBeforeDelete()
    {
        parent::onBeforeDelete();
        // Remove the stock from watchlists (join rows only; members are not affected)
        $this->Watchers()->removeAll();
    }

    public function getTitle(): string
    {
        return $this->Ticker ? "{$this->Ticker}: {$this->Name}" : (string) $this->Name;
    }

    public function getCMSFields()
    {
        $this->beforeUpdateCMSFields(function (FieldList $fields) {
            $this->moveFieldsToTab($fields, 'Root.Fundamentals', [
                'LastPrice', 'MarketCap', 'PriceAsOf',
                'SharesOutstanding', 'SharesOutstandingAsOf',
                'PublicFloat', 'PublicFloatAsOf',
                'Cash', 'CashAsOf',
                'OperatingCashFlow3M', 'OperatingCashFlowAsOf',
            ]);
            $this->moveFieldsToTab($fields, 'Root.Ownership', [
                'InsiderPercent', 'InstitutionalPercent', 'OwnershipAsOf',
            ]);
            $this->moveFieldsToTab($fields, 'Root.Background', [
                'StateOfIncorporation', 'Auditor', 'Industry',
            ]);
            $fields->addFieldToTab(
                'Root.Fundamentals',
                HeaderField::create('FundamentalsNote', 'An empty "as of" date means the value is unknown', 4),
                'LastPrice'
            );
            $fields->makeFieldReadonly(['LastImportedAt', 'SecDataImportedAt']);
        });

        return parent::getCMSFields();
    }

    /**
     * Profile page URL: /stocks/TICKER
     */
    public function Link(?string $action = null): string
    {
        $directory = StockDirectoryPage::get_instance();
        return $directory ? $directory->Link(rawurlencode($this->Ticker) . ($action ? "/{$action}" : '')) : '';
    }

    /**
     * Whether the logged-in member has this stock on their watchlist (false when logged out).
     */
    public function getIsOnCurrentWatchlist(): bool
    {
        $member = Security::getCurrentUser();
        return $member && $member->isWatching($this);
    }

    /**
     * @return DataList<Filing>
     */
    public function getDilutionFilings(): DataList
    {
        return $this->Filings()->filter('FormType', Filing::DILUTION_FORMS);
    }

    /**
     * @return DataList<CatalystEvent>
     */
    public function getUpcomingCatalysts(): DataList
    {
        return $this->CatalystEvents()->filter('EventDate:GreaterThanOrEqual', date('Y-m-d', DBDatetime::now()->getTimestamp()));
    }

    /**
     * @return DataList<CatalystEvent>
     */
    public function getUpcomingLockupExpiries(): DataList
    {
        return $this->getUpcomingCatalysts()->filter('Type', 'LockupExpiry');
    }

    public function getLastPriceNice(): string
    {
        if (!$this->PriceAsOf) {
            return '';
        }
        // Sub-dollar prices need more decimal places to be meaningful
        return '$' . number_format((float) $this->LastPrice, $this->LastPrice < 1 ? 4 : 2);
    }

    public function getCashNice(): string
    {
        return $this->CashAsOf ? self::formatCompactUsd($this->Cash) : '';
    }

    public function getOperatingCashFlowNice(): string
    {
        return $this->OperatingCashFlowAsOf ? self::formatCompactUsd($this->OperatingCashFlow3M) : '';
    }

    public function getPublicFloatNice(): string
    {
        return $this->PublicFloatAsOf ? self::formatCompactUsd($this->PublicFloat) : '';
    }

    /**
     * Zero-padded CIK, as used in SEC URLs and APIs.
     */
    public function getPaddedCIK(): string
    {
        return str_pad((string) $this->CIK, 10, '0', STR_PAD_LEFT);
    }

    public function getSecFilingsUrl(): string
    {
        return 'https://www.sec.gov/cgi-bin/browse-edgar?action=getcompany&CIK=' . $this->getPaddedCIK();
    }

    public function getMarketCapNice(): string
    {
        return $this->PriceAsOf ? self::formatCompactUsd($this->MarketCap) : '';
    }

    /**
     * Months of cash left at the latest quarterly operating cash outflow.
     * A simple arithmetic metric, not a forecast. Null when it can't be calculated.
     */
    public function getCashRunwayMonths(): ?float
    {
        if (!$this->CashAsOf || !$this->OperatingCashFlowAsOf || $this->OperatingCashFlow3M >= 0) {
            return null;
        }
        $monthlyOutflow = -$this->OperatingCashFlow3M / 3;
        return round($this->Cash / $monthlyOutflow, 1);
    }

    public static function formatCompactUsd(int|float $amount): string
    {
        $abs = abs($amount);
        [$divisor, $suffix] = match (true) {
            $abs >= 1e9 => [1e9, 'B'],
            $abs >= 1e6 => [1e6, 'M'],
            $abs >= 1e3 => [1e3, 'K'],
            default => [1, ''],
        };
        $sign = $amount < 0 ? '-' : '';
        return $sign . '$' . number_format($abs / $divisor, $divisor > 1 ? 1 : 0) . $suffix;
    }

    /**
     * Remove fields from wherever they were scaffolded and add them to another tab.
     */
    private function moveFieldsToTab(FieldList $fields, string $tab, array $names): void
    {
        $moved = [];
        foreach ($names as $name) {
            $field = $fields->dataFieldByName($name);
            if ($field) {
                $fields->removeByName($name);
                $moved[] = $field;
            }
        }
        $fields->addFieldsToTab($tab, $moved);
    }
}
