<?php

namespace Dtc\QueueBundle\Doctrine;

use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\ODM\MongoDB\DocumentRepository;
use Doctrine\ORM\EntityRepository;
use Dtc\QueueBundle\Model\BaseJob;
use Dtc\QueueBundle\Model\Job;
use Dtc\QueueBundle\Model\JobTiming;
use Dtc\QueueBundle\Model\JobTimingManager;
use Dtc\QueueBundle\Model\PriorityJobManager;
use Dtc\QueueBundle\Model\RetryableJob;
use Dtc\QueueBundle\Model\Run;
use Dtc\QueueBundle\Model\RunManager;
use Dtc\QueueBundle\Util\Util;

abstract class BaseJobManager extends PriorityJobManager
{
    /** Number of jobs to prune / reset / gather at a time */
    const FETCH_COUNT = 100;

    /** Number of seconds before a job is considered stalled if the runner is no longer active */
    const STALLED_SECONDS = 1800;

    /**
     * @var ObjectManager
     */
    protected $objectManager;
    /**
     * @var string
     */
    protected $jobArchiveClass;

    /**
     * @param string $objectName
     * @param string $archiveObjectName
     * @param string $runClass
     * @param string $runArchiveClass
     */
    public function __construct(RunManager $runManager, JobTimingManager $jobTimingManager, ObjectManager $objectManager,
        $jobClass,
        $jobArchiveClass)
    {
        $this->objectManager = $objectManager;
        $this->jobArchiveClass = $jobArchiveClass;
        parent::__construct($runManager, $jobTimingManager, $jobClass);
    }

    /**
     * @return ObjectManager
     */
    public function getObjectManager()
    {
        return $this->objectManager;
    }

    /**
     * @return string
     */
    public function getJobArchiveClass()
    {
        return $this->jobArchiveClass;
    }

    /**
     * @return ObjectRepository
     */
    public function getRepository()
    {
        return $this->getObjectManager()->getRepository($this->getJobClass());
    }

    /**
     * @param string $objectName
     */
    abstract protected function countJobsByStatus($objectName, $status, $workerName = null, $method = null);

    public function resetErroneousJobs($workerName = null, $method = null)
    {
        $count = $this->countJobsByStatus($this->getJobArchiveClass(), Job::STATUS_ERROR, $workerName, $method);

        $criterion = ['status' => Job::STATUS_ERROR];
        $this->addWorkerNameMethod($criterion, $workerName, $method);

        $countProcessed = 0;
        for ($i = 0; $i < $count; $i += static::FETCH_COUNT) {
            $countProcessed += $this->resetJobsByCriterion(
                $criterion, static::FETCH_COUNT, $i);
        }

        return $countProcessed;
    }

    /**
     * Sets the status to Job::STATUS_EXPIRED for those jobs that are expired.
     *
     * @param null $workerName
     * @param null $method
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
        for ($i = 0; $i < $count; $i += static::FETCH_COUNT) {
            $expiredJobs = $repository->findBy($criterion, null, static::FETCH_COUNT, $i);
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

    protected function flush()
    {
        $this->getObjectManager()->flush();
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

        for ($i = 0; $i < $count; $i += static::FETCH_COUNT) {
            $runningJobs = $repository->findBy($criterion, null, static::FETCH_COUNT, $i);
            if (!empty($runningJobs)) {
                foreach ($runningJobs as $job) {
                    /** @var RetryableJob $job */
                    if (null !== $runId = $job->getRunId()) {
                        $runningJobsById[$runId][] = $job;
                    }
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

    protected function updateMaxStatus(RetryableJob $job, $status, $max = null, $count = 0)
    {
        if (null !== $max && $count >= $max) {
            $job->setStatus($status);

            return true;
        }

        return false;
    }

    protected function runStalledLoop($i, $count, array $stalledJobs, &$countProcessed)
    {
        $objectManager = $this->getObjectManager();
        $newCount = 0;
        for ($j = $i, $max = $i + static::FETCH_COUNT; $j < $max && $j < $count; ++$j) {
            /* RetryableJob $job */
            $job = $stalledJobs[$j];
            $job->setStalledCount($job->getStalledCount() + 1);
            if ($this->updateMaxStatus($job, RetryableJob::STATUS_MAX_STALLED, $job->getMaxStalled(), $job->getStalledCount()) ||
                $this->updateMaxStatus($job, RetryableJob::STATUS_MAX_RETRIES, $job->getMaxRetries(), $job->getRetries())) {
                $objectManager->remove($job);
                $this->jobTiminigManager->recordTiming(JobTiming::STATUS_FINISHED_STALLED);
                continue;
            }

            $job->setRetries($job->getRetries() + 1);
            $job->setStatus(BaseJob::STATUS_NEW);
            $job->setLocked(null);
            $job->setLockedAt(null);
            $objectManager->persist($job);
            ++$newCount;
            ++$countProcessed;
        }

        return $newCount;
    }

