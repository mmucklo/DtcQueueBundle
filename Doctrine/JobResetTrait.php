<?php

namespace Dtc\QueueBundle\Doctrine;

use Doctrine\Common\Persistence\ObjectManager;
use Dtc\QueueBundle\Model\Job;
use Dtc\QueueBundle\Util\Util;

trait JobResetTrait {
    private function resetJobsByCriterion(ObjectManager $objectManager,
                                          $jobDef,
                                          $jobArchiveDef,
                                          $idGenFunc,
                                          $criterion,
                                          $limit,
                                          $offset)
    {
        $jobRepository = $objectManager->getRepository($jobDef);
        $jobArchiveRepository = $objectManager->getRepository($jobArchiveDef);
        $className = $jobRepository->getClassName();
        $metadata = $objectManager->getClassMetadata($className);
        $idGenFunc($metadata);
        $identifierData = $metadata->getIdentifier();
        $idColumn = isset($identifierData[0]) ? $identifierData : 'id';
        $results = $jobArchiveRepository->findBy($criterion, [$idColumn => 'ASC'], $limit, $offset);
        $countProcessed = 0;

        foreach ($results as $jobArchive) {
            /** @var Job $job */
            $job = new $className();
            Util::copy($jobArchive, $job);
            $job->setStatus(Job::STATUS_NEW);
            $job->setLocked(null);
            $job->setLockedAt(null);
            $job->setUpdatedAt(new \DateTime());

            $objectManager->persist($job);
            $objectManager->remove($jobArchive);
            ++$countProcessed;
        }

        return $countProcessed;
    }

}