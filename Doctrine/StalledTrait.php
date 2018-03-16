<?php

namespace Dtc\QueueBundle\Doctrine;

use Dtc\QueueBundle\Model\BaseJob;
use Dtc\QueueBundle\Model\StallableJob;
use Dtc\QueueBundle\Util\Util;

trait StalledTrait
{
    /**
     * @param int   $if
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

    protected function getStalledJobs($workerName = null, $method = null)
    {
        $count = $this->countJobsByStatus($this->getJobClass(), BaseJob::STATUS_RUNNING, $workerName, $method);

        $criterion = ['status' => BaseJob::STATUS_RUNNING];
        $this->addWorkerNameMethod($criterion, $workerName, $method);

        $runningJobs = $this->findRunningJobs($criterion, $count);

        return $this->extractStalledJobs($runningJobs);
    }

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

    private function extractStalledJobsRunArchive(array $runningJobsById, array &$stalledJobs, $runId)
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
     * @param array $runningJobsById
     *
     * @return array
     */
    private function extractStalledJobs(array $runningJobsById)
    {
        $stalledJobs = [];
        foreach (array_keys($runningJobsById) as $runId) {
            if (!$runId && 0 !== $runId) {
                $stalledJobs = array_merge($stalledJobs, $runningJobsById[$runId]);
                continue;
            }
            $this->extractStalledFromLiveRuns($runId, $runningJobsById[$runId], $stalledJobs);
            $this->extractStalledJobsRunArchive($runningJobsById, $stalledJobs, $runId);
        }

        return $stalledJobs;
    }

    /**
     * @param $runId
     * @param array $jobs
     * @param array $stalledJobs
     */
    private function extractStalledFromLiveRuns($runId, array $jobs, array &$stalledJobs)
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
            return;
        }

        // Can't find run amongst live runs
        $stalledJobs = array_merge($stalledJobs, $jobs);
    }
}