    public function resetStalledJobs($workerName = null, $method = null)
    {
        $stalledJobs = $this->getStalledJobs($workerName, $method);

        $countProcessed = 0;
        for ($i = 0, $count = count($stalledJobs); $i < $count; $i += static::FETCH_COUNT) {
            $newCount = $this->runStalledLoop($i, $count, $stalledJobs, $countProcessed);
            $this->flush();
            for ($j = 0; $j < $newCount; ++$j) {
                $this->jobTiminigManager->recordTiming(JobTiming::STATUS_FINISHED_STALLED);
                $this->jobTiminigManager->recordTiming(JobTiming::STATUS_INSERT);
            }
        }

        return $countProcessed;
    }

    /**
     * @param string $workerName
     * @param string $method
     */
    public function pruneStalledJobs($workerName = null, $method = null)
    {
        $stalledJobs = $this->getStalledJobs($workerName, $method);
        $objectManager = $this->getObjectManager();

        $countProcessed = 0;
        for ($i = 0, $count = count($stalledJobs); $i < $count; $i += static::FETCH_COUNT) {
            for ($j = $i, $max = $i + static::FETCH_COUNT; $j < $max && $j < $count; ++$j) {
                /** @var RetryableJob $job */
                $job = $stalledJobs[$j];
                $job->setStalledCount($job->getStalledCount() + 1);
                $job->setStatus(BaseJob::STATUS_ERROR);
                $job->setMessage('stalled');
                $this->updateMaxStatus($job, RetryableJob::STATUS_MAX_STALLED, $job->getMaxStalled(), $job->getStalledCount());
                $objectManager->remove($job);
                ++$countProcessed;
            }
            $this->flush();
        }

        return $countProcessed;
    }

    public function deleteJob(\Dtc\QueueBundle\Model\Job $job)
    {
        $objectManager = $this->getObjectManager();
        $objectManager->remove($job);
        $objectManager->flush();
    }

    public function saveHistory(\Dtc\QueueBundle\Model\Job $job)
    {
        $this->deleteJob($job); // Should cause job to be archived
    }

    protected function prioritySave(\Dtc\QueueBundle\Model\Job $job)
    {
        // Generate crc hash for the job
        $hashValues = array($job->getClassName(), $job->getMethod(), $job->getWorkerName(), $job->getArgs());
        $crcHash = hash('sha256', serialize($hashValues));
        $job->setCrcHash($crcHash);
        $objectManager = $this->getObjectManager();

        if (true === $job->getBatch()) {
            $oldJob = $this->updateNearestBatch($job);
            if ($oldJob) {
                return $oldJob;
            }
        }

        // Just save a new job
        $this->resetSaveOk(__FUNCTION__);
        $objectManager->persist($job);
        $objectManager->flush();

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
        $offset)
    {
        $objectManager = $this->getObjectManager();
        $this->resetSaveOk(__FUNCTION__);
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
            $this->resetJob($jobArchive, $className, $countProcessed);
        }
        $objectManager->flush();

        $this->restoreIdGenerator($objectName);

        return $countProcessed;
    }

    protected function resetSaveOk($function)
    {
    }

    /**
     * @param null $workerName
     * @param null $methodName
     * @param bool $prioritize
     */
    abstract public function getJobQueryBuilder($workerName = null, $methodName = null, $prioritize = true);

    /**
     * @param RetryableJob $jobArchive
     * @param $className
     * @param $countProcessed
     */
    protected function resetJob(RetryableJob $jobArchive, $className, &$countProcessed)
    {
        $objectManager = $this->getObjectManager();
        if ($this->updateMaxStatus($jobArchive, RetryableJob::STATUS_MAX_RETRIES, $jobArchive->getMaxRetries(), $jobArchive->getRetries())) {
            $objectManager->persist($jobArchive);

            return;
        }

        /** @var RetryableJob $job */
        $job = new $className();

        Util::copy($jobArchive, $job);
        $job->setStatus(BaseJob::STATUS_NEW);
        $job->setLocked(null);
        $job->setLockedAt(null);
        $job->setMessage(null);
        $job->setFinishedAt(null);
        $job->setStartedAt(null);
        $job->setElapsed(null);
        $job->setRetries($job->getRetries() + 1);
        $objectManager->persist($job);
        $objectManager->remove($jobArchive);
        $this->jobTiminigManager->recordTiming(JobTiming::STATUS_INSERT);
        ++$countProcessed;
    }

    abstract public function getWorkersAndMethods();
    abstract public function countLiveJobs($workerName = null, $methodName = null);
    abstract public function archiveAllJobs($workerName = null, $methodName = null, $progressCallback);
}
