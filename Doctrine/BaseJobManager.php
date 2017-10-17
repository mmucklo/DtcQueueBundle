<?php

namespace Dtc\QueueBundle\Doctrine;

use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\ODM\MongoDB\DocumentRepository;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Dtc\QueueBundle\Model\AbstractJobManager;
use Dtc\QueueBundle\Model\BaseJob;
use Dtc\QueueBundle\Model\Job;
use Dtc\QueueBundle\Model\RetryableJob;
use Dtc\QueueBundle\Model\Run;
use Dtc\QueueBundle\Util\Util;

abstract class BaseJobManager extends AbstractJobManager
{
    /** Number of jobs to prune / reset / gather at a time */
    const FETCH_COUNT = 100;

    /** Number of seconds before a job is considered stalled if the runner is no longer active */
    const STALLED_SECONDS = 1800;
    protected $objectManager;
    protected $objectName;
    protected $archiveObjectName;
    protected $runClass;
    protected $runArchiveClass;
    protected static $saveInsertCalled = null;
    protected static $resetInsertCalled = null;

    /**
     * @param string $objectName
     * @param string $archiveObjectName
     * @param string $runClass
     * @param string $runArchiveClass
     */
    public function __construct(ObjectManager $objectManager,
        $objectName,
        $archiveObjectName,
        $runClass,
        $runArchiveClass)
    {
        $this->objectManager = $objectManager;
        $this->objectName = $objectName;
        $this->archiveObjectName = $archiveObjectName;
        $this->runClass = $runClass;
        $this->runArchiveClass = $runArchiveClass;
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
    public function getObjectName()
    {
        return $this->objectName;
    }

    /**
     * @return string
     */
    public function getArchiveObjectName()
    {
        return $this->archiveObjectName;
    }

    /**
     * @return string
     */
    public function getRunClass()
    {
        return $this->runClass;
    }

    /**
     * @return string
     */
    public function getRunArchiveClass()
    {
        return $this->runArchiveClass;
    }

    /**
     * @return ObjectRepository
     */
    public function getRepository()
    {
        return $this->getObjectManager()->getRepository($this->getObjectName());
    }

    /**
     * @param string $objectName
     */
    abstract protected function countJobsByStatus($objectName, $status, $workerName = null, $method = null);

    public function resetErroneousJobs($workerName = null, $method = null)
    {
        $count = $this->countJobsByStatus($this->getArchiveObjectName(), Job::STATUS_ERROR, $workerName, $method);

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
            if (!empty($expiredJobs)) {
                foreach ($expiredJobs as $expiredJob) {
                    /* @var Job $expiredJob */
                    $expiredJob->setStatus(Job::STATUS_EXPIRED);
                    $objectManager->remove($expiredJob);
                    ++$finalCount;
                }
            }
            $objectManager->flush();
        }

        return $finalCount;
    }

