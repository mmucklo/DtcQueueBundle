<?php

namespace Dtc\QueueBundle\Doctrine;

use Doctrine\ODM\MongoDB\DocumentRepository;
use Doctrine\ORM\EntityRepository;
use Dtc\QueueBundle\Model\BaseJob;
use Dtc\QueueBundle\Model\RetryableJob;
use Dtc\QueueBundle\Model\Job;
use Dtc\QueueBundle\Model\JobTiming;
use Dtc\QueueBundle\Model\StallableJob;
use Dtc\QueueBundle\Model\Run;
use Dtc\QueueBundle\Util\Util;

abstract class DoctrineJobManager extends BaseDoctrineJobManager
{
    use ProgressCallbackTrait;

    /** Number of seconds before a job is considered stalled if the runner is no longer active */
    const STALLED_SECONDS = 1800;

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

    protected function addWorkerNameMethod(array &$criterion, $workerName = null, $method = null)
    {
        if (null !== $workerName) {
            $criterion['workerName'] = $workerName;
        }
        if (null !== $method) {
            $criterion['method'] = $method;
        }
    }

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

    protected function getStalledJobs($workerName = null, $method = null)
    {
        $count = $this->countJobsByStatus($this->getJobClass(), Job::STATUS_RUNNING, $workerName, $method);

        $criterion = ['status' => BaseJob::STATUS_RUNNING];
        $this->addWorkerNameMethod($criterion, $workerName, $method);

        $runningJobs = $this->findRunningJobs($criterion, $count);

        return $this->extractStalledJobs($runningJobs);
    }

    protected function findRunningJobs($criterion, $count)
    {
        $repository = $this->getRepository();
        $runningJobsById = [];

        $metadata = $this->getObjectManager()->getClassMetadata($this->getJobClass());
        $identifierData = $metadata->getIdentifier();
        $idColumn = isset($identifierData[0]) ? $identifierData[0] : 'id';

        $fetchCount = $this->getFetchCount($count);
        for ($i = 0; $i < $count; $i += $fetchCount) {
            $runningJobs = $repository->findBy($criterion, [$idColumn => 'ASC'], $fetchCount, $i);
            if (!empty($runningJobs)) {
                foreach ($runningJobs as $job) {
                    /** @var StallableJob $job */
                    $runId = $job->getRunId();
                    $runningJobsById[$runId][] = $job;
                }
            }
        }

        return $runningJobsById;
    }

    /**
     * @param $runId
     * @param array $jobs
     * @param array $stalledJobs
     */
    protected function extractStalledLiveRuns($runId, array $jobs, array &$stalledJobs)
    {
        $objectManager = $this->getObjectManager();
        $runRepository = $objectManager->getRepository($this->getRunManager()->getRunClass());
        if ($run = $runRepository->find($runId)) {
            foreach ($jobs as $job) {
                if ($run->getCurrentJobId() == $job->getId()) {
                    continue;
                }
                $stalledJobs[] = $job;
            }
        }
    }

    /**
     * @param array $runningJobsById
     *
     * @return array
     */
    protected function extractStalledJobs(array $runningJobsById)
    {
        $stalledJobs = [];
        foreach (array_keys($runningJobsById) as $runId) {
            if (!$runId && 0 !== $runId) {
                $stalledJobs = array_merge($stalledJobs, $runningJobsById[$runId]);
                continue;
            }
            $this->extractStalledLiveRuns($runId, $runningJobsById[$runId], $stalledJobs);
            $this->extractStalledJobsRunArchive($runningJobsById, $stalledJobs, $runId);
        }

        return $stalledJobs;
    }

    protected function extractStalledJobsRunArchive(array $runningJobsById, array &$stalledJobs, $runId)
    {
        $runManager = $this->getRunManager();
        if (!method_exists($runManager, 'getObjectManager')) {
            return;
        }
        if (!method_exists($runManager, 'getRunArchiveClass')) {
            return;
        }

        /** @var EntityRepository|DocumentRepository $runArchiveRepository */
        $runArchiveRepository = $runManager->getObjectManager()->getRepository($runManager->getRunArchiveClass());
        /** @var Run $run */
        if ($run = $runArchiveRepository->find($runId)) {
            if ($endTime = $run->getEndedAt()) {
                // Did it end over an hour ago
                if ((time() - $endTime->getTimestamp()) > static::STALLED_SECONDS) {
                    $stalledJobs = array_merge($stalledJobs, $runningJobsById[$runId]);
                }
            }
        }
    }

    /**
     * @param int   $i
     * @param int   $count
     * @param int   $saveCount
     * @param array $stalledJobs
     *
     * @return int
     */
    protected function runStalledLoop($i, $count, $saveCount, array $stalledJobs)
    {
        $resetCount = 0;
        for ($j = $i, $max = $i + $saveCount; $j < $max && $j < $count; ++$j) {
            /* StallableJob $job */
            $job = $stalledJobs[$j];
            $job->setStatus(StallableJob::STATUS_STALLED);
            if ($this->saveHistory($job)) {
                ++$resetCount;
            }
        }

        return $resetCount;
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
     * @param array $criterion
     * @param int   $limit
     * @param int   $offset
     */
    private function resetJobsByCriterion(
        array $criterion,
        $limit,
        $offset
    ) {
        $objectManager = $this->getObjectManager();
        $objectName = $this->getJobClass();
        $archiveObjectName = $this->getJobArchiveClass();
        $jobRepository = $objectManager->getRepository($objectName);
        $jobArchiveRepository = $objectManager->getRepository($archiveObjectName);
        $className = $jobRepository->getClassName();
        $metadata = $objectManager->getClassMetadata($className);
        $this->stopIdGenerator($objectName);
        $identifierData = $metadata->getIdentifier();
        $idColumn = isset($identifierData[0]) ? $identifierData[0] : 'id';
        $results = $jobArchiveRepository->findBy($criterion, [$idColumn => 'ASC'], $limit, $offset);
        $countProcessed = 0;

        foreach ($results as $jobArchive) {
            $countProcessed += $this->resetArchiveJob($jobArchive);
        }
        $objectManager->flush();

        $this->restoreIdGenerator($objectName);

        return $countProcessed;
    }

    /**
     * @param string|null $workerName
     * @param string|null $methodName
     * @param bool        $prioritize
     */
    abstract public function getJobQueryBuilder($workerName = null, $methodName = null, $prioritize = true);

    /**
     * @param StallableJob $jobArchive
     *
     * @return int Number of jobs reset
     */
    protected function resetArchiveJob(StallableJob $jobArchive)
    {
        $objectManager = $this->getObjectManager();
        if ($this->updateMaxStatus($jobArchive, StallableJob::STATUS_MAX_RETRIES, $jobArchive->getMaxRetries(), $jobArchive->getRetries())) {
            $objectManager->persist($jobArchive);

            return 0;
        }

        /** @var StallableJob $job */
        $className = $this->getJobClass();
        $newJob = new $className();
        Util::copy($jobArchive, $newJob);
        $this->resetJob($newJob);
        $objectManager->remove($jobArchive);
        $this->flush();

        return 1;
    }

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

    abstract public function getWorkersAndMethods();

    abstract public function countLiveJobs($workerName = null, $methodName = null);

    abstract public function archiveAllJobs($workerName = null, $methodName = null, callable $progressCallback = null);
}
