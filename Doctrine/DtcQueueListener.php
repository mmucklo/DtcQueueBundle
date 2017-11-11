<?php

namespace Dtc\QueueBundle\Doctrine;

use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Mapping\ClassMetadata;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Id\AssignedGenerator;
use Dtc\QueueBundle\Model\RetryableJob;
use Dtc\QueueBundle\Model\Run;
use Dtc\QueueBundle\ODM\JobManager;
use Dtc\QueueBundle\Util\Util;

class DtcQueueListener
{
    private $jobArchiveClass;
    private $runArchiveClass;
    private $objectManager;

    public function __construct($jobArchiveClass, $runArchiveClass)
    {
        $this->jobArchiveClass = $jobArchiveClass;
        $this->runArchiveClass = $runArchiveClass;
    }

    public function preRemove(LifecycleEventArgs $eventArgs)
    {
        $this->objectManager = $eventArgs->getObjectManager();
        $object = $eventArgs->getObject();

        if ($object instanceof \Dtc\QueueBundle\Model\Job) {
            $this->processJob($object);
        } elseif ($object instanceof Run) {
            $this->processRun($object);
        }
    }

    public function processRun(Run $object)
    {
        $runArchiveClass = $this->runArchiveClass;
        if ($object instanceof $runArchiveClass) {
            return;
        }

        $runArchive = new $runArchiveClass();
        Util::copy($object, $runArchive);
        $objectManager = $this->objectManager;
        $metadata = $objectManager->getClassMetadata($runArchiveClass);
        $this->adjustIdGenerator($metadata);
        $objectManager->persist($runArchive);
    }

    /**
     * @param $metadata
     */
    protected function adjustIdGenerator($metadata)
    {
        $objectManager = $this->objectManager;
        if ($objectManager instanceof EntityManager) {
            /* @var \Doctrine\ORM\Mapping\ClassMetadata $metadata */
            $metadata->setIdGeneratorType(\Doctrine\ORM\Mapping\ClassMetadata::GENERATOR_TYPE_NONE);
            $metadata->setIdGenerator(new AssignedGenerator());
        } elseif ($objectManager instanceof DocumentManager) {
            /* @var ClassMetadata $metadata */
            $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_NONE);
        }
    }

    public function processJob(\Dtc\QueueBundle\Model\Job $object)
    {
        if ($object instanceof \Dtc\QueueBundle\Document\Job ||
            $object instanceof \Dtc\QueueBundle\Entity\Job) {
            /** @var JobManager $jobManager */
            $archiveObjectName = $this->jobArchiveClass;
            $objectManager = $this->objectManager;
            $repository = $objectManager->getRepository($archiveObjectName);
            $className = $repository->getClassName();
            $metadata = $objectManager->getClassMetadata($className);
            $this->adjustIdGenerator($metadata);

            /** @var RetryableJob $jobArchive */
            $jobArchive = new $className();
            Util::copy($object, $jobArchive);
            $jobArchive->setUpdatedAt(new \DateTime());
            $objectManager->persist($jobArchive);
        }
    }

    public function preUpdate(LifecycleEventArgs $eventArgs)
    {
        $object = $eventArgs->getObject();
        if ($object instanceof \Dtc\QueueBundle\Model\RetryableJob) {
            $dateTime = new \DateTime();
            $object->setUpdatedAt($dateTime);
        }
    }

    public function prePersist(LifecycleEventArgs $eventArgs)
    {
        $object = $eventArgs->getObject();

        if ($object instanceof \Dtc\QueueBundle\Model\RetryableJob) {
            $dateTime = new \DateTime();
            if (!$object->getCreatedAt()) {
                $object->setCreatedAt($dateTime);
            }
            $object->setUpdatedAt($dateTime);
        }
    }
}
