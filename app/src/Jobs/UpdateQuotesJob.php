<?php

namespace App\Jobs;

use App\MarketData\MarketClock;
use App\MarketData\QuoteImporter;
use App\Model\MarketSession;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJob;

/**
 * Every 15 minutes: refresh quotes and movers for whichever session is trading.
 */
class UpdateQuotesJob extends AbstractQueuedJob
{
    use SchedulesNextRun;

    public function getTitle()
    {
        return 'Update quotes and movers';
    }

    public function getJobType()
    {
        return QueuedJob::QUEUED;
    }

    public function process()
    {
        // Run once more shortly after the close so the day's final prices are captured
        $session = MarketClock::currentSession();
        if ($session === MarketSession::AfterHours && MarketClock::now()->format('H:i') < '16:30') {
            $session = MarketSession::Regular;
        }

        if ($session === null) {
            $this->addMessage('Market closed; nothing to update');
        } else {
            $stats = QuoteImporter::singleton()->import($session);
            $this->addMessage($stats === null
                ? "No {$session->label()} data source configured"
                : "{$session->label()}: {$stats['updated']} stocks updated, {$stats['movers']} movers");
        }

        $this->isComplete = true;
    }

    protected function nextRunAt(): string
    {
        return '+15 minutes';
    }
}
