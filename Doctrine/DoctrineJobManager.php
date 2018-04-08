<?php

namespace Dtc\QueueBundle\Doctrine;

use Dtc\QueueBundle\Model\BaseJob;
use Dtc\QueueBundle\Model\RetryableJob;
use Dtc\QueueBundle\Model\Job;
use Dtc\QueueBundle\Model\JobTiming;
use Dtc\QueueBundle\Model\StallableJob;

abstract class DoctrineJobManager extends BaseDoctrineJobManager
{
    use ProgressCallbackTrait;
    use StalledTrait;

    /** Number of seconds before a job is considered stalled if the runner is no longer active */
    const STALLED_SECONDS = 1800;
    const TYPE_WAITING = 'waiting';
    const TYPE_NON_RUNNING = 'non_running';

    /**
     * @param string $objectName
     */
    abstract protected function countJobsByStatus($objectName, $status, $workerName = null, $method = null);

    public function resetExceptionJobs($workerName = null, $method = null)
    {
        $count = $this->countJobsByStatus($this->getJobArchiveClass(), Job::STATUS_EXCEPTION, $workerName, $method);

        $criterion = ['status' => Job::STATUS_EXCEPTION];
        $this->addWorkerNameMethod($criterion, $workerName, $method);
        $saveCount = $this->getSaveCount($count);
        $countProcessed = 0;
        for ($i = 0; $i < $count; $i += $saveCount) {
            $countProcessed += $this->resetJobsByCriterion(
                $criterion,
                $saveCount,
                $i
            );
        }

        return $countProcessed;
    }

    /**
     * Sets the status to Job::STATUS_EXPIRED for those jobs that are expired.
     *
     * @param string|null $workerName
     * @param string|null $method
     *
     * @return mixed
     */
    abstract protected function updateExpired($workerName = null, $method = null);

    public function pruneExpiredJobs($workerName = null, $method = null)
    {
        $count = $this->updateExpired($workerName, $method);
        $criterion = ['status' => Job::STATUS_EXPIRED];
        $this->addWorkerNameMethod($criterion, $workerName, $method);
        $objectManager = $this->getObjectManager();
        $repository = $this->getRepository();
        $finalCount = 0;

        $metadata = $this->getObjectManager()->getClassMetadata($this->getJobClass());
        $identifierData = $metadata->getIdentifier();
        $idColumn = isset($identifierData[0]) ? $identifierData[0] : 'id';

        $fetchCount = $this->getFetchCount($count);
        for ($i = 0; $i < $count; $i += $fetchCount) {
            $expiredJobs = $repository->findBy($criterion, [$idColumn => 'ASC'], $fetchCount, $i);
            $innerCount = 0;
            if (!empty($expiredJobs)) {
                foreach ($expiredJobs as $expiredJob) {
                    /* @var Job $expiredJob */
                    $expiredJob->setStatus(Job::STATUS_EXPIRED);
                    $objectManager->remove($expiredJob);
                    ++$finalCount;
                    ++$innerCount;
                }
            }
            $this->flush();
            for ($j = 0; $j < $innerCount; ++$j) {
                $this->jobTiminigManager->recordTiming(JobTiming::STATUS_FINISHED_EXPIRED);
            }
        }

        return $finalCount;
    }

    public function resetStalledJobs($workerName = null, $method = null, callable $progressCallback = null)
    {
        $stalledJobs = $this->getStalledJobs($workerName, $method);
        $stalledJobsCount = count($stalledJobs);
        $this->updateProgress($progressCallback, 0, $stalledJobsCount);
        $countReset = 0;
        $saveCount = $this->getSaveCount($stalledJobsCount);
        for ($i = 0; $i < $stalledJobsCount; $i += $saveCount) {
            $resetCount = $this->runStalledLoop($i, $stalledJobsCount, $saveCount, $stalledJobs);
            for ($j = $i, $max = $i + $saveCount; $j < $max && $j < $stalledJobsCount; ++$j) {
                $this->jobTiminigManager->recordTiming(JobTiming::STATUS_FINISHED_STALLED);
            }
            $countReset += $resetCount;
            $this->flush();
            $this->updateProgress($progressCallback, $countReset, $stalledJobsCount);
        }

        return $countReset;
    }

    /**
     * @param string|null   $workerName
     * @param string|null   $method
     * @param callable|null $progressCallback
     */
    public function pruneStalledJobs($workerName = null, $method = null, callable $progressCallback = null)
    {
        $stalledJobs = $this->getStalledJobs($workerName, $method);
        $stalledJobsCount = count($stalledJobs);
        $this->updateProgress($progressCallback, 0, $stalledJobsCount);
        $countProcessed = 0;
        $saveCount = $this->getSaveCount($stalledJobsCount);
        for ($i = 0; $i < $stalledJobsCount; $i += $saveCount) {
            for ($j = $i, $max = $i + $saveCount; $j < $max && $j < $stalledJobsCount; ++$j) {
                /** @var StallableJob $job */
                $job = $stalledJobs[$j];
                $job->setStatus(StallableJob::STATUS_STALLED);
                $job->setStalls(intval($job->getStalls()) + 1);
                $this->deleteJob($job);
                ++$countProcessed;
            }
            $this->flush();
            $this->updateProgress($progressCallback, $countProcessed, $stalledJobsCount);
        }

        return $countProcessed;
    }

    protected function stallableSaveHistory(StallableJob $job, $retry)
    {
        if (!$retry) {
            $this->deleteJob($job);
        }

        return $retry;
    }

    protected function stallableSave(StallableJob $job)
    {
        // Generate crc hash for the job
        $hashValues = array(get_class($job), $job->getMethod(), $job->getWorkerName(), $job->getArgs());
        $crcHash = hash('sha256', serialize($hashValues));
        $job->setCrcHash($crcHash);

        if (true === $job->getBatch()) {
            $oldJob = $this->updateNearestBatch($job);
            if ($oldJob) {
                return $oldJob;
            }
        }

        // Just save a new job
        $this->persist($job);

        return $job;
    }

    abstract protected function updateNearestBatch(Job $job);

    /**
     * @param string $objectName
     */
    abstract protected function stopIdGenerator($objectName);

    abstract protected function restoreIdGenerator($objectName);

    /**
     * @param string|null $workerName
     * @param string|null $methodName
     * @param bool        $prioritize
     */
    abstract public function getJobQueryBuilder($workerName = null, $methodName = null, $prioritize = true);

    protected function resetJob(RetryableJob $job)
    {
        if (!$job instanceof StallableJob) {
            throw new \InvalidArgumentException('$job should be instance of '.StallableJob::class);
        }
        $job->setStatus(BaseJob::STATUS_NEW);
        $job->setMessage(null);
        $job->setFinishedAt(null);
        $job->setStartedAt(null);
        $job->setElapsed(null);
        $job->setRetries($job->getRetries() + 1);
        $this->persist($job);

        return true;
    }

    public function archiveNonRunningJobs($workerName = null, $methodName = null, callable $progressCallback = null)
    {
        return $this->archiveJobs($methodName, $methodName, DoctrineJobManager::TYPE_NON_RUNNING, $progressCallback);
    }

    public function archiveWaitingJobs($workerName = null, $methodName = null, callable $progressCallback = null)
    {
        return $this->archiveJobs($methodName, $methodName, DoctrineJobManager::TYPE_WAITING, $progressCallback);
    }

    abstract public function archiveJobs($workerName = null, $methodName = null, $type, callable $progressCallback = null);
    abstract public function deleteArchiveJobs($workerName = null, $methodName = null, callable $progressCallback = null);
}
