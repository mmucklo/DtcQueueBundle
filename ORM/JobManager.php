<?php

namespace Dtc\QueueBundle\ORM;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Dtc\QueueBundle\Doctrine\DoctrineJobManager;
use Dtc\QueueBundle\Entity\Job;
use Dtc\QueueBundle\Exception\UnsupportedException;
use Dtc\QueueBundle\Model\BaseJob;
use Dtc\QueueBundle\Util\Util;
use Symfony\Component\Process\Exception\LogicException;

class JobManager extends DoctrineJobManager
{
    use CommonTrait;
    protected static $saveInsertCalled = null;
    protected static $resetInsertCalled = null;

    public function countJobsByStatus($objectName, $status, $workerName = null, $method = null)
    {
        /** @var EntityManager $objectManager */
        $objectManager = $this->getObjectManager();

        $queryBuilder = $objectManager
            ->createQueryBuilder()
            ->select('count(a.id)')
            ->from($objectName, 'a')
            ->where('a.status = :status');

        if (null !== $workerName) {
            $queryBuilder->andWhere('a.workerName = :workerName')
                ->setParameter(':workerName', $workerName);
        }

        if (null !== $method) {
            $queryBuilder->andWhere('a.method = :method')
                ->setParameter(':method', $workerName);
        }

        $count = $queryBuilder->setParameter(':status', $status)
            ->getQuery()->getSingleScalarResult();

        if (!$count) {
            return 0;
        }

        return $count;
    }

    /**
     * @param string|null $workerName
     * @param string|null $method
     *
     * @return int Count of jobs pruned
     */
    public function pruneExceptionJobs($workerName = null, $method = null)
    {
        /** @var EntityManager $objectManager */
        $objectManager = $this->getObjectManager();
        $queryBuilder = $objectManager->createQueryBuilder()->delete($this->getJobArchiveClass(), 'j');
        $queryBuilder->where('j.status = :status')
            ->setParameter(':status', BaseJob::STATUS_EXCEPTION);

        $this->addWorkerNameCriterion($queryBuilder, $workerName, $method);
        $query = $queryBuilder->getQuery();

        return intval($query->execute());
    }

    protected function resetSaveOk($function)
    {
        $objectManager = $this->getObjectManager();
        $splObjectHash = spl_object_hash($objectManager);

        if ('save' === $function) {
            $compare = static::$resetInsertCalled;
        } else {
            $compare = static::$saveInsertCalled;
        }

        if ($splObjectHash === $compare) {
            // Insert SQL is cached...
            $msg = "Can't call save and reset within the same process cycle (or using the same EntityManager)";
            throw new LogicException($msg);
        }

        if ('save' === $function) {
            static::$saveInsertCalled = spl_object_hash($objectManager);
        } else {
            static::$resetInsertCalled = spl_object_hash($objectManager);
        }
    }

    /**
     * @param string $workerName
     * @param string $method
     */
    protected function addWorkerNameCriterion(QueryBuilder $queryBuilder, $workerName = null, $method = null)
    {
        if (null !== $workerName) {
            $queryBuilder->andWhere('j.workerName = :workerName')->setParameter(':workerName', $workerName);
        }

        if (null !== $method) {
            $queryBuilder->andWhere('j.method = :method')->setParameter(':method', $method);
        }
    }

    protected function updateExpired($workerName = null, $method = null)
    {
        /** @var EntityManager $objectManager */
        $objectManager = $this->getObjectManager();
        $queryBuilder = $objectManager->createQueryBuilder()->update($this->getJobClass(), 'j');
        $queryBuilder->set('j.status', ':newStatus');
        $queryBuilder->where('j.expiresAt <= :expiresAt')
            ->setParameter(':expiresAt', Util::getMicrotimeDateTime());
        $queryBuilder->andWhere('j.status = :status')
            ->setParameter(':status', BaseJob::STATUS_NEW)
            ->setParameter(':newStatus', Job::STATUS_EXPIRED);

        $this->addWorkerNameCriterion($queryBuilder, $workerName, $method);
        $query = $queryBuilder->getQuery();

        return intval($query->execute());
    }

