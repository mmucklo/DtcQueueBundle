<?php

namespace Dtc\QueueBundle\ORM;

use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Id\AssignedGenerator;
use Doctrine\ORM\Mapping\ClassMetadata;

trait CommonTrait
{
    /**
     * @param string        $objectName
     * @param string        $field
     * @param \DateTime     $olderThan
     *
     * @return int
     */
    protected function removeOlderThan(ObjectManager $objectManager, $objectName, $field, \DateTime $olderThan)
    {
        /** @var EntityManager $objectManager */
        $qb = $objectManager->createQueryBuilder()->delete($objectName, 'j');
        $qb = $qb
            ->where('j.'.$field.' < :'.$field)
            ->setParameter(':'.$field, $olderThan);

        $query = $qb->getQuery();

        return $query->execute();
    }

    public function stopIdGenerator($objectName)
    {
        /** @var EntityManager $objectManager */
        $objectManager = $this->getObjectManager();
        $repository = $objectManager->getRepository($objectName);
        /** @var ClassMetadata $metadata */
        $metadata = $objectManager->getClassMetadata($repository->getClassName());
        $this->formerIdGenerators[$objectName]['generator'] = $metadata->idGenerator;
        $this->formerIdGenerators[$objectName]['type'] = $metadata->generatorType;
        $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_NONE);
        $metadata->setIdGenerator(new AssignedGenerator());
    }

    public function restoreIdGenerator($objectName)
    {
        $objectManager = $this->getObjectManager();
        $repository = $objectManager->getRepository($objectName);
        /** @var ClassMetadata $metadata */
        $metadata = $objectManager->getClassMetadata($repository->getClassName());
        $generator = $this->formerIdGenerators[$objectName]['generator'];
        $type = $this->formerIdGenerators[$objectName]['type'];
        $metadata->setIdGeneratorType($type);
        $metadata->setIdGenerator($generator);
    }
}
