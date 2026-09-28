<?php

namespace App\Tasks;

use App\MarketData\EarningsImporter;
use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * vendor/bin/sake tasks:import-earnings-calendar [--days=21]
 */
class ImportEarningsCalendarTask extends BuildTask
{
    protected static string $commandName = 'import-earnings-calendar';

    protected string $title = 'Import earnings calendar';

    protected static string $description = 'Imports upcoming earnings dates for in-scope stocks into the catalyst calendar.';

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $days = $input->getOption('days') ? (int) $input->getOption('days') : null;
        $count = EarningsImporter::singleton()->import($days);
        $output->writeln("Imported {$count} earnings dates");
        return Command::SUCCESS;
    }

    public function getOptions(): array
    {
        return [
            new InputOption('days', null, InputOption::VALUE_REQUIRED, 'Days ahead to import'),
        ];
    }
}