    /**
     * Removes archived jobs older than $olderThan.
     *
     * @param \DateTime $olderThan
     */
    public function pruneArchivedJobs(\DateTime $olderThan)
    {
        return $this->removeOlderThan(
            $this->getJobArchiveClass(),
                'updatedAt',
                $olderThan
        );
    }

    public function getWaitingJobCount($workerName = null, $method = null)
    {
        /** @var EntityManager $objectManager */
        $objectManager = $this->getObjectManager();
        $queryBuilder = $objectManager->createQueryBuilder();

        $queryBuilder = $queryBuilder->select('count(j)')->from($this->getJobClass(), 'j');

        $this->addWorkerNameCriterion($queryBuilder, $workerName, $method);
        $this->addStandardPredicate($queryBuilder);

        $query = $queryBuilder->getQuery();

        return $query->getSingleScalarResult();
    }

    /**
     * Get Jobs statuses.
     */
    public function getStatus()
    {
        $result = [];
        $this->getStatusByEntityName($this->getJobClass(), $result);
        $this->getStatusByEntityName($this->getJobArchiveClass(), $result);

        $finalResult = [];
        foreach ($result as $key => $item) {
            ksort($item);
            foreach ($item as $status => $count) {
                if (isset($finalResult[$key][$status])) {
                    $finalResult[$key][$status] += $count;
                } else {
                    $finalResult[$key][$status] = $count;
                }
            }
        }

        return $finalResult;
    }

    /**
     * @param string $entityName
     */
    protected function getStatusByEntityName($entityName, array &$result)
    {
        /** @var EntityManager $objectManager */
        $objectManager = $this->getObjectManager();
        $result1 = $objectManager->getRepository($entityName)->createQueryBuilder('j')->select('j.workerName, j.method, j.status, count(j) as c')
            ->groupBy('j.workerName, j.method, j.status')->getQuery()->getArrayResult();

        foreach ($result1 as $item) {
            $method = $item['workerName'].'->'.$item['method'].'()';
            if (!isset($result[$method])) {
                $result[$method] = static::getAllStatuses();
            }
            $result[$method][$item['status']] += intval($item['c']);
        }
    }

    /**
     * Get the next job to run (can be filtered by workername and method name).
     *
     * @param string $workerName
     * @param string $methodName
     * @param bool   $prioritize
     * @param int    $runId
     *
     * @return Job|null
     */
    public function getJob($workerName = null, $methodName = null, $prioritize = true, $runId = null)
    {
        do {
            $queryBuilder = $this->getJobQueryBuilder($workerName, $methodName, $prioritize);
            $queryBuilder->select('j.id');
            $queryBuilder->setMaxResults(100);

            /** @var QueryBuilder $queryBuilder */
            $query = $queryBuilder->getQuery();
            $jobs = $query->getResult();
            if ($jobs) {
                foreach ($jobs as $job) {
                    if ($job = $this->takeJob($job['id'], $runId)) {
                        return $job;
                    }
                }
            }
        } while ($jobs);

        return null;
    }

    /**
     * @param string|null $workerName
     * @param string|null $methodName
     * @param bool        $prioritize
     *
     * @return QueryBuilder
     */
    public function getJobQueryBuilder($workerName = null, $methodName = null, $prioritize = true)
    {
        /** @var EntityRepository $repository */
        $repository = $this->getRepository();
        $queryBuilder = $repository->createQueryBuilder('j');
        $this->addStandardPredicate($queryBuilder);
        $this->addWorkerNameCriterion($queryBuilder, $workerName, $methodName);

        if ($prioritize) {
            $queryBuilder->addOrderBy('j.priority', 'DESC');
            $queryBuilder->addOrderBy('j.whenUs', 'ASC');
        } else {
            $queryBuilder->orderBy('j.whenUs', 'ASC');
        }

        return $queryBuilder;
    }

