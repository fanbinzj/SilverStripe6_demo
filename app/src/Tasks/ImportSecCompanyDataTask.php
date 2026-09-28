<?php

namespace App\Tasks;

use App\MarketData\SecCompanyImporter;
use App\Model\Stock;
use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Throwable;

/**
 * vendor/bin/sake tasks:import-sec-company-data [--ticker=ABEO] [--limit=20]
 *
 * Without --ticker, imports in-scope stocks, least recently imported first.
 * A full run takes around 15 minutes (two SEC requests per stock, rate limited);
 * in production this runs as a queued job instead.
 */
class ImportSecCompanyDataTask extends BuildTask
{
    protected static string $commandName = 'import-sec-company-data';

    protected string $title = 'Import SEC company data';

    protected static string $description = 'Imports background, former names, offering filings and XBRL figures from SEC EDGAR.';

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $ticker = $input->getOption('ticker');
        $stocks = $ticker
            ? Stock::get()->filter('Ticker', strtoupper($ticker))
            : Stock::get()->filter('InScope', true)->sort('SecDataImportedAt', 'ASC');

        $limit = (int) $input->getOption('limit');
        if ($limit > 0) {
            $stocks = $stocks->limit($limit);
        }
        if (!$stocks->exists()) {
            $output->writeln('No matching stocks. Run tasks:update-quotes first to mark stocks in scope.');
            return Command::FAILURE;
        }

        $importer = SecCompanyImporter::singleton();
        $failed = 0;
        foreach ($stocks as $stock) {
            try {
                $importer->importStock($stock);
                $output->writeln("{$stock->Ticker}: {$stock->Filings()->count()} filings, cash as of " . ($stock->CashAsOf ?: 'unknown'));
            } catch (Throwable $e) {
                // One bad company shouldn't stop the whole run
                $failed++;
                $output->writeln("<error>{$stock->Ticker}: {$e->getMessage()}</>");
            }
        }

        return $failed ? Command::FAILURE : Command::SUCCESS;
    }

    public function getOptions(): array
    {
        return [
            new InputOption('ticker', null, InputOption::VALUE_REQUIRED, 'Import a single stock'),
            new InputOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum number of stocks to import', 0),
        ];
    }
}
