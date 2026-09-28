<?php

namespace App\Tasks;

use App\Model\Stock;
use GuzzleHttp\Client;
use RuntimeException;
use SilverStripe\Core\Environment;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Imports the SEC's list of exchange-listed companies into Stock records.
 *
 * Creates new stocks, updates names/exchanges, and marks stocks that are no
 * longer listed. Run daily; safe to run repeatedly.
 *
 * CLI: vendor/bin/sake tasks:import-sec-company-list
 */
class ImportSecCompanyListTask extends BuildTask
{
    protected static string $commandName = 'import-sec-company-list';

    protected string $title = 'Import SEC company list';

    protected static string $description = 'Creates or updates stocks from the SEC list of exchange-listed companies.';

    private const string SOURCE_URL = 'https://www.sec.gov/files/company_tickers_exchange.json';

    /**
     * Exchanges in scope, as named in the SEC file (NYSE includes NYSE American).
     *
     * Keyed by name rather than a plain list: YAML config merges lists by appending,
     * so a list entry could never be removed. With keys, YAML can set `NYSE: false`.
     */
    private static array $exchanges = [
        'Nasdaq' => true,
        'NYSE' => true,
    ];

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $rows = $this->fetchRows();
        $output->writeln(sprintf('Fetched %d companies from the SEC', count($rows)));

        $exchanges = array_keys(array_filter(static::config()->get('exchanges')));
        $listed = array_filter($rows, fn(array $row) => in_array($row['exchange'], $exchanges, true));
        $output->writeln(sprintf('%d are listed on %s', count($listed), implode(' or ', $exchanges)));

        // One query to load everything we already have, instead of one query per row
        $existing = [];
        foreach (Stock::get() as $stock) {
            $existing[$stock->Ticker] = $stock;
        }

        $created = 0;
        $updated = 0;
        $delisted = 0;
        $now = DBDatetime::now()->getValue();

        // A single transaction makes thousands of writes much faster (especially on SQLite)
        DB::get_conn()->withTransaction(function () use ($listed, $existing, &$created, &$updated, &$delisted, $now) {
            foreach ($listed as $row) {
                $stock = $existing[$row['ticker']] ?? null;
                if (!$stock) {
                    $stock = Stock::create(['Ticker' => $row['ticker']]);
                    $created++;
                } else {
                    $updated++;
                }
                $stock->update([
                    'Name' => $row['name'],
                    'CIK' => $row['cik'],
                    'Exchange' => $row['exchange'],
                    'IsListed' => true,
                    'LastImportedAt' => $now,
                ]);
                $stock->write();
                unset($existing[$row['ticker']]);
            }

            // Anything left was not in today's list
            foreach ($existing as $stock) {
                if ($stock->IsListed) {
                    $stock->IsListed = false;
                    $stock->InScope = false;
                    $stock->write();
                    $delisted++;
                }
            }
        });

        $output->writeln(sprintf(
            'Created %d, updated %d, marked %d as no longer listed',
            $created,
            $updated,
            $delisted
        ));

        return Command::SUCCESS;
    }

    /**
     * @return array<array{cik: int, name: string, ticker: string, exchange: ?string}>
     */
    private function fetchRows(): array
    {
        $userAgent = Environment::getEnv('SEC_USER_AGENT');
        if (!$userAgent) {
            throw new RuntimeException('Set SEC_USER_AGENT in .env (see .env.example)');
        }

        $client = new Client(['timeout' => 30]);
        $response = $client->get(self::SOURCE_URL, ['headers' => ['User-Agent' => $userAgent]]);
        $json = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);

        // The file is {"fields": ["cik","name","ticker","exchange"], "data": [[...], ...]}
        return array_map(fn(array $row) => array_combine($json['fields'], $row), $json['data']);
    }
}
