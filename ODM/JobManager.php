<?php

namespace Dtc\QueueBundle\ODM;

use Doctrine\ODM\MongoDB\Mapping\ClassMetadata;
use Dtc\QueueBundle\Doctrine\BaseJobManager;
use Doctrine\ODM\MongoDB\DocumentManager;
use Dtc\QueueBundle\Document\Job;

class JobManager extends BaseJobManager
{
    public function countJobsByStatus($objectName, $status, $workerName = null, $method = null)
    {
        /** @var DocumentManager $objectManager */
        $objectManager = $this->getObjectManager();
        $qb = $objectManager->createQueryBuilder($objectName);
        $qb
            ->find()
            ->field('status')->equals($status);

        if (null !== $workerName) {
            $qb->field('workerName')->equals($workerName);
        }

        if (null !== $method) {
            $qb->field('method')->equals($method);
        }

        $query = $qb->getQuery();

        return $query->count();
    }

    /**
     * @param string $objectName
     */
    public function stopIdGenerator($objectName)
    {
        $objectManager = $this->getObjectManager();
        $repository = $objectManager->getRepository($objectName);
        /** @var ClassMetadata $metadata */
        $metadata = $this->getObjectManager()->getClassMetadata($repository->getClassName());
        $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_NONE);
    }

    /**
     * @param string $workerName
     * @param string $method
     */
    public function pruneErroneousJobs($workerName = null, $method = null)
    {
        /** @var DocumentManager $objectManager */
        $objectManager = $this->getObjectManager();
        $qb = $objectManager->createQueryBuilder($this->getArchiveObjectName());
        $qb
            ->remove()
            ->field('status')->equals(Job::STATUS_ERROR);

        if (null !== $workerName) {
            $qb->field('workerName')->equals($workerName);
        }

        if (null !== $method) {
            $qb->field('method')->equals($method);
        }
        $query = $qb->getQuery();
        $result = $query->execute();
        if (isset($result['n'])) {
            return $result['n'];
        }

        return 0;
    }

    /**
     * Prunes expired jobs.
     *
     * @param string $workerName
     * @param string $method
     */
    public function pruneExpiredJobs($workerName = null, $method = null)
    {
        /** @var DocumentManager $objectManager */
        $objectManager = $this->getObjectManager();
        $qb = $objectManager->createQueryBuilder($this->getObjectName());
        $qb
            ->remove()
            ->field('expiresAt')->lte(new \DateTime());

        if (null !== $workerName) {
            $qb->field('workerName')->equals($workerName);
        }

        if (null !== $method) {
            $qb->field('method')->equals($method);
        }

        $query = $qb->getQuery();
        $result = $query->execute();
        if (isset($result['n'])) {
            return $result['n'];
        }

        return 0;
    }

    /**
     * Removes archived jobs older than $olderThan.
     *
     * @param \DateTime $olderThan
     */
    public function pruneArchivedJobs(\DateTime $olderThan)
    {
        /** @var DocumentManager $objectManager */
        $objectManager = $this->getObjectManager();
        $qb = $objectManager->createQueryBuilder($this->getArchiveObjectName());
        $qb
            ->remove()
            ->field('updatedAt')->lt($olderThan);

        $query = $qb->getQuery();
        $result = $query->execute();
        if (isset($result['n'])) {
            return $result['n'];
        }

        return 0;
    }

    public function getJobCount($workerName = null, $method = null)
    {
        /** @var DocumentManager $objectManager */
        $objectManager = $this->getObjectManager();
        $qb = $objectManager->createQueryBuilder($this->getObjectName());
        $qb
            ->find();

        if (null !== $workerName) {
            $qb->field('workerName')->equals($workerName);
        }

        if (null !== $method) {
            $qb->field('method')->equals($method);
        }

        // Filter
        $date = new \DateTime();
        $qb
            ->addAnd(
                $qb->expr()->addOr($qb->expr()->field('expiresAt')->equals(null), $qb->expr()->field('expiresAt')->gt($date))
            )
            ->field('locked')->equals(null);

        $query = $qb->getQuery();

        return $query->count(true);
    }

    /**
     * Get Status Jobs.
     *
     * @param string $documentName
     */
    protected function getStatusByDocument($documentName)
    {
        // Run a map reduce function get worker and status break down
        $mapFunc = "function() {
            var result = {};
            result[this.status] = 1;
            var key = this.worker_name + '->' + this.method + '()';
            emit(key, result);
        }";
        $reduceFunc = 'function(k, vals) {
            var result = {};
            for (var index in vals) {
                var val =  vals[index];
                for (var i in val) {
                    if (result.hasOwnProperty(i)) {
                        result[i] += val[i];
                    }
                    else {
                        result[i] = val[i];
                    }
                }
            }
            return result;
        }';
        /** @var DocumentManager $objectManager */
        $objectManager = $this->getObjectManager();
        $qb = $objectManager->createQueryBuilder($documentName);
        $qb->map($mapFunc)
            ->reduce($reduceFunc);
        $query = $qb->getQuery();
        $results = $query->execute();

        $allStatus = array(
            Job::STATUS_ERROR => 0,
            Job::STATUS_NEW => 0,
            Job::STATUS_RUNNING => 0,
            Job::STATUS_SUCCESS => 0,
        );

        $status = [];

        foreach ($results as $info) {
            $status[$info['_id']] = $info['value'] + $allStatus;
        }

        return $status;
    }

    public function getStatus()
    {
        $result = $this->getStatusByDocument($this->getObjectName());
        $status2 = $this->getStatusByDocument($this->getArchiveObjectName());
        foreach ($status2 as $key => $value) {
            foreach ($value as $k => $v) {
                $result[$key][$k] += $v;
            }
        }

        $finalResult = [];
        foreach ($result as $key => $item) {
            ksort($item);
            $finalResult[$key] = $item;
        }

        return $finalResult;
    }

    /**
     * Get the next job to run (can be filtered by workername and method name).
     *
     * @param string $workerName
     * @param string $methodName
     * @param bool   $prioritize
     *
     * @return \Dtc\QueueBundle\Model\Job
     */
    public function getJob($workerName = null, $methodName = null, $prioritize = true, $runId = null)
    {
        /** @var DocumentManager $objectManager */
        $objectManager = $this->getObjectManager();
        $qb = $objectManager->createQueryBuilder($this->getObjectName());
        $qb
            ->findAndUpdate()
            ->returnNew();

        if (null !== $workerName) {
            $qb->field('workerName')->equals($workerName);
        }

        if (null !== $methodName) {
            $qb->field('method')->equals($methodName);
        }

        if ($prioritize) {
            $qb->sort('priority', 'asc');
        } else {
            $qb->sort('whenAt', 'asc');
        }

        // Filter
        $date = new \DateTime();
        $qb
            ->addAnd(
                $qb->expr()->addOr($qb->expr()->field('whenAt')->equals(null), $qb->expr()->field('whenAt')->lte($date)),
                $qb->expr()->addOr($qb->expr()->field('expiresAt')->equals(null), $qb->expr()->field('expiresAt')->gt($date))
            )
            ->field('status')->equals(Job::STATUS_NEW)
            ->field('locked')->equals(null);

        // Update
        $qb
            ->field('lockedAt')->set($date) // Set started
            ->field('locked')->set(true)
            ->field('status')->set(Job::STATUS_RUNNING)
            ->field('runId')->set($runId);

        //$arr = $qb->getQueryArray();
        $query = $qb->getQuery();

        //ve($query->debug());
        $job = $query->execute();

        return $job;
    }
}
