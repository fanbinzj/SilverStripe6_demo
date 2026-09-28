<?php

namespace App\Tasks;

use App\MarketData\QuoteImporter;
use App\Model\MarketSession;
use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * vendor/bin/sake tasks:update-quotes [--session=market-hours]
 */
class UpdateQuotesTask extends BuildTask
{
    protected static string $commandName = 'update-quotes';

    protected string $title = 'Update quotes and movers';

    protected static string $description = 'Fetches prices, updates market caps and which stocks are in scope, and rebuilds the movers list.';

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $session = MarketSession::tryFrom((string) $input->getOption('session'));
        if (!$session) {
            $output->writeln('<error>--session must be one of: ' . implode(', ', array_column(MarketSession::cases(), 'value')) . '</>');
            return Command::INVALID;
        }

        $importer = QuoteImporter::singleton();
        $stats = $importer->import($session);
        if ($stats === null) {
            $output->writeln("{$importer->getProvider()->getSourceName()} has no {$session->label()} data; nothing to do.");
            return Command::SUCCESS;
        }

        $output->writeln(sprintf(
            '%d quotes, %d stocks updated, %d in scope, %d movers',
            $stats['quotes'],
            $stats['updated'],
            $stats['inScope'],
            $stats['movers']
        ));
        return Command::SUCCESS;
    }

    public function getOptions(): array
    {
        return [
            new InputOption('session', null, InputOption::VALUE_REQUIRED, 'Trading session', MarketSession::Regular->value),
        ];
    }
}
