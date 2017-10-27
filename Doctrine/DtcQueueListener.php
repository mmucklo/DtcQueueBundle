<?php

namespace Dtc\QueueBundle\Doctrine;

use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Mapping\ClassMetadata;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Id\AssignedGenerator;
use Dtc\QueueBundle\Model\RetryableJob;
use Dtc\QueueBundle\Model\Run;
use Dtc\QueueBundle\ODM\JobManager;
use Dtc\QueueBundle\Util\Util;
use Symfony\Component\DependencyInjection\ContainerInterface;

class DtcQueueListener
{
    /** @var ContainerInterface */
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function preRemove(LifecycleEventArgs $eventArgs)
    {
        $object = $eventArgs->getObject();
        $objectManager = $eventArgs->getObjectManager();

        if ($object instanceof \Dtc\QueueBundle\Model\Job) {
            $this->processJob($object);
        } elseif ($object instanceof Run) {
            $this->processRun($object, $objectManager);
        }
    }

    public function processRun(Run $object, ObjectManager $objectManager)
    {
        /** @var BaseRunManager $runManager */
        $runManager = $this->container->get('dtc_queue.run_manager');

        $runArchiveClass = $runManager->getRunArchiveClass();
        $runArchive = new $runArchiveClass();
        Util::copy($object, $runArchive);
        $metadata = $objectManager->getClassMetadata($runArchiveClass);
        if ($objectManager instanceof EntityManager) {
            /* @var \Doctrine\ORM\Mapping\ClassMetadata $metadata */
            $metadata->setIdGeneratorType(\Doctrine\ORM\Mapping\ClassMetadata::GENERATOR_TYPE_NONE);
            $metadata->setIdGenerator(new AssignedGenerator());
        } elseif ($objectManager instanceof DocumentManager) {
            /* @var ClassMetadata $metadata */
            $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_NONE);
        }
        $objectManager->persist($runArchive);
    }

    public function processJob(\Dtc\QueueBundle\Model\Job $object)
    {
        $jobManager = $this->container->get('dtc_queue.job_manager');

        if ($object instanceof \Dtc\QueueBundle\Document\Job ||
            $object instanceof \Dtc\QueueBundle\Entity\Job) {
            /** @var JobManager $jobManager */
            $archiveObjectName = $jobManager->getArchiveObjectName();
            $objectManager = $jobManager->getObjectManager();
            $repository = $objectManager->getRepository($archiveObjectName);
            $jobManager->stopIdGenerator($jobManager->getArchiveObjectName());
            $className = $repository->getClassName();

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
