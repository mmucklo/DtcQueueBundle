<?php

namespace Dtc\QueueBundle\Doctrine;

use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Mapping\ClassMetadata;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Id\AssignedGenerator;
use Dtc\QueueBundle\Model\Run;
use Dtc\QueueBundle\Model\StallableJob;
use Dtc\QueueBundle\Util\Util;

class DtcQueueListener
{
    private $jobArchiveClass;
    private $runArchiveClass;
    private $entityManagerName;
    private $objectManager;
    private $registry;
    private $localeFix;

    public function __construct($jobArchiveClass, $runArchiveClass, $localeFix)
    {
        $this->jobArchiveClass = $jobArchiveClass;
        $this->runArchiveClass = $runArchiveClass;
        $this->localeFix = $localeFix;
    }

    public function setRegistry($registry)
    {
        $this->registry = $registry;
    }

    public function setEntityManagerName($entityManagerName)
    {
        $this->entityManagerName = $entityManagerName;
    }

    /**
     * @param \Doctrine\Persistence\Event\LifecycleEventArgs $eventArgs
     */
    public function preRemove($eventArgs)
    {
        $object = $eventArgs->getObject();
        $objectManager = $eventArgs->getObjectManager();
        $this->objectManager = $objectManager;

        if ($object instanceof \Dtc\QueueBundle\Model\Job) {
            $this->processJob($object);
        } elseif ($object instanceof Run) {
            $this->processRun($object);
        }
    }

    protected function getObjectManager()
    {
        if (!$this->registry) {
            return $this->objectManager;
        }

        if ($this->objectManager instanceof EntityManager && !$this->objectManager->isOpen()) {
            $this->objectManager = $this->registry->getManager($this->entityManagerName);
            if (!$this->objectManager->isOpen()) {
                $this->objectManager = $this->registry->resetManager($this->entityManagerName);
            }
        }

        return $this->objectManager;
    }

    public function processRun(Run $object)
    {
        $runArchiveClass = $this->runArchiveClass;
        if ($object instanceof $runArchiveClass) {
            return;
        }

        $objectManager = $this->getObjectManager();

        $repository = $objectManager->getRepository($runArchiveClass);
        $newArchive = false;

        if (!$runArchive = $repository->find($object->getId())) {
            $runArchive = new $runArchiveClass();
            $newArchive = true;
        }

        Util::copy($object, $runArchive);
        if ($newArchive) {
            $metadata = $objectManager->getClassMetadata($runArchiveClass);
            $this->adjustIdGenerator($metadata);
        }

        $objectManager->persist($runArchive);
    }

    /**
     * @param $metadata
     */
    protected function adjustIdGenerator($metadata)
    {
        $objectManager = $this->getObjectManager();
        if ($objectManager instanceof EntityManager && $metadata instanceof \Doctrine\ORM\Mapping\ClassMetadata) {
            $metadata->setIdGeneratorType(\Doctrine\ORM\Mapping\ClassMetadata::GENERATOR_TYPE_NONE);
            $metadata->setIdGenerator(new AssignedGenerator());
        } elseif ($objectManager instanceof DocumentManager && $metadata instanceof ClassMetadata) {
            $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_NONE);
        }
    }

    public function processJob(\Dtc\QueueBundle\Model\Job $object)
    {
        if ($object instanceof \Dtc\QueueBundle\Document\Job ||
            $object instanceof \Dtc\QueueBundle\Entity\Job) {
            /** @var JobManager $jobManager */
            $archiveObjectName = $this->jobArchiveClass;
            $objectManager = $this->getObjectManager();
            $repository = $objectManager->getRepository($archiveObjectName);
            $className = $repository->getClassName();

            /** @var StallableJob $jobArchive */
            $newArchive = false;
            if (!$jobArchive = $repository->find($object->getId())) {
                $jobArchive = new $className();
                $newArchive = true;
            }

            if ($newArchive) {
                $metadata = $objectManager->getClassMetadata($className);
                $this->adjustIdGenerator($metadata);
            }

            Util::copy($object, $jobArchive);
            $jobArchive->setUpdatedAt(Util::getMicrotimeDateTime());
            $objectManager->persist($jobArchive);
        }
    }

    /**
     * @param \Doctrine\Persistence\Event\LifecycleEventArgs $eventArgs
     */
    public function preUpdate($eventArgs)
    {
        $object = $eventArgs->getObject();
        if ($object instanceof \Dtc\QueueBundle\Model\StallableJob) {
            $dateTime = \Dtc\QueueBundle\Util\Util::getMicrotimeDateTime();
            $object->setUpdatedAt($dateTime);
        }
        if ($this->localeFix && method_exists($object, 'getElapsed') && $object->getElapsed()) {
            $localeinfo = localeconv();
            if (isset($localeinfo['decimal_point']) && $localeinfo['decimal_point'] && '.' !== $localeinfo['decimal_point']) {
                $object->setElapsed(number_format($object->getElapsed(), 16));
            }
        }
    }

    /**
     * @param \Doctrine\Persistence\Event\LifecycleEventArgs $eventArgs
     */
    public function prePersist($eventArgs)
    {
        $object = $eventArgs->getObject();

        if ($object instanceof \Dtc\QueueBundle\Model\StallableJob) {
            $dateTime = \Dtc\QueueBundle\Util\Util::getMicrotimeDateTime();
            if (!$object->getCreatedAt()) {
                $object->setCreatedAt($dateTime);
            }
            $object->setUpdatedAt($dateTime);
        }
        if ($this->localeFix && method_exists($object, 'getElapsed') && $object->getElapsed()) {
            $localeinfo = localeconv();
            if (isset($localeinfo['decimal_point']) && $localeinfo['decimal_point'] && '.' !== $localeinfo['decimal_point']) {
                $object->setElapsed(number_format($object->getElapsed(), 16));
            }
        }
    }
}
