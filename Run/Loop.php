<?php

namespace Dtc\QueueBundle\Run;

use Dtc\QueueBundle\Doctrine\DoctrineJobManager;
use Dtc\QueueBundle\Model\BaseJob;
use Dtc\QueueBundle\Model\Job;
use Dtc\QueueBundle\Manager\JobManagerInterface;
use Dtc\QueueBundle\Model\Run;
use Dtc\QueueBundle\Manager\RunManager;
use Dtc\QueueBundle\Manager\WorkerManager;
use Dtc\QueueBundle\Util\Util;
use Dtc\QueueBundle\Exception\ClassNotSubclassException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Loop
{
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

    /** @var Run */
    protected $lastRun;

    public function __construct(
        WorkerManager $workerManager,
        JobManagerInterface $jobManager,
        RunManager $runManager
    ) {
        $this->workerManager = $workerManager;
        $this->jobManager = $jobManager;
        $this->runManager = $runManager;
    }

    /**
     * @return Run|null
     */
    public function getLastRun()
    {
        return $this->lastRun;
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
    public function setProcessTimeout($processTimeout)
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
     * @param float $start
     */
    public function runJobById($start, $jobId)
    {
        $run = $this->runManager->runStart($start, null, null, $this->processTimeout);
        $this->lastRun = $run;

        if (!$this->jobManager instanceof DoctrineJobManager) {
            throw new ClassNotSubclassException("Can't get job by id when not using a database/datastore backed queue (such as mongodb or an RDBMS)");
        }

        /** @var Job $job */
        $job = $this->jobManager->getRepository()->find($jobId);
        if (!$job) {
            $this->log('error', "Job id is not found: {$jobId}");
            $this->runManager->runStop($run, $start);

            return;
        }

        $job = $this->workerManager->runJob($job);
        $this->reportJob($job);
        $run->setProcessed(1);
        $this->runManager->runStop($run, $start);
        $this->log('info', 'Ended with 1 job processed over '.strval($run->getElapsed()).' seconds.');

        return;
    }

    /**
     * @param float    $start
     * @param int      $nanoSleep
     * @param int|null $maxCount
     * @param int|null $duration
     */
    public function runLoop($start, $workerName, $methodName, $maxCount, $duration = null, $nanoSleep = 500000000)
    {
        $this->checkParameters($nanoSleep, $maxCount, $duration);
        $this->workerManager->setLoggingFunc([$this, 'log']);
        $run = $this->runManager->runStart($start, $maxCount, $duration, $this->processTimeout);
        $this->lastRun = $run;
        try {
            $this->log('info', 'Staring up a new job...');

            $endTime = $this->getEndTime($run, $duration);
            $currentJob = 1;
            $noMoreJobsToRun = false;
            do {
                $job = $this->workerManager->run($workerName, $methodName, true, $run->getId());
                $this->runManager->recordHeartbeat($run, $start, $job);
                $this->runCurrentJob($run, $job, $noMoreJobsToRun, $currentJob, $duration, $nanoSleep);
            } while (!$this->isFinished($maxCount, $duration, $endTime, $currentJob, $noMoreJobsToRun));
        } catch (\Exception $e) {
            // Uncaught error: possibly with QueueBundle itself
            $this->log('critical', $e->getMessage(), ["trace" => $e->getTraceAsString()]);
        }
        $this->runManager->runStop($run, $start);
        $this->log('info', 'Ended with '.$run->getProcessed().' job(s) processed over '.strval($run->getElapsed()).' seconds.');

        return 0;
    }

    /**
     * @param int      $nanoSleep
     * @param int|null $maxCount
     * @param int|null $duration
     *
     * @throws \InvalidArgumentException
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
     * @throws \InvalidArgumentException
     */
    protected function validateMaxCountDuration($maxCount, $duration)
    {
        if (0 === $maxCount && 0 === $duration) {
            throw new \InvalidArgumentException('maxCount and duration can not both be 0');
        }
        if (null === $maxCount && null === $duration) {
            throw new \InvalidArgumentException('maxCount and duration can not both be null');
        }
    }

    /**
     * @param int|null $nanoSleep
     *
     * @throws \InvalidArgumentException
     */
    protected function validateNanoSleep($nanoSleep)
    {
        if (null === $nanoSleep) {
            throw new \InvalidArgumentException("nanoSleep can't be null");
        }
    }

    /**
     * @param int|null $duration
     *
     * @return \DateTime|null
     */
    protected function getEndTime(Run $run, $duration)
    {
        $endTime = null;
        if (null !== $duration) {
            $interval = new \DateInterval("PT${duration}S");
            $endTime = clone $run->getStartedAt();
            $endTime->add($interval);
        }
        return $endTime;
    }

    /**
     * @param Run      $run
     * @param Job|null $job
     * @param bool     $noMoreJobsToRun
     * @param int      $currentJob
     * @param int|null $duration
     * @param int      $nanoSleep
     */
    protected function runCurrentJob($run, $job, &$noMoreJobsToRun, &$currentJob, $duration, $nanoSleep)
    {
        if (null !== $job) {
            $noMoreJobsToRun = false;
            $this->reportJob($job);
            $this->runManager->updateProcessed($run, $currentJob);
            ++$currentJob;
        } else {
            if (!$noMoreJobsToRun) {
                $this->log('info', 'No more jobs to run ('.($currentJob - 1).' processed so far).');
                $noMoreJobsToRun = true;
            }
            if (null !== $duration) {
                if ($nanoSleep > 0) {
                    $nanoSleepTime = function_exists('random_int') ? random_int(0, $nanoSleep) : mt_rand(0, $nanoSleep);
                    time_nanosleep(0, $nanoSleepTime);
                }
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
    protected function isFinished($maxCount, $duration, $endTime, $currentJob, $noMoreJobsToRun)
    {
        if (null === $maxCount) {
            return $this->isFinishedEndTime($duration, $endTime);
        }
        if ($currentJob <= $maxCount) {
            return $this->isFinishedJobs($duration, $endTime, $noMoreJobsToRun);
        }

        return true;
    }

    /**
     * @param \DateTime|null $endTime
     * @param bool           $noMoreJobsToRun
     *
     * @return bool
     */
    protected function isFinishedJobs($duration, $endTime, $noMoreJobsToRun)
    {
        if (null === $endTime) { // This means that there is a $maxCount as we force one or the other to be not null
            if ($noMoreJobsToRun) {
                return true;
            }

            return false;
        }

        return $this->isFinishedEndTime($duration, $endTime);
    }

    /**
     * @param \DateTime $endTime
     *
     * @return bool
     */
    protected function isFinishedEndTime($duration, \DateTime $endTime)
    {
        if (0 === $duration) {
            return false;
        }
        $now = Util::getMicrotimeDateTime();
        if ($endTime > $now) {
            return false;
        }

        return true;
    }

    /**
     * @param Job $job
     */
    protected function reportJob(Job $job)
    {
        if (BaseJob::STATUS_EXCEPTION == $job->getStatus()) {
            $message = "Exception with job id: {$job->getId()}\n".$job->getMessage();
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
            $date = \Dtc\QueueBundle\Util\Util::getMicrotimeDateTime();
            $this->output->write("[$level] [".$date->format('c').'] '.$msg);
            if (!empty($context)) {
                $this->output->write(print_r($context, true));
            }
            $this->output->writeln('');
        }
    }
}
