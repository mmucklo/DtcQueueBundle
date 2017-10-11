<?php

namespace Dtc\QueueBundle\Doctrine;

use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Mapping\ClassMetadata;
use Doctrine\ORM\EntityManager;
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
        $object = $eventArgs->getObject();
        $objectManager = $eventArgs->getObjectManager();

        if ($object instanceof Job) {
            /* @var DocumentManager $objectManager */
            $this->removeDocument($object, $objectManager);
        }
    }

    private function removeDocument(Job $object, DocumentManager $documentManager)
    {
        /** @var JobManager $jobManager */
        $jobManager = $this->jobManager;
        $className = $jobManager->getArchiveDocumentName();
        $jobArchive = new $className();
        Util::copy($object, $jobArchive);

        $metadata = $documentManager->getClassMetadata($className);
        $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_NONE);
        $documentManager->persist($jobArchive);
    }

    private function removeEntity(\Dtc\QueueBundle\Entity\Job $object, EntityManager $entityManager)
    {
        /** @var \Dtc\QueueBundle\ORM\JobManager $jobManager */
        $jobManager = $this->jobManager;
        $className = $jobManager->getArchiveEntityName();
        $jobArchive = new $className();
        Util::copy($object, $jobArchive);

        $metadata = $entityManager->getClassMetadata($className);
        $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_NONE);
        $metadata->setIdGenerator(new AssignedGenerator());
        $entityManager->persist($jobArchive);
    }
}
