<?php

namespace App\Jobs;

use App\MarketData\SecCompanyImporter;
use App\Model\Stock;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJob;
use Throwable;

/**
 * Nightly: import SEC data for every in-scope stock.
 *
 * Runs as many small steps (one stock each) instead of one long loop. The job
 * saves its progress between steps, so if the worker stops part way through,
 * it resumes from the next stock instead of starting again.
 */
class ImportSecCompanyDataJob extends AbstractQueuedJob
{
    use SchedulesNextRun;

    public function getTitle()
    {
        return 'Import SEC company data';
    }

    public function getJobType()
    {
        // The "large" queue is for long-running jobs, so they don't hold up quick ones
        return QueuedJob::LARGE;
    }

    public function setup()
    {
        parent::setup();
        $this->stockIDs = Stock::get()->filter('InScope', true)->sort('SecDataImportedAt', 'ASC')->column('ID');
        $this->totalSteps = count($this->stockIDs);
        $this->failed = 0;
    }

    public function process()
    {
        $ids = $this->stockIDs;
        $stock = Stock::get()->byID($ids[$this->currentStep] ?? 0);

        if ($stock) {
            try {
                SecCompanyImporter::singleton()->importStock($stock);
            } catch (Throwable $e) {
                $this->failed++;
                $this->addMessage("{$stock->Ticker}: {$e->getMessage()}", 'WARNING');
            }
        }

        $this->currentStep++;
        if ($this->currentStep >= $this->totalSteps) {
            $this->addMessage("Imported {$this->totalSteps} stocks, {$this->failed} failed");
            $this->isComplete = true;
        }
    }

    protected function nextRunAt(): string
    {
        return 'tomorrow 02:00';
    }
}
