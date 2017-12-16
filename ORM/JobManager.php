<?php

namespace Dtc\QueueBundle\ORM;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\ORM\QueryBuilder;
use Dtc\QueueBundle\Doctrine\BaseJobManager;
use Dtc\QueueBundle\Entity\Job;
use Dtc\QueueBundle\Model\BaseJob;
use Dtc\QueueBundle\Model\RetryableJob;
use Symfony\Component\Process\Exception\LogicException;

class JobManager extends BaseJobManager
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
    public function pruneErroneousJobs($workerName = null, $method = null)
    {
        /** @var EntityManager $objectManager */
        $objectManager = $this->getObjectManager();
        $queryBuilder = $objectManager->createQueryBuilder()->delete($this->getJobArchiveClass(), 'j');
        $queryBuilder->where('j.status = :status')
            ->setParameter(':status', BaseJob::STATUS_ERROR);

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
            ->setParameter(':expiresAt', new \DateTime());
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
        return $this->removeOlderThan($this->getJobArchiveClass(),
                'updatedAt',
                $olderThan);
    }

    public function getJobCount($workerName = null, $method = null)
    {
        /** @var EntityManager $objectManager */
        $objectManager = $this->getObjectManager();
        $queryBuilder = $objectManager->createQueryBuilder();

        $queryBuilder = $queryBuilder->select('count(j)')->from($this->getJobClass(), 'j');

        $where = 'where';
        if (null !== $workerName) {
            if (null !== $method) {
                $queryBuilder->where($queryBuilder->expr()->andX(
                    $queryBuilder->expr()->eq('j.workerName', ':workerName'),
                                                $queryBuilder->expr()->eq('j.method', ':method')
                ))
                    ->setParameter(':method', $method);
            } else {
                $queryBuilder->where('j.workerName = :workerName');
            }
            $queryBuilder->setParameter(':workerName', $workerName);
            $where = 'andWhere';
        } elseif (null !== $method) {
            $queryBuilder->where('j.method = :method')->setParameter(':method', $method);
            $where = 'andWhere';
        }

        $dateTime = new \DateTime();
        // Filter
        $queryBuilder
            ->$where($queryBuilder->expr()->orX(
                $queryBuilder->expr()->isNull('j.whenAt'),
                                        $queryBuilder->expr()->lte('j.whenAt', ':whenAt')
            ))
            ->andWhere($queryBuilder->expr()->orX(
                $queryBuilder->expr()->isNull('j.expiresAt'),
                $queryBuilder->expr()->gt('j.expiresAt', ':expiresAt')
            ))
            ->andWhere('j.locked is NULL')
            ->setParameter(':whenAt', $dateTime)
            ->setParameter(':expiresAt', $dateTime);

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
                $result[$method] = [BaseJob::STATUS_NEW => 0,
                    BaseJob::STATUS_RUNNING => 0,
                    RetryableJob::STATUS_EXPIRED => 0,
                    RetryableJob::STATUS_MAX_ERROR => 0,
                    RetryableJob::STATUS_MAX_STALLED => 0,
                    RetryableJob::STATUS_MAX_RETRIES => 0,
                    BaseJob::STATUS_SUCCESS => 0,
                    BaseJob::STATUS_ERROR => 0, ];
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
     *
     * @return Job|null
     */
    public function getJob($workerName = null, $methodName = null, $prioritize = true, $runId = null)
    {
        $queryBuilder = $this->getJobQueryBuilder($workerName, $methodName, $prioritize);
        $queryBuilder->select('j.id');
        $queryBuilder->setMaxResults(1);

        /** @var QueryBuilder $queryBuilder */
        $query = $queryBuilder->getQuery();
        $jobs = $query->getResult();

        return $this->takeJob($jobs, $runId);
    }

    /**
     * @param null $workerName
     * @param null $methodName
     * @param bool $prioritize
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
            $queryBuilder->addOrderBy('j.whenAt', 'ASC');
        } else {
            $queryBuilder->orderBy('j.whenAt', 'ASC');
        }

        return $queryBuilder;
    }

    protected function addStandardPredicate(QueryBuilder $queryBuilder) {
        $dateTime = new \DateTime();
        $queryBuilder
            ->where('j.status = :status')->setParameter(':status', BaseJob::STATUS_NEW)
            ->andWhere('j.locked is NULL')
            ->andWhere($queryBuilder->expr()->orX(
                $queryBuilder->expr()->isNull('j.whenAt'),
                $queryBuilder->expr()->lte('j.whenAt', ':whenAt')
            ))
            ->andWhere($queryBuilder->expr()->orX(
                $queryBuilder->expr()->isNull('j.expiresAt'),
                $queryBuilder->expr()->gt('j.expiresAt', ':expiresAt')
            ))
            ->setParameter(':whenAt', $dateTime)
            ->setParameter(':expiresAt', $dateTime);
    }

    protected function takeJob($jobs, $runId = null)
    {
        if (isset($jobs[0]['id'])) {
            /** @var EntityRepository $repository */
            $repository = $this->getRepository();
            /** @var QueryBuilder $queryBuilder */
            $queryBuilder = $repository->createQueryBuilder('j');
            $queryBuilder
                ->update()
                ->set('j.locked', ':locked')
                ->setParameter(':locked', true)
                ->set('j.lockedAt', ':lockedAt')
                ->setParameter(':lockedAt', new \DateTime())
                ->set('j.status', ':status')
                ->setParameter(':status', BaseJob::STATUS_RUNNING);
            if (null !== $runId) {
                $queryBuilder
                    ->set('j.runId', ':runId')
                    ->setParameter(':runId', $runId);
            }
            $queryBuilder->where('j.id = :id');
            $queryBuilder->andWhere('j.locked is NULL');
            $queryBuilder->setParameter(':id', $jobs[0]['id']);
            $resultCount = $queryBuilder->getQuery()->execute();

            if (1 === $resultCount) {
                return $repository->find($jobs[0]['id']);
            }
        }

        return null;
    }

    /**
     * Tries to update the nearest job as a batch.
     *
     * @param \Dtc\QueueBundle\Model\Job $job
     *
     * @return mixed|null
     */
    public function updateNearestBatch(\Dtc\QueueBundle\Model\Job $job)
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = $this->getRepository()->createQueryBuilder('j');
        $queryBuilder->select()
            ->where('j.crcHash = :crcHash')
            ->andWhere('j.status = :status')
            ->setParameter(':status', BaseJob::STATUS_NEW)
            ->setParameter(':crcHash', $job->getCrcHash())
            ->orderBy('j.whenAt', 'ASC')
            ->setMaxResults(1);
        $existingJobs = $queryBuilder->getQuery()->execute();

        if (empty($existingJobs)) {
            return null;
        }
        /** @var Job $existingJob */
        $existingJob = $existingJobs[0];

        $newPriority = max($job->getPriority(), $existingJob->getPriority());
        $newWhenAt = min($job->getWhenAt(), $existingJob->getWhenAt());

        $this->updateBatchJob($existingJob, $newPriority, $newWhenAt);

        return $existingJob;
    }

    protected function updateBatchJob(Job $existingJob, $newPriority, $newWhenAt)
    {
        $existingPriority = $existingJob->getPriority();
        $existingWhenAt = $existingJob->getWhenAt();

        if ($newPriority !== $existingPriority || $newWhenAt !== $existingWhenAt) {
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
            if ($newWhenAt !== $existingWhenAt) {
                $existingJob->setWhenAt($newWhenAt);
                $queryBuilder->set('j.whenAt', ':whenAt')
                    ->setParameter(':whenAt', $newWhenAt);
            }
            $queryBuilder->where('j.id = :id');
            $queryBuilder->andWhere('j.locked is NULL');
            $queryBuilder->setParameter(':id', $existingJob->getId());
            $queryBuilder->getQuery()->execute();
        }

        return $existingJob;
    }

    public function getWorkersAndMethods() {
        /** @var EntityRepository $repository */
        $repository = $this->getRepository();
        $queryBuilder = $repository->createQueryBuilder('j');
        $this->addStandardPredicate($queryBuilder);
        $queryBuilder
            ->select('DISTINCT j.workerName, j.method');

        $results = $queryBuilder->getQuery()->getArrayResult();
        if (!$results) {
            return [];
        }
        $workerMethods = [];
        foreach ($results as $result) {
            $workerMethods[$result['workerName']][] = $result['method'];
        }
        return $workerMethods;
    }

    public function archiveAllJobs($workerName = null, $method = null) {
        // First mark all Live non-running jobs as Archive
        /** @var EntityManager $entityManager */
        $entityManager = $this->getObjectManager();
        $repository = $this->getRepository();
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = $repository->createQueryBuilder('j');
        $queryBuilder->update($this->getJobClass(), 'j')
            ->set('j.status', ':statusArchive')
            ->setParameter(':statusArchive', Job::STATUS_ARCHIVE);
        $this->addWorkerNameCriterion($queryBuilder, $workerName, $method);
        $this->addStandardPredicate($queryBuilder);
        $resultCount = $queryBuilder->getQuery()->execute();

        if ($resultCount) {
            $this->runArchive($workerName, $method);
        }
    }

    /**
     * Move jobs in 'archive' status to the archive table.
     *
     *  This is a bit of a hack to run a lower level query so as to process the INSERT INTO SELECT
     *   All on the server as "INSERT INTO SELECT" is not supported natively in Doctrine.
     * @param null $workerName
     * @param null $methodName
     */
    protected function runArchive($workerName = null, $methodName = null) {
        $entityManager = $this->getObjectManager();
        $metaFactory = $entityManager->getMetadataFactory();
        /** @var ClassMetadata $metadata */
        $metadata = $metaFactory->getMetadataFor($this->getJobClass());
        /** @var ClassMetadata $metaDataArchive */
        $metaDataArchive = $metaFactory->getMetadataFor($this->getJobArchiveClass());
        $tableName = $metadata->getTableName();
        $tableNameArchive = $metaDataArchive->getTableName();
        $fields = $metadata->getFieldNames();

        $columns = [];
        foreach ($fields as $field) {
            $mapping = $metadata->getFieldMapping($field);
            $columns[] = $mapping['columnName'];
        }

        $clause = implode(',', $columns);
        $sql = "INSERT into $tableNameArchive ($clause) SELECT $clause FROM $tableName WHERE status = '" . Job::STATUS_ARCHIVE . "'";
        $entityManager->getConnection()->executeUpdate($sql);

        // Delete any jobs that are in archive status
        $repository = $this->getRepository();
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = $repository->createQueryBuilder('j');
        $queryBuilder->delete($this->getJobClass(), 'j');
        $this->addWorkerNameCriterion($queryBuilder, $workerName, $methodName);
        $queryBuilder->andWhere('j.status = :statusArchive')
            ->setParameter(':statusArchive', Job::STATUS_ARCHIVE);
        $queryBuilder->getQuery()->execute();

    }
}
