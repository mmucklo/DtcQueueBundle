<?php

namespace Dtc\QueueBundle\Doctrine;

use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Doctrine\ODM\MongoDB\Mapping\ClassMetadata;
use Doctrine\ORM\Id\AssignedGenerator;
use Dtc\QueueBundle\Document\Job;
use Dtc\QueueBundle\Model\JobManagerInterface;
use Dtc\QueueBundle\ODM\JobManager;
use Dtc\QueueBundle\Util\Util;

class RemoveListener
{
    /** @var JobManagerInterface */
    private $jobManager;

    public function setJobManager(JobManagerInterface $jobManager)
    {
        $this->jobManager = $jobManager;
    }

    public function preRemove(LifecycleEventArgs $eventArgs)
    {
        echo "preRemove\n\n";
        $object = $eventArgs->getObject();
        $objectManager = $eventArgs->getObjectManager();
        $jobManager = $this->jobManager;

        if ($object instanceof Job) {
            /** @var JobManager $jobManager */
            $className = $jobManager->getArchiveDocumentName();
            /** @var ClassMetadata $metadata */
            $metadata = $objectManager->getClassMetadata($className);
            $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_NONE);
        }
        else {
            /** @var \Dtc\QueueBundle\ORM\JobManager $jobManager */
            $className = $jobManager->getArchiveEntityName();
            /** @var \Doctrine\ORM\Mapping\ClassMetadata $metadata */
            $metadata = $objectManager->getClassMetadata($className);
            $metadata->setIdGeneratorType(\Doctrine\ORM\Mapping\ClassMetadata::GENERATOR_TYPE_NONE);
            $metadata->setIdGenerator(new AssignedGenerator());
        }

        $jobArchive = new $className();
        Util::copy($object, $jobArchive);
        $objectManager->persist($jobArchive);
    }
}
