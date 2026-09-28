<?php

namespace App\Jobs;

use App\MarketData\EarningsImporter;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJob;

/**
 * Daily: refresh upcoming earnings dates.
 */
class ImportEarningsCalendarJob extends AbstractQueuedJob
{
    use SchedulesNextRun;

    public function getTitle()
    {
        return 'Import earnings calendar';
    }

    public function getJobType()
    {
        return QueuedJob::QUEUED;
    }

    public function process()
    {
        $count = EarningsImporter::singleton()->import();
        $this->addMessage("Imported {$count} earnings dates");
        $this->isComplete = true;
    }

    protected function nextRunAt(): string
    {
        return 'tomorrow 03:00';
    }
}
