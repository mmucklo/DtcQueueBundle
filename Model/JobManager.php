<?php
namespace Dtc\QueueBundle\Model;

use Doctrine\ODM\MongoDB\DocumentRepository;
use Doctrine\ODM\MongoDB\DocumentManager;

class JobManager
{
    protected $dm;
    protected $documentName;

    public function __construct(DocumentManager $dm, $documentName)
    {
        $this->dm = $dm;
        $this->documentName = $documentName;
    }

    /**
     * @return DocumentManager
     */
    public function getDocumentManager()
    {
        return $this->dm;
    }

    /**
     * @return DocumentRepository
     */
    public function getRepository()
    {
        return $this->dm->getRepository($this->documentName);
    }

    /**
     * Get the next job to run (can be filtered by workername and method name)
     *
     * @param string $workerName
     * @param string $methodName
     * @param boolean $prioritize
     *
     * @return Dtc\QueueBundle\Model\Job
     */
    public function getJob($workerName = null, $methodName = null, $prioritize = true)
    {

        $qb = $this->dm->createQueryBuilder($this->documentName);
        $qb
            ->findAndUpdate()
            ->returnNew();

        if ($workerName) {
            $qb->field('workerName')->equals($workerName);
        }

        if ($methodName = null) {
            $qb->field('methodName')->equals($methodName);
        }

        if ($prioritize) {
            $qb->sort('priority', 'asc');
        }
        else {
            $qb->sort('when', 'asc');
        }

        // Filter
        $qb
            ->addOr($qb->expr()->field('when')->equals(null))
            ->addOr($qb->expr()->field('when')->lte(new \DateTime()))
            ->field('status')->equals(Job::STATUS_NEW)
            ->field('locked')->equals(null);

        // Update
        $qb
            ->field('lockedAt')->set(new \DateTime())        // Set started
            ->field('locked')->set(true)
        ;

        $arr = $qb->getQueryArray();
        $query = $qb->getQuery();

        //ve($query->debug());
        $job = $query->execute();
        return $job;
    }

    public function deleteJob(Job $job) {
        $this->dm->remove($job);
        $this->dm->flush();
    }

    public function save(Job $job)
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

            if ($oldJob)
            {
                // Old job exists - just override fields Set higher priority
                $oldJob->setPriority(max($job->getPriority(), $oldJob->getPriority()));
                $oldJob->setWhen(min($job->getWhen(), $oldJob->getWhen()));
                $oldJob->setBatch(true);
                $oldJob->setUpdatedAt(new \DateTime());

                $this->dm->flush();
                return $oldJob;
            }
        }

        // Just save a new job
        $this->dm->persist($job);
        $this->dm->flush();

        return $job;
    }
}
