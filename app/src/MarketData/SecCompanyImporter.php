<?php

namespace App\MarketData;

use App\Model\Filing;
use App\Model\NameChange;
use App\Model\Stock;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\Connect\DatabaseException;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBDatetime;

/**
 * Imports one company's background, former names, offering-related filings and
 * key XBRL figures from SEC EDGAR. Two HTTP requests per stock.
 */
class SecCompanyImporter
{
    use Configurable;
    use Injectable;

    // Offering-related filings older than this are not imported
    private static int $filing_lookback_years = 5;

    // How many times to run the database update when it conflicts with another process
    private static int $max_transaction_attempts = 3;

    public function __construct(
        private readonly SecClient $sec,
    ) {
    }

    public function importStock(Stock $stock): void
    {
        // Fetch once; only the database part is retried
        $submissions = $this->sec->getSubmissions($stock->CIK);
        $facts = $this->sec->getCompanyFacts($stock->CIK);

        $attempts = static::config()->get('max_transaction_attempts');
        for ($attempt = 1; ; $attempt++) {
            try {
                DB::get_conn()->withTransaction(function () use ($stock, $submissions, $facts) {
                    if ($submissions) {
                        $this->applySubmissions($stock, $submissions);
                    }
                    $this->applyFacts($stock, $facts ? new XbrlFacts($facts) : null);
                    $stock->SecDataImportedAt = DBDatetime::now()->getValue();
                    $stock->write();
                });
                return;
            } catch (DatabaseException $e) {
                // Another process (e.g. the quote import) changed the same rows while this transaction
                // ran. MySQL/MariaDB reject the commit and ask for the transaction to be restarted:
                // deadlocks (1213) and, with MariaDB's innodb_snapshot_isolation, "record has changed
                // since last read" (1020). Start again from fresh data.
                if ($attempt >= $attempts || !str_contains($e->getMessage(), 'try restarting transaction')) {
                    throw $e;
                }
                $stock = Stock::get()->byID($stock->ID);
            }
        }
    }

    private function applySubmissions(Stock $stock, array $submissions): void
    {
        $stock->StateOfIncorporation = UsStates::name(
            $submissions['stateOfIncorporationDescription'] ?: $submissions['stateOfIncorporation'] ?: ''
        );
        $stock->Industry = $submissions['sicDescription'] ?? '';

        // Former names: replace the whole set, it's small and the SEC list is authoritative
        $stock->NameChanges()->removeAll();
        foreach ($submissions['formerNames'] ?? [] as $former) {
            NameChange::create([
                'StockID' => $stock->ID,
                'FormerName' => $former['name'],
                'UsedFrom' => substr($former['from'] ?? '', 0, 10) ?: null,
                'UsedTo' => substr($former['to'] ?? '', 0, 10) ?: null,
            ])->write();
        }

        $this->importFilings($stock, $submissions['filings']['recent'] ?? []);
    }

    /**
     * "recent" holds parallel arrays: form[i], filingDate[i], accessionNumber[i], ...
     */
    private function importFilings(Stock $stock, array $recent): void
    {
        $cutoff = date('Y-m-d', strtotime('-' . static::config()->get('filing_lookback_years') . ' years'));
        $known = $stock->Filings()->column('AccessionNumber');

        foreach ($recent['form'] ?? [] as $i => $form) {
            $accession = $recent['accessionNumber'][$i];
            if (
                !in_array($form, Filing::DILUTION_FORMS, true)
                || $recent['filingDate'][$i] < $cutoff
                || in_array($accession, $known, true)
            ) {
                continue;
            }
            Filing::create([
                'StockID' => $stock->ID,
                'FormType' => $form,
                'AccessionNumber' => $accession,
                'FiledDate' => $recent['filingDate'][$i],
                'PrimaryDocument' => $recent['primaryDocument'][$i],
                'Description' => $recent['primaryDocDescription'][$i] ?? '',
            ])->write();
        }
    }

    /**
     * Each figure is either set with its "as of" date, or cleared (unknown) if the SEC has no value.
     */
    private function applyFacts(Stock $stock, ?XbrlFacts $facts): void
    {
        $this->setFact($stock, 'SharesOutstanding', $facts?->latestInstant('dei', ['EntityCommonStockSharesOutstanding'], 'shares'));
        $this->setFact($stock, 'PublicFloat', $facts?->latestInstant('dei', ['EntityPublicFloat'], 'USD'));
        $this->setFact($stock, 'Cash', $facts?->latestInstant('us-gaap', [
            'CashAndCashEquivalentsAtCarryingValue',
            'CashCashEquivalentsRestrictedCashAndRestrictedCashEquivalents',
        ], 'USD'));
        $this->setFact(
            $stock,
            'OperatingCashFlow3M',
            $facts?->latestQuarter('us-gaap', 'NetCashProvidedByUsedInOperatingActivities', 'USD'),
            'OperatingCashFlowAsOf'
        );
    }

    /**
     * @param array{value: float, asOf: string}|null $fact
     */
    private function setFact(Stock $stock, string $field, ?array $fact, ?string $asOfField = null): void
    {
        $asOfField ??= $field . 'AsOf';
        $stock->$field = $fact ? (int) round($fact['value']) : 0;
        $stock->$asOfField = $fact['asOf'] ?? null;
    }
}