    protected function addStandardPredicate(QueryBuilder $queryBuilder, $status = BaseJob::STATUS_NEW)
    {
        $dateTime = Util::getMicrotimeDateTime();
        $decimal = Util::getMicrotimeDecimalFormat($dateTime);

        $queryBuilder
            ->where('j.status = :status')->setParameter(':status', $status)
            ->andWhere($queryBuilder->expr()->orX(
                $queryBuilder->expr()->isNull('j.whenUs'),
                $queryBuilder->expr()->lte('j.whenUs', ':whenUs')
            ))
            ->andWhere($queryBuilder->expr()->orX(
                $queryBuilder->expr()->isNull('j.expiresAt'),
                $queryBuilder->expr()->gt('j.expiresAt', ':expiresAt')
            ))
            ->setParameter(':whenUs', $decimal)
            ->setParameter(':expiresAt', $dateTime);
    }

    /**
     * @param int $runId
     */
    protected function takeJob($jobId, $runId = null)
    {
        /** @var EntityRepository $repository */
        $repository = $this->getRepository();
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = $repository->createQueryBuilder('j');
        $queryBuilder
            ->update()
            ->set('j.status', ':status')
            ->setParameter(':status', BaseJob::STATUS_RUNNING);
        if (null !== $runId) {
            $queryBuilder
                ->set('j.runId', ':runId')
                ->setParameter(':runId', $runId);
        }
        $queryBuilder->set('j.startedAt', ':startedAt')
            ->setParameter(':startedAt', Util::getMicrotimeDateTime());
        $queryBuilder->where('j.id = :id');
        $queryBuilder->setParameter(':id', $jobId);
        $resultCount = $queryBuilder->getQuery()->execute();

        if (1 === $resultCount) {
            return $repository->find($jobId);
        }

        return null;
    }

    /**
     * Tries to update the nearest job as a batch.
     *
     * @param \Dtc\QueueBundle\Model\Job $job
     *
     * @return null|Job
     */
    public function updateNearestBatch(\Dtc\QueueBundle\Model\Job $job)
    {
        if (!$job instanceof Job) {
            throw new UnsupportedException('$job must be instance of '.Job::class);
        }

        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = $this->getRepository()->createQueryBuilder('j');
        $queryBuilder->select()
            ->where('j.crcHash = :crcHash')
            ->andWhere('j.status = :status')
            ->setParameter(':status', BaseJob::STATUS_NEW)
            ->setParameter(':crcHash', $job->getCrcHash())
            ->orderBy('j.whenUs', 'ASC')
            ->setMaxResults(1);
        $existingJobs = $queryBuilder->getQuery()->execute();

        if (empty($existingJobs)) {
            return null;
        }

        /** @var Job $existingJob */
        $existingJob = $existingJobs[0];

        $newPriority = max($job->getPriority(), $existingJob->getPriority());
        $newWhenUs = $existingJob->getWhenUs();
        $bcResult = bccomp($job->getWhenUs(), $existingJob->getWhenUs());
        if ($bcResult < 0) {
            $newWhenUs = $job->getWhenUs();
        }

        $this->updateBatchJob($existingJob, $newPriority, $newWhenUs);

        return $existingJob;
    }

    /**
     * @param int    $newPriority
     * @param string $newWhenUs
     */
    protected function updateBatchJob(Job $existingJob, $newPriority, $newWhenUs)
    {
        $existingPriority = $existingJob->getPriority();
        $existingWhenUs = $existingJob->getWhenUs();

        if ($newPriority !== $existingPriority || $newWhenUs !== $existingWhenUs) {
            /** @var EntityRepository $repository */
            $repository = $this->getRepository();
            /** @var QueryBuilder $queryBuilder */
            $queryBuilder = $repository->createQueryBuilder('j');
            $queryBuilder->update();
            if ($newPriority !== $existingPriority) {
                $existingJob->setPriority($newPriority);
                $queryBuilder->set('j.priority', ':priority')
                    ->setParameter(':priority', $newPriority);
            }
            if ($newWhenUs !== $existingWhenUs) {
                $existingJob->setWhenUs($newWhenUs);
                $queryBuilder->set('j.whenUs', ':whenUs')
                    ->setParameter(':whenUs', $newWhenUs);
            }
            $queryBuilder->where('j.id = :id');
            $queryBuilder->setParameter(':id', $existingJob->getId());
            $queryBuilder->getQuery()->execute();
        }

        return $existingJob;
    }

