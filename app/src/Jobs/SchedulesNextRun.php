<?php

namespace App\Jobs;

use Symbiote\QueuedJobs\Services\QueuedJobService;

/**
 * For jobs that repeat: when a run finishes, queue the next one.
 * If a job goes missing anyway (e.g. it crashed), QueuedJobService.defaultJobs
 * in app/_config/queuedjobs.yml recreates it.
 */
trait SchedulesNextRun
{
    /**
     * strtotime() expression for the next run, e.g. '+15 minutes' or 'tomorrow 02:00'
     */
    abstract protected function nextRunAt(): string;

    public function afterComplete()
    {
        parent::afterComplete();
        QueuedJobService::singleton()->queueJob(new static(), date('Y-m-d H:i:s', strtotime($this->nextRunAt())));
    }
}
