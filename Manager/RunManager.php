<?php

namespace Dtc\QueueBundle\Manager;

use Dtc\QueueBundle\Exception\UnsupportedException;
use Dtc\QueueBundle\Model\Job;
use Dtc\QueueBundle\Model\Run;

class RunManager
{
    /** @var string */
    protected $runClass;

    public function __construct($runClass)
    {
        $this->runClass = $runClass;
    }

    /**
     * @return string
     */
    public function getRunClass()
    {
        return $this->runClass;
    }

    /**
     * @param string $runClass
     */
    public function setRunClass($runClass)
    {
        $this->runClass = $runClass;
    }

    /**
     * @param \DateTime $olderThan
     *
     * @return int Number of archived runs pruned
     */
    public function pruneArchivedRuns(\DateTime $olderThan)
    {
        throw new UnsupportedException('not supported');
    }

    public function pruneStalledRuns()
    {
        throw new UnsupportedException('not supported');
    }

    /**
     * @param float    $start
     * @param Job|null $job
     */
    public function recordHeartbeat(Run $run, $start, Job $job = null)
    {
        $jobId = null;
        if (null !== $job) {
            $jobId = $job->getId();
        }

        $run->setLastHeartbeatAt(new \DateTime());
        $run->setCurrentJobId($jobId);
        $run->setElapsed(microtime(true) - $start);
        $this->persistRun($run);
    }

    protected function persistRun(Run $run, $action = 'persist')
    {
        // To be overridden
    }

    /**
     * @param int $count
     */
    public function updateProcessed(Run $run, $count)
    {
        $run->setProcessed($count);
        $this->persistRun($run);
    }

    /**
     * Sets up the runManager (document / entity persister) if appropriate.
     *
     * @param float    $start
     * @param int|null $maxCount
     * @param int|null $duration
     * @param int      $processTimeout
     *
     * @return Run
     */
    public function runStart($start, $maxCount = null, $duration = null, $processTimeout = null)
    {
        $runClass = $this->getRunClass();
        /** @var Run $run */
        $run = new $runClass();
        $startDate = \DateTime::createFromFormat('U.u', $formattedStart = number_format($start, 6, '.', ''));
        if (false === $startDate) {
            throw new \RuntimeException("Could not create date from $start formatted as $formattedStart");
        }
        $run->setLastHeartbeatAt($startDate);
        $run->setStartedAt($startDate);
        if (null !== $maxCount) {
            $run->setMaxCount($maxCount);
        }
        if (null !== $duration) {
            $run->setDuration($duration);
        }
        $run->setHostname(gethostname());
        $run->setPid(getmypid());
        $run->setProcessed(0);
        $run->setProcessTimeout($processTimeout);
        $this->persistRun($run);

        return $run;
    }

    /**
     * @param Run      $run
     * @param int|null $start
     */
    public function runStop(Run $run, $start)
    {
        $end = microtime(true);
        $endedTime = \DateTime::createFromFormat('U.u', $end);
        if ($endedTime) {
            $run->setEndedAt($endedTime);
        }
        $run->setElapsed($end - $start);
        $this->persistRun($run, 'remove');
    }
}
