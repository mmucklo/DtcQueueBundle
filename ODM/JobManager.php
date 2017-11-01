<?php

namespace Dtc\QueueBundle\ODM;

use Doctrine\MongoDB\Query\Builder;
use Dtc\QueueBundle\Doctrine\BaseJobManager;
use Doctrine\ODM\MongoDB\DocumentManager;
use Dtc\QueueBundle\Document\Job;
use Dtc\QueueBundle\Model\BaseJob;
use Dtc\QueueBundle\Model\RetryableJob;

class JobManager extends BaseJobManager
{
    use CommonTrait;

    public function countJobsByStatus($objectName, $status, $workerName = null, $method = null)
    {
        /** @var DocumentManager $objectManager */
        $objectManager = $this->getObjectManager();
        $qb = $objectManager->createQueryBuilder($objectName);
        $qb
            ->find()
            ->field('status')->equals($status);

        $this->addWorkerNameCriterion($qb, $workerName, $method);
        $query = $qb->getQuery();

        return $query->count();
    }

    /**
     * @param string|null $workerName
     * @param string|null $method
     */
    public function pruneErroneousJobs($workerName = null, $method = null)
    {
        /** @var DocumentManager $objectManager */
        $objectManager = $this->getObjectManager();
        $qb = $objectManager->createQueryBuilder($this->getArchiveObjectName());
        $qb = $qb->remove();
        $qb->field('status')->equals(BaseJob::STATUS_ERROR);
        $this->addWorkerNameCriterion($qb, $workerName, $method);

        $query = $qb->getQuery();
        $result = $query->execute();
        if (isset($result['n'])) {
            return $result['n'];
        }

        return 0;
    }

    /**
     * @param Builder     $builder
     * @param string|null $workerName
     * @param string|null $method
     */
    protected function addWorkerNameCriterion(Builder $builder, $workerName = null, $method = null)
    {
        if (null !== $workerName) {
            $builder->field('workerName')->equals($workerName);
        }

        if (null !== $method) {
            $builder->field('method')->equals($method);
        }
    }

    /**
     * @param null $workerName
     * @param null $method
     *
     * @return int
     */
    protected function updateExpired($workerName = null, $method = null)
    {
        /** @var DocumentManager $objectManager */
        $objectManager = $this->getObjectManager();
        $qb = $objectManager->createQueryBuilder($this->getObjectName());
        $qb = $qb->updateMany();
        $qb->field('expiresAt')->lte(new \DateTime());
        $qb->field('status')->equals(BaseJob::STATUS_NEW);
        $this->addWorkerNameCriterion($qb, $workerName, $method);
        $qb->field('status')->set(\Dtc\QueueBundle\Model\Job::STATUS_EXPIRED);
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
     *                             return int
     */
    public function pruneArchivedJobs(\DateTime $olderThan)
    {
        /** @var DocumentManager $documentManager */
        $documentManager = $this->getObjectManager();

        return $this->removeOlderThan($documentManager, $this->getArchiveObjectName(), 'updatedAt', $olderThan);
    }

    public function getJobCount($workerName = null, $method = null)
    {
        /** @var DocumentManager $objectManager */
        $objectManager = $this->getObjectManager();
        $qb = $objectManager->createQueryBuilder($this->getObjectName());
        $qb
            ->find();

        $this->addWorkerNameCriterion($qb, $workerName, $method);

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
     *
     * @return array
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
            BaseJob::STATUS_ERROR => 0,
            BaseJob::STATUS_NEW => 0,
            RetryableJob::STATUS_EXPIRED => 0,
            RetryableJob::STATUS_MAX_ERROR => 0,
            RetryableJob::STATUS_MAX_RETRIES => 0,
            RetryableJob::STATUS_MAX_STALLED => 0,
            BaseJob::STATUS_RUNNING => 0,
            BaseJob::STATUS_SUCCESS => 0,
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

        $this->addWorkerNameCriterion($qb, $workerName, $methodName);
        if ($prioritize) {
            $qb->sort('priority', 'desc');
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
            ->field('status')->equals(BaseJob::STATUS_NEW)
            ->field('locked')->equals(null);

        // Update
        $qb
            ->field('lockedAt')->set($date) // Set started
            ->field('locked')->set(true)
            ->field('status')->set(BaseJob::STATUS_RUNNING)
            ->field('runId')->set($runId);

        $query = $qb->getQuery();

        $job = $query->execute();

        return $job;
    }
}
