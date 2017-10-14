<?php

namespace Dtc\QueueBundle\Doctrine;

use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Mapping\ClassMetadata;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Id\AssignedGenerator;
use Dtc\QueueBundle\Model\BaseJob;
use Dtc\QueueBundle\Model\Run;
use Dtc\QueueBundle\ODM\JobManager;
use Dtc\QueueBundle\Util\Util;
use Symfony\Component\DependencyInjection\ContainerInterface;

class RemoveListener
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
        /** @var BaseJobManager $jobManager */
        $jobManager = $this->container->get('dtc_queue.job_manager');

        $runArchiveClass = $jobManager->getRunArchiveClass();
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

            $jobArchive = new $className();
            Util::copy($object, $jobArchive);
            if (BaseJob::STATUS_RUNNING === $jobArchive->getStatus()) {
                $jobArchive->setStatus(BaseJob::STATUS_ERROR);
                $jobArchive->setMessage('stalled');
            }
            $jobArchive->setUpdatedAt(new \DateTime());
            $objectManager->persist($jobArchive);
        }
    }
}
