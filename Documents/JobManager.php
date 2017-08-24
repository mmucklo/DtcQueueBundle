<?php

namespace Dtc\QueueBundle\Documents;

use Dtc\QueueBundle\Model\JobManagerInterface;
use Doctrine\ODM\MongoDB\DocumentRepository;
use Doctrine\ODM\MongoDB\DocumentManager;

class JobManager implements JobManagerInterface
{
    protected $documentManager;
    protected $documentName;

    public function __construct(DocumentManager $documentManager, $documentName)
    {
        $this->documentManager = $documentManager;
        $this->documentName = $documentName;
    }

    /**
     * @return DocumentManager
     */
    public function getDocumentManager()
    {
        return $this->documentManager;
    }

    /**
     * @return string
     */
    public function getDocumentName()
    {
        return $this->documentName;
    }

    /**
     * @return DocumentRepository
     */
    public function getRepository()
    {
        return $this->getDocumentManager()->getRepository($this->getDocumentName());
    }

    public function resetErroneousJobs($workerName = null, $method = null)
    {
        $qb = $this->getDocumentManager()->createQueryBuilder($this->getDocumentName());
        $qb
            ->update()
            ->multiple(true)
            ->field('status')->equals(Job::STATUS_ERROR)
            ->field('locked')->set(null)
            ->field('status')->set(Job::STATUS_NEW);

        if ($workerName) {
            $qb->field('workerName')->equals($workerName);
        }

        if ($method) {
            $qb->field('method')->equals($method);
        }

        $query = $qb->getQuery();
        $query->execute();
    }

    public function pruneErroneousJobs($workerName = null, $method = null)
    {
        $qb = $this->getDocumentManager()->createQueryBuilder($this->getDocumentName());
        $qb
        ->remove()
        ->multiple(true)
        ->field('status')->equals(Job::STATUS_ERROR);

        if ($workerName) {
            $qb->field('workerName')->equals($workerName);
        }

        if ($method) {
            $qb->field('method')->equals($method);
        }

        $query = $qb->getQuery();
        $query->execute();
    }

    public function getJobCount($workerName = null, $method = null)
    {
        $qb = $this->getDocumentManager()->createQueryBuilder($this->getDocumentName());
        $qb
        ->find();

        if ($workerName) {
            $qb->field('workerName')->equals($workerName);
        }

        if ($method) {
            $qb->field('method')->equals($method);
        }

        // Filter
        $qb
            ->addOr($qb->expr()->field('whenAt')->equals(null))
            ->addOr($qb->expr()->field('whenAt')->lte(new \DateTime()))
            ->field('locked')->equals(null);

        $query = $qb->getQuery();

        return $query->count(true);
    }

    /**
     * Get Status Jobs.
     */
    public function getStatus()
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

        $qb = $this->getDocumentManager()->createQueryBuilder($this->getDocumentName())
            ->map($mapFunc)
            ->reduce($reduceFunc);
        $query = $qb->getQuery();
        $results = $query->execute();

        $allStatus = array(
            Job::STATUS_ERROR => 0,
            Job::STATUS_NEW => 0,
            Job::STATUS_SUCCESS => 0,
        );

        $status = [];

        foreach ($results as $info) {
            $status[$info['_id']] = $info['value'] + $allStatus;
        }

        return $status;
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
    public function getJob($workerName = null, $methodName = null, $prioritize = true)
    {
        $qb = $this->getDocumentManager()->createQueryBuilder($this->getDocumentName());
        $qb
            ->findAndUpdate()
            ->returnNew();

        if ($workerName) {
            $qb->field('workerName')->equals($workerName);
        }

        if ($methodName) {
            $qb->field('method')->equals($methodName);
        }

        if ($prioritize) {
            $qb->sort('priority', 'asc');
        } else {
            $qb->sort('when', 'asc');
        }

        // Filter
        $qb
            ->addOr($qb->expr()->field('whenAt')->equals(null))
            ->addOr($qb->expr()->field('whenAt')->lte(new \DateTime()))
            ->field('status')->equals(Job::STATUS_NEW)
            ->field('locked')->equals(null);
        // Update
        $qb
            ->field('lockedAt')->set(new \DateTime())        // Set started
            ->field('locked')->set(true)
        ;

        //$arr = $qb->getQueryArray();
        $query = $qb->getQuery();

        //ve($query->debug());
        $job = $query->execute();

        return $job;
    }

    public function deleteJob(\Dtc\QueueBundle\Model\Job $job)
    {
        $this->getDocumentManager()->remove($job);
        $this->getDocumentManager()->flush();
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
                $this->getDocumentManager()->flush();

                return $oldJob;
            }
        }

        // Just save a new job
        $this->getDocumentManager()->persist($job);
        $this->getDocumentManager()->flush();

        return $job;
    }
}