    protected function getStalledJobs($workerName = null, $method = null)
    {
        $count = $this->countJobsByStatus($this->getObjectName(), Job::STATUS_RUNNING, $workerName, $method);

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

    protected function extractStalledJobs(array $runningJobsById)
    {
        $objectManager = $this->getObjectManager();
        $runRepository = $objectManager->getRepository($this->runClass);
        /** @var EntityRepository|DocumentRepository $runArchiveRepository */
        $runArchiveRepository = $objectManager->getRepository($this->runArchiveClass);

        $stalledJobs = [];
        foreach (array_keys($runningJobsById) as $runId) {
            if ($runRepository->find($runId)) {
                continue;
            }
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

        return $stalledJobs;
    }

    public function resetStalledJobs($workerName = null, $method = null)
    {
        $stalledJobs = $this->getStalledJobs($workerName, $method);

        $objectManager = $this->getObjectManager();

        $countProcessed = 0;
        for ($i = 0, $count = count($stalledJobs); $i < $count; $i += static::FETCH_COUNT) {
            for ($j = $i, $max = $i + static::FETCH_COUNT; $j < $max && $j < $count; ++$j) {
                $job = $stalledJobs[$j];
                /* RetryableJob $job */
                $job->setStalledCount($job->getStalledCount() + 1);
                if (null !== ($maxStalled = $job->getMaxStalled()) && $job->getStalledCount() >= $maxStalled) {
                    $job->setStatus(RetryableJob::STATUS_MAX_STALLED);
                    $objectManager->remove($job);
                    continue;
                }
                if (null !== ($maxRetries = $job->getMaxRetries()) && $job->getRetries() >= $maxRetries) {
                    $job->setStatus(RetryableJob::STATUS_MAX_RETRIES);
                    $objectManager->remove($job);
                    continue;
                }

                $job->setRetries($job->getRetries() + 1);
                $job->setStatus(BaseJob::STATUS_NEW);
                $job->setLocked(null);
                $job->setLockedAt(null);
                $objectManager->persist($job);
                ++$countProcessed;
            }
            $objectManager->flush();
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
                if (null !== ($maxStalled = $job->getMaxStalled()) && ($job->getStalledCount() >= $job->getMaxStalled())) {
                    $job->setStatus(RetryableJob::STATUS_MAX_STALLED);
                }
                $objectManager->remove($job);
                ++$countProcessed;
            }
            $objectManager->flush();
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

    public function save(\Dtc\QueueBundle\Model\Job $job)
    {
        // Todo: Serialize args

        // Generate crc hash for the job
        $hashValues = array($job->getClassName(), $job->getMethod(), $job->getWorkerName(), $job->getArgs());
        $crcHash = hash('sha256', serialize($hashValues));
        $job->setCrcHash($crcHash);
        $objectManager = $this->getObjectManager();

        if (true === $job->getBatch()) {
            // See if similar job that hasn't run exists
            $criteria = array('crcHash' => $crcHash, 'status' => BaseJob::STATUS_NEW);
            $oldJob = $this->getRepository()->findOneBy($criteria);

            if ($oldJob) {
                // Old job exists - just override fields Set higher priority
                $oldJob->setPriority(max($job->getPriority(), $oldJob->getPriority()));
                $oldJob->setWhenAt(min($job->getWhenAt(), $oldJob->getWhenAt()));
                $oldJob->setBatch(true);
                $objectManager->persist($oldJob);
                $objectManager->flush();

                return $oldJob;
            }
        }

        // Just save a new job
        if (!$job->getId() && $objectManager instanceof EntityManager) {
            if (null !== self::$resetInsertCalled && spl_object_hash($objectManager) === self::$resetInsertCalled) {
                // Insert SQL is cached...
                throw new \Exception("Can't call save and reset within the same process cycle");
            }
            self::$saveInsertCalled = spl_object_hash($objectManager);
        }
        $objectManager->persist($job);
        $objectManager->flush();

        return $job;
    }

    /**
     * @param string $objectName
     */
    abstract protected function stopIdGenerator($objectName);

    abstract protected function restoreIdGenerator($objectName);

    /**
     * @param int $limit
     * @param int $offset
     */
    private function resetJobsByCriterion(
        $criterion,
        $limit,
        $offset)
    {
        $objectManager = $this->getObjectManager();
        if ($objectManager instanceof EntityManager) {
            if (null !== self::$saveInsertCalled && spl_object_hash($objectManager) === self::$saveInsertCalled) {
                // Insert SQL is cached...
                throw new \Exception("Can't call reset and save within the same process cycle");
            }
            self::$resetInsertCalled = spl_object_hash($objectManager);
        }

        $objectName = $this->getObjectName();
        $archiveObjectName = $this->getArchiveObjectName();
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
            /** @var RetryableJob $jobArchive */
            if (null !== ($maxRetries = $jobArchive->getMaxRetries()) && $jobArchive->getRetries() >= $maxRetries) {
                $jobArchive->setStatus(RetryableJob::STATUS_MAX_RETRIES);
                $objectManager->persist($jobArchive);
                continue;
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
            ++$countProcessed;
        }
        $objectManager->flush();

        $this->restoreIdGenerator($objectName);

        return $countProcessed;
    }
}
