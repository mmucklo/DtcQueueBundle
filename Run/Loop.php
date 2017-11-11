<?php

namespace Dtc\QueueBundle\Run;

use Dtc\QueueBundle\Doctrine\BaseJobManager;
use Dtc\QueueBundle\Doctrine\BaseRunManager;
use Dtc\QueueBundle\Model\BaseJob;
use Dtc\QueueBundle\Model\Job;
use Dtc\QueueBundle\Model\JobManagerInterface;
use Dtc\QueueBundle\Model\Run;
use Dtc\QueueBundle\Model\RunManager;
use Dtc\QueueBundle\Model\WorkerManager;
use Dtc\QueueBundle\Util\Util;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Loop
{
    /** @var Run $run */
    protected $run;

    /** @var OutputInterface */
    protected $output;

    /** @var LoggerInterface */
    protected $logger;

    /** @var WorkerManager */
    protected $workerManager;

    /** @var JobManagerInterface */
    protected $jobManager;

    /** @var RunManager */
    protected $runManager;

    /** @var int */
    protected $processTimeout;

    public function __construct(
        WorkerManager $workerManager,
        JobManagerInterface $jobManager,
        RunManager $runManager)
    {
        $this->workerManager = $workerManager;
        $this->jobManager = $jobManager;
        $this->runManager = $runManager;
    }

    /**
     * @return int
     */
    public function getProcessTimeout()
    {
        return $this->processTimeout;
    }

    /**
     * @param int $processTimeout
     */
    public function setProcessTimeout(int $processTimeout)
    {
        $this->processTimeout = $processTimeout;
    }

    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function setOutput(OutputInterface $output)
    {
        $this->output = $output;
    }

    /**
     * The current (last) run object.
     *
     * @return Run|null
     */
    public function getRun()
    {
        return $this->run;
    }

    /**
     * @param float $start
     */
    public function runJobById($start, $jobId)
    {
        $this->runStart($start);

        if (!$this->jobManager instanceof BaseJobManager) {
            throw new \Exception("Can't get job by id when not using a database/datastore backed queue (such as mongodb or an RDBMS)");
        }

        /** @var Job $job */
        $job = $this->jobManager->getRepository()->find($jobId);
        if (!$job) {
            $this->log('error', "Job id is not found: {$jobId}");
            $this->runStop($start);

            return;
        }

        $job = $this->workerManager->runJob($job);
        $this->reportJob($job);
        $this->run->setProcessed(1);
        $this->runStop($start);

        return;
    }

    /**
     * @param float    $start
     * @param int      $nanoSleep
     * @param null|int $maxCount
     * @param null|int $duration
     */
    public function runLoop($start, $workerName, $methodName, $maxCount, $duration = null, $nanoSleep = 500000000)
    {
        $this->checkParameters($nanoSleep, $maxCount, $duration);
        $this->workerManager->setLoggingFunc([$this, 'log']);
        $this->runStart($start, $maxCount, $duration);
        try {
            $this->log('info', 'Staring up a new job...');

            $endTime = $this->getEndTime($duration);
            $currentJob = 1;
            $noMoreJobsToRun = false;
            do {
                $job = $this->workerManager->run($workerName, $methodName, true, $this->run->getId());
                $this->recordHeartbeat($start, $job);
                $this->runCurrentJob($job, $noMoreJobsToRun, $currentJob, $duration, $nanoSleep);
            } while (!$this->isFinished($maxCount, $endTime, $currentJob, $noMoreJobsToRun));
        } catch (\Exception $e) {
            // Uncaught error: possibly with QueueBundle itself
            $this->log('critical', $e->getMessage(), $e->getTrace());
        }
        $this->runStop($start);

        return 0;
    }

    /**
     * @param int      $nanoSleep
     * @param null|int $maxCount
     * @param null|int $duration
     *
     * @throws \Exception
     */
    private function checkParameters(&$nanoSleep, &$maxCount, &$duration)
    {
        $maxCount = Util::validateIntNull('maxCount', $maxCount, 32);
        $duration = Util::validateIntNull('duration', $duration, 32);
        $nanoSleep = Util::validateIntNull('nanoSleep', $nanoSleep, 63);

        $this->validateNanoSleep($nanoSleep);
        $this->validateMaxCountDuration($maxCount, $duration);
    }

    /**
     * @param int|null $maxCount
     * @param int|null $duration
     *
     * @throws \Exception
     */
    protected function validateMaxCountDuration($maxCount, $duration)
    {
        if (0 === $maxCount && 0 === $duration) {
            throw new \Exception('maxCount and duration can not both be 0');
        }
        if (null === $maxCount && null === $duration) {
            throw new \Exception('maxCount and duration can not both be null');
        }
    }

    /**
     * @param int|null $nanoSleep
     *
     * @throws \Exception
     */
    protected function validateNanoSleep($nanoSleep)
    {
        if (null === $nanoSleep) {
            throw new \Exception("nanoSleep can't be null");
        }
    }

    /**
     * @param int|null $duration
     *
     * @return null|\DateTime
     */
    protected function getEndTime($duration)
    {
        $endTime = null;
        if (null !== $duration) {
            $interval = new \DateInterval("PT${duration}S");
            $endTime = clone $this->run->getStartedAt();
            $endTime->add($interval);
        }

        return $endTime;
    }

    /**
     * @param Job|null $job
     * @param bool     $noMoreJobsToRun
     * @param int      $currentJob
     * @param int|null $duration
     * @param int      $nanoSleep
     */
    protected function runCurrentJob($job, &$noMoreJobsToRun, &$currentJob, $duration, $nanoSleep)
    {
        if (null !== $job) {
            $noMoreJobsToRun = false;
            $this->reportJob($job);
            $this->updateProcessed($currentJob);
            ++$currentJob;
        } else {
            if (!$noMoreJobsToRun) {
                $this->log('info', 'No more jobs to run ('.($currentJob - 1).' processed so far).');
                $noMoreJobsToRun = true;
            }
            if (null !== $duration) {
                $nanoSleepTime = function_exists('random_int') ? random_int(0, $nanoSleep) : mt_rand(0, $nanoSleep);
                time_nanosleep(0, $nanoSleepTime);
            }
        }
    }

    /**
     * @param $maxCount
     * @param $duration
     * @param $processTimeout
     */
    public function checkMaxCountDuration(&$maxCount, &$duration, &$processTimeout)
    {
        if (null !== $duration && null !== $processTimeout && $duration >= $processTimeout) {
            $this->log('info', "duration ($duration) >= to process timeout ($processTimeout), so doubling process timeout to: ".(2 * $processTimeout));
            $processTimeout *= 2;
        }

        if (null === $maxCount && null === $duration) {
            $maxCount = 1;
        }
    }

    /**
     * Determine if the run loop is finished.
     *
     * @param int|null       $maxCount
     * @param int            $currentJob
     * @param \DateTime|null $endTime
     * @param bool           $noMoreJobsToRun
     *
     * @return bool
     */
    protected function isFinished($maxCount, $endTime, $currentJob, $noMoreJobsToRun)
    {
        if (null === $maxCount) {
            return $this->isFinishedEndTime($endTime);
        }
        if ($currentJob <= $maxCount) {
            return $this->isFinishedJobs($endTime, $noMoreJobsToRun);
        }

        return true;
    }

    /**
     * @param \DateTime|null $endTime
     * @param bool           $noMoreJobsToRun
     *
     * @return bool
     */
    protected function isFinishedJobs($endTime, $noMoreJobsToRun)
    {
        if (null === $endTime) { // This means that there is a $maxCount as we force one or the other to be not null
            if ($noMoreJobsToRun) {
                return true;
            }

            return false;
        }

        return $this->isFinishedEndTime($endTime);
    }

    /**
     * @param \DateTime $endTime
     *
     * @return bool
     */
    protected function isFinishedEndTime(\DateTime $endTime)
    {
        $now = new \DateTime();
        if ($endTime > $now) {
            return false;
        }

        return true;
    }

    /**
     * @param float    $start
     * @param Job|null $jobId
     */
    protected function recordHeartbeat($start, Job $job)
    {
        $jobId = null;
        if (null !== $job) {
            $jobId = $job->getId();
        }

        $this->run->setLastHeartbeatAt(new \DateTime());
        $this->run->setCurrentJobId($jobId);
        $this->run->setElapsed(microtime(true) - $start);
        $this->persistRun();
    }

    protected function persistRun($action = 'persist')
    {
        if ($this->runManager instanceof BaseRunManager) {
            $objectManager = $this->runManager->getObjectManager();
            $objectManager->$action($this->run);
            $objectManager->flush();
        }
    }

    /**
     * @param int $count
     */
    protected function updateProcessed($count)
    {
        $this->run->setProcessed($count);
        $this->persistRun();
    }

    /**
     * Sets up the runManager (document / entity persister) if appropriate.
     *
     * @param float    $start
     * @param int|null $maxCount
     * @param int|null $duration
     */
    protected function runStart($start, $maxCount = null, $duration = null)
    {
        $runClass = $this->runManager->getRunClass();
        $this->run = new $runClass();
        $startDate = \DateTime::createFromFormat('U.u', $start);
        $this->run->setLastHeartbeatAt($startDate);
        $this->run->setStartedAt($startDate);
        if (null !== $maxCount) {
            $this->run->setMaxCount($maxCount);
        }
        if (null !== $duration) {
            $this->run->setDuration($duration);
        }
        $this->run->setHostname(gethostname());
        $this->run->setPid(getmypid());
        $this->run->setProcessed(0);
        $this->run->setProcessTimeout($this->processTimeout);
        $this->persistRun();
    }

    /**
     * @param int|null $start
     */
    protected function runStop($start)
    {
        $end = microtime(true);
        $endedTime = \DateTime::createFromFormat('U.u', $end);
        if ($endedTime) {
            $this->run->setEndedAt($endedTime);
        }
        $this->run->setElapsed($end - $start);
        $this->persistRun('remove');
        $this->log('info', 'Ended with '.$this->run->getProcessed().' job(s) processed over '.strval($this->run->getElapsed()).' seconds.');
    }

    /**
     * @param Job $job
     */
    protected function reportJob(Job $job)
    {
        $this->runManager->recordJobRun($job);

        if (BaseJob::STATUS_ERROR == $job->getStatus()) {
            $message = "Error with job id: {$job->getId()}\n".$job->getMessage();
            $this->log('error', $message);
        }

        $message = "Finished job id: {$job->getId()} in {$job->getElapsed()} seconds\n";
        $this->log('info', $message);
    }

    /**
     * @param string $level
     */
    public function log($level, $msg, array $context = [])
    {
        if ($this->logger) {
            $this->logger->$level($msg, $context);

            return;
        }

        if ($this->output) {
            $date = new \DateTime();
            $this->output->write("[$level] [".$date->format('c').'] '.$msg);
            if (!empty($context)) {
                $this->output->write(print_r($context, true));
            }
            $this->output->writeln('');
        }
    }
}
