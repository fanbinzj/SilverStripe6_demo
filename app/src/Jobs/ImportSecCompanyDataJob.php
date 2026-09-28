<?php

namespace App\Jobs;

use App\MarketData\SecCompanyImporter;
use App\Model\Stock;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJob;
use SilverStripe\Core\Config\Configurable;
use Throwable;

/**
 * Nightly: import SEC data for every in-scope stock.
 *
 * Runs as many small steps (a batch of stocks each) instead of one long loop.
 * The job saves its progress between steps, so if the worker stops part way
 * through, it resumes from the next batch instead of starting again.
 *
 * Batches rather than one stock per step: queuedjobs 6.2.2 adds a log handler
 * on every step and never removes it (the "already added" check looks for the
 * inner handler, but the outer BufferHandler is what gets added), so memory
 * grows with the number of steps. See also QueuedJobService.memory_limit in
 * app/_config/queuedjobs.yml.
 */
class ImportSecCompanyDataJob extends AbstractQueuedJob
{
    use Configurable;
    use SchedulesNextRun;

    private static int $stocks_per_step = 25;

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
        $ids = Stock::get()->filter('InScope', true)->sort('SecDataImportedAt', 'ASC')->column('ID');
        $this->batches = array_chunk($ids, static::config()->get('stocks_per_step'));
        $this->stockCount = count($ids);
        $this->totalSteps = count($this->batches);
        $this->failed = 0;
    }

    public function process()
    {
        $batches = $this->batches;
        $importer = SecCompanyImporter::singleton();

        foreach (Stock::get()->byIDs($batches[$this->currentStep] ?? []) as $stock) {
            try {
                $importer->importStock($stock);
            } catch (Throwable $e) {
                // One bad company shouldn't stop the run; it is retried on the next run
                $this->failed++;
                $this->addMessage("{$stock->Ticker}: {$e->getMessage()}", 'WARNING');
            }
        }

        $this->currentStep++;
        if ($this->currentStep >= $this->totalSteps) {
            $this->addMessage("Imported {$this->stockCount} stocks, {$this->failed} failed");
            $this->isComplete = true;
        }
    }

    protected function nextRunAt(): string
    {
        return 'tomorrow 02:00';
    }
}