    public function getWorkersAndMethods($status = BaseJob::STATUS_NEW)
    {
        /** @var EntityRepository $repository */
        $repository = $this->getRepository();
        $queryBuilder = $repository->createQueryBuilder('j');
        $this->addStandardPredicate($queryBuilder, $status);
        $queryBuilder
            ->select('DISTINCT j.workerName, j.method');

        $results = $queryBuilder->getQuery()->getArrayResult();
        if (empty($results)) {
            return [];
        }
        $workerMethods = [];
        foreach ($results as $result) {
            $workerMethods[$result['workerName']][] = $result['method'];
        }

        return $workerMethods;
    }

    /**
     * @param string $workerName
     * @param string $methodName
     */
    public function countLiveJobs($workerName = null, $methodName = null)
    {
        /** @var EntityRepository $repository */
        $repository = $this->getRepository();
        $queryBuilder = $repository->createQueryBuilder('j');
        $this->addStandardPredicate($queryBuilder);
        $this->addWorkerNameCriterion($queryBuilder, $workerName, $methodName);
        $queryBuilder->select('count(j.id)');

        return $queryBuilder->getQuery()->getSingleScalarResult();
    }

    /**
     * @param string        $workerName
     * @param string        $methodName
     * @param callable|null $progressCallback
     */
    public function archiveAllJobs($workerName = null, $methodName = null, callable $progressCallback = null)
    {
        // First mark all Live non-running jobs as Archive
        $repository = $this->getRepository();
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = $repository->createQueryBuilder('j');
        $queryBuilder->update($this->getJobClass(), 'j')
            ->set('j.status', ':statusArchive')
            ->setParameter(':statusArchive', Job::STATUS_ARCHIVE);
        $this->addStandardPredicate($queryBuilder);
        $this->addWorkerNameCriterion($queryBuilder, $workerName, $methodName);
        $resultCount = $queryBuilder->getQuery()->execute();

        if ($resultCount) {
            $this->runArchive($workerName, $methodName, $progressCallback);
        }
    }

    /**
     * Move jobs in 'archive' status to the archive table.
     *
     *  This is a bit of a hack to run a lower level query so as to process the INSERT INTO SELECT
     *   All on the server as "INSERT INTO SELECT" is not supported natively in Doctrine.
     *
     * @param string|null   $workerName
     * @param string|null   $methodName
     * @param callable|null $progressCallback
     */
    protected function runArchive($workerName = null, $methodName = null, callable $progressCallback = null)
    {
        /** @var EntityManager $entityManager */
        $entityManager = $this->getObjectManager();
        $count = 0;
        do {
            /** @var EntityRepository $repository */
            $repository = $this->getRepository();
            $queryBuilder = $repository->createQueryBuilder('j');
            $queryBuilder->where('j.status = :status')
                ->setParameter(':status', Job::STATUS_ARCHIVE)
                ->setMaxResults(10000);

            $results = $queryBuilder->getQuery()->getArrayResult();
            foreach ($results as $jobRow) {
                $job = $repository->find($jobRow['id']);
                if ($job) {
                    $entityManager->remove($job);
                }
                ++$count;
                if (0 == $count % 10) {
                    $this->flush();
                    $this->updateProgress($progressCallback, $count);
                }
            }
            $this->flush();
            $this->updateProgress($progressCallback, $count);
        } while (!empty($results) && 10000 == count($results));
    }
}
