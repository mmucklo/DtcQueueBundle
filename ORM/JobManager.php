<?php

namespace Dtc\QueueBundle\ORM;

use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Dtc\QueueBundle\Entity\Job;
use Dtc\QueueBundle\Model\JobManagerInterface;

class JobManager implements JobManagerInterface
{
    protected $entityManager;
    protected $entityName;

    public function __construct(EntityManager $entityManager, $entityName)
    {
        $this->entityManager = $entityManager;
        $this->entityName = $entityName;
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
     * @return EntityRepository
     */
    public function getRepository()
    {
        return $this->getEntityManager()->getRepository($this->getEntityName());
    }

    public function resetErroneousJobs($workerName = null, $method = null)
    {
        $qb = $this->getEntityManager()->createQueryBuilder()->update($this->entityName, 'j');
        $qb = $qb
            ->set('j.locked', $qb->expr()->literal(null))
            ->set('j.status', $qb->expr()->literal(Job::STATUS_NEW))
            ->where('j.status = :status')
            ->setParameter(':status', Job::STATUS_ERROR);

        if ($workerName) {
            $qb = $qb->andWhere('j.workerName = :workerName')->setParameter(':workerName', $workerName);
        }

        if ($method) {
            $qb = $qb->andWhere('j.method = :method')->setParameter(':method', $method);
        }

        $query = $qb->getQuery();

        return $query->execute();
    }

    public function pruneErroneousJobs($workerName = null, $method = null)
    {
        $qb = $this->getEntityManager()->createQueryBuilder()->delete($this->entityName, 'j');
        $qb = $qb
            ->where('j.status = :status')
            ->setParameter(':status', Job::STATUS_ERROR);

        if ($workerName) {
            $qb = $qb->andWhere('j.workerName = :workerName')->setParameter(':workerName', $workerName);
        }

        if ($method) {
            $qb = $qb->andWhere('j.method = :method')->setParameter(':method', $method);
        }

        $query = $qb->getQuery();

        return $query->execute();
    }

    public function getJobCount($workerName = null, $method = null)
    {
        $qb = $this->getRepository()->createQueryBuilder('j')->select('count(j)')->from($this->getEntityName(), 'j');

        $where = 'where';
        if ($workerName) {
            if ($method) {
                $qb = $qb->where($qb->expr()->andX($qb->expr()->eq('j.workerName', ':workerName'),
                                             $qb->expr()->eq('j.method', ':method')))
                    ->setParameter(':method', $method);
            } else {
                $qb = $qb->where('j.workerName = :workerName');
            }
            $qb = $qb->setParameter(':workerName', $workerName);
            $where = 'andWhere';
        } elseif ($method) {
            $qb = $qb->where('j.method = :method')->setParameter(':method', $method);
            $where = 'andWhere';
        }

        $dateTime = new \DateTime();
        // Filter
        $qb = $qb
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
        return $this->getRepository()->createQueryBuilder('j')->select('j.status, count(j)')
            ->where('j.status = :status1')
            ->orWhere('j.status = :status2')
            ->orWhere('j.status = :status3')
            ->setParameter(':status1', Job::STATUS_ERROR)
            ->setParameter(':status2', Job::STATUS_NEW)
            ->setParameter(':status3', Job::STATUS_SUCCESS)
            ->groupBy('j.status')->getQuery()->getArrayResult();
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
        $qb = $qb
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
            $qb = $qb->andWhere('j.workerName = :workerName')
                ->setParameter(':workerName', $workerName);
        }

        if ($methodName) {
            $qb = $qb->andWhere('j.method = :method')
                ->setParameter(':method', $methodName);
        }

        if ($prioritize) {
            $qb = $qb->add('orderBy', 'j.priority ASC, j.whenAt ASC');
        } else {
            $qb = $qb->orderBy('j.whenAt', 'ASC');
        }
        $qb = $qb->setMaxResults(1);

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
        $this->save($job);
    }

    public function save(\Dtc\QueueBundle\Model\Job $job)
    {
        // Todo: Serialize args

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
