<?php

namespace Dtc\QueueBundle\ORM;

use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Id\AssignedGenerator;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;
use Dtc\QueueBundle\Entity\Job;
use Dtc\QueueBundle\Model\JobManagerInterface;
use Dtc\QueueBundle\Util\Util;

class JobManager implements JobManagerInterface
{
    protected $entityManager;
    protected $entityName;
    protected $archiveEntityName;

    public function __construct(EntityManager $entityManager, $entityName, $archiveEntityName)
    {
        $this->entityManager = $entityManager;
        $this->entityName = $entityName;
        $this->archiveEntityName = $archiveEntityName;
        if (!$entityName) {
            throw new \Exception('$entityName is empty');
        }
        if (!$archiveEntityName) {
            throw new \Exception('$archiveEntityName is empty');
        }
    }

    /**
     * @return EntityManager
     */
    public function getEntityManager()
    {
        return $this->entityManager;
    }

    /**
     * @return string
     */
    public function getEntityName()
    {
        return $this->entityName;
    }

    /**
     * @return string
     */
    public function getArchiveEntityName()
    {
        return $this->archiveEntityName;
    }

    /**
     * @return EntityRepository
     */
    public function getRepository()
    {
        return $this->getEntityManager()->getRepository($this->getEntityName());
    }

    public function resetErroneousJobs($workerName = null, $method = null)
    {
        $archiveEntityName = $this->getArchiveEntityName();
        $entityManager = $this->getEntityManager();
        $qb = $entityManager
            ->createQueryBuilder()
            ->select('count(ja.id)')
            ->from($archiveEntityName, 'ja')
            ->where('ja.status = :status');

        if ($workerName) {
            $qb->andWhere('ja.workerName = :workerName')
                ->setParameter(':workerName', $workerName);
        }

        if ($method) {
            $qb->andWhere('ja.method = :method')
                ->setParameter(':method', $workerName);
        }

        $count = $qb->setParameter(':status', Job::STATUS_ERROR)
            ->getQuery()->getSingleScalarResult();

        if (!$count) {
            return 0;
        }

        $countProcessed = 0;
        for ($i = 0; $i < $count; $i += 100) {
            $repository = $entityManager->getRepository($archiveEntityName);
            $criterion = ['status' => Job::STATUS_ERROR];
            if ($workerName) {
                $criterion['workerName'] = $workerName;
            }
            if ($method) {
                $criterion['method'] = $method;
            }
            $results = $repository->findBy($criterion, null, 100);
            $entityManager->beginTransaction();
            foreach ($results as $jobArchive) {
                $className = $this->getEntityName();
                $job = new $className();
                Util::copy($jobArchive, $job);
                $job->setStatus(Job::STATUS_NEW);
                $job->setLocked(null);
                $job->setLockedAt(null);
                $job->setUpdatedAt(new \DateTime());
                $metadata = $entityManager->getClassMetadata($this->getEntityName());
                $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_NONE);
                $metadata->setIdGenerator(new AssignedGenerator());
                $entityManager->remove($jobArchive);
                $entityManager->persist($job);
                ++$countProcessed;
            }
            $entityManager->commit();
            $entityManager->flush();
        }

        return $countProcessed;
    }

    public function pruneErroneousJobs($workerName = null, $method = null)
    {
        $qb = $this->getEntityManager()->createQueryBuilder()->delete($this->getArchiveEntityName(), 'j');
        $qb = $qb
            ->where('j.status = :status')
            ->setParameter(':status', Job::STATUS_ERROR);

        if ($workerName) {
            $qb->andWhere('j.workerName = :workerName')->setParameter(':workerName', $workerName);
        }

        if ($method) {
            $qb->andWhere('j.method = :method')->setParameter(':method', $method);
        }

        $query = $qb->getQuery();

        return $query->execute();
    }

    public function pruneExpiredJobs()
    {
        $qb = $this->getEntityManager()->createQueryBuilder()->delete($this->getEntityName(), 'j');
        $qb = $qb
            ->where('j.expiresAt <= :expiresAt')
            ->setParameter(':expiresAt', new \DateTime());

        $query = $qb->getQuery();

        return $query->execute();
    }

    /**
     * Removes archived jobs older than $olderThan.
     *
     * @param \DateTime $olderThan
     */
    public function pruneArchivedJobs(\DateTime $olderThan)
    {
        $qb = $this->getEntityManager()->createQueryBuilder()->delete($this->getArchiveEntityName(), 'j');
        $qb = $qb
            ->where('j.updatedAt < :updatedAt')
            ->setParameter(':updatedAt', $olderThan);

        $query = $qb->getQuery();

        return $query->execute();
    }

    public function getJobCount($workerName = null, $method = null)
    {
        $qb = $this->getRepository()->createQueryBuilder('j')->select('count(j)')->from($this->getEntityName(), 'j');

        $where = 'where';
        if ($workerName) {
            if ($method) {
                $qb->where($qb->expr()->andX($qb->expr()->eq('j.workerName', ':workerName'),
                                             $qb->expr()->eq('j.method', ':method')))
                    ->setParameter(':method', $method);
            } else {
                $qb->where('j.workerName = :workerName');
            }
            $qb->setParameter(':workerName', $workerName);
            $where = 'andWhere';
        } elseif ($method) {
            $qb->where('j.method = :method')->setParameter(':method', $method);
            $where = 'andWhere';
        }

        $dateTime = new \DateTime();
        // Filter
        $qb
            ->$where($qb->expr()->orX($qb->expr()->isNull('j.whenAt'),
                                        $qb->expr()->lte('j.whenAt', ':whenAt')))
            ->andWhere($qb->expr()->orX($qb->expr()->isNull('j.expiresAt'),
                $qb->expr()->gt('j.expiresAt', ':expiresAt')))
            ->andWhere('j.locked is NULL')
            ->setParameter(':whenAt', $dateTime)
            ->setParameter(':expiresAt', $dateTime);

        $query = $qb->getQuery();

        return $query->getSingleScalarResult();
    }

    /**
     * Get Jobs statuses.
     */
    public function getStatus()
    {
        $result = [];
        $this->getStatusByEntityName($this->getEntityName(), $result);
        $this->getStatusByEntityName($this->getArchiveEntityName(), $result);

        $finalResult = [];
        foreach ($result as $key => $item) {
            ksort($item);
            $finalResult[$key] = $item;
        }
        return $finalResult;
    }

    protected function getStatusByEntityName($entityName, array &$result) {
        $result1 = $this->getEntityManager()->getRepository($entityName)->createQueryBuilder('j')->select('j.workerName, j.status, count(j) as c')
            ->where('j.status = :status1')
            ->orWhere('j.status = :status2')
            ->orWhere('j.status = :status3')
            ->setParameter(':status1', Job::STATUS_ERROR)
            ->setParameter(':status2', Job::STATUS_NEW)
            ->setParameter(':status3', Job::STATUS_SUCCESS)
            ->groupBy('j.workerName, j.status')->getQuery()->getArrayResult();

        foreach ($result1 as $item) {
            if (isset($result[$item['workerName']][$item['status']])) {
                $result[$item['workerName']][$item['status']] += $item['c'];
            }
            else {
                $result[$item['workerName']][$item['status']] = $item['c'];
            }
        }
    }

    /**
     * Get the next job to run (can be filtered by workername and method name).
     *
     * @param string $workerName
     * @param string $methodName
     * @param bool   $prioritize
     *
     * @return \Dtc\QueueBundle\Model\Job|null
     */
    public function getJob($workerName = null, $methodName = null, $prioritize = true)
    {
        $uniqid = uniqid(gethostname().'-'.getmypid(), true);
        $hash = hash('sha256', $uniqid);

        $entityManager = $this->getEntityManager();
        $entityManager->beginTransaction();
        $repositoryManager = $this->getRepository();
        $qb = $repositoryManager->createQueryBuilder('j');
        $dateTime = new \DateTime();
        $qb
            ->select('j')
            ->where('j.status = :status')->setParameter(':status', Job::STATUS_NEW)
            ->andWhere('j.locked is NULL')
            ->andWhere($qb->expr()->orX($qb->expr()->isNull('j.whenAt'),
                        $qb->expr()->lte('j.whenAt', ':whenAt')))
            ->andWhere($qb->expr()->orX($qb->expr()->isNull('j.expiresAt'),
                        $qb->expr()->gt('j.expiresAt', ':expiresAt')))
            ->setParameter(':whenAt', $dateTime)
            ->setParameter(':expiresAt', $dateTime);

        if ($workerName) {
            $qb->andWhere('j.workerName = :workerName')
                ->setParameter(':workerName', $workerName);
        }

        if ($methodName) {
            $qb->andWhere('j.method = :method')
                ->setParameter(':method', $methodName);
        }

        if ($prioritize) {
            $qb->add('orderBy', 'j.priority ASC, j.whenAt ASC');
        } else {
            $qb->orderBy('j.whenAt', 'ASC');
        }
        $qb->setMaxResults(1);

        /** @var QueryBuilder $qb */
        $query = $qb->getQuery();
        $query->setLockMode(LockMode::PESSIMISTIC_WRITE);
        $jobs = $query->getResult();

        if ($jobs) {
            /** @var Job $job */
            $job = $jobs[0];
            if (!$job) {
                throw new \Exception("No job found for $hash, even though last result was count ".count($jobs));
            }
            $job->setLocked(true);
            $job->setLockedAt(new \DateTime());
            $entityManager->commit();
            $entityManager->flush();

            return $job;
        }

        return null;
    }

    public function deleteJob(\Dtc\QueueBundle\Model\Job $job)
    {
        if (!$job instanceof Job) {
            throw new \Exception("Job must be instance of Dtc\\QueuBundle\\Entity\\Job, instead it's ".get_class($job));
        }
        $this->entityManager->remove($job);
        $this->entityManager->flush();
    }

    public function saveHistory(\Dtc\QueueBundle\Model\Job $job)
    {
        $className = $this->getArchiveEntityName();
        $jobArchive = new $className();
        Util::copy($job, $jobArchive);

        $metadata = $this->entityManager->getClassMetadata($this->getArchiveEntityName());
        $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_NONE);
        $metadata->setIdGenerator(new AssignedGenerator());
        $this->entityManager->persist($jobArchive);
        $this->entityManager->remove($job);
        $this->entityManager->flush();
    }

    public function save(\Dtc\QueueBundle\Model\Job $job)
    {
        // Generate crc hash for the job
        $hashValues = array($job->getClassName(), $job->getMethod(), $job->getWorkerName(), $job->getArgs());
        $crcHash = hash('sha256', serialize($hashValues));
        $job->setCrcHash($crcHash);
        $entityManager = $this->getEntityManager();

        if ($job->getBatch() === true) {
            // See if similar job that hasn't run exists
            $criteria = array('crcHash' => $crcHash, 'status' => Job::STATUS_NEW);
            $oldJob = $this->getRepository()->findOneBy($criteria);

            if ($oldJob) {
                // Old job exists - just override fields Set higher priority
                $oldJob->setPriority(max($job->getPriority(), $oldJob->getPriority()));
                $oldJob->setWhenAt(min($job->getWhenAt(), $oldJob->getWhenAt()));
                $oldJob->setBatch(true);
                $oldJob->setUpdatedAt(new \DateTime());
                $entityManager->flush();

                return $oldJob;
            }
        }

        // Just save a new job
        $entityManager->persist($job);
        $entityManager->flush();

        return $job;
    }
}
