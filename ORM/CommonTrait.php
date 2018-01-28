<?php

namespace Dtc\QueueBundle\ORM;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Id\AssignedGenerator;
use Doctrine\ORM\Mapping\ClassMetadata;

trait CommonTrait
{
    protected $formerIdGenerators;

    /** @var string */
    protected $entityManagerName;

    /** @var Registry */
    protected $registry;

    public function setEntityManagerName($name = 'default')
    {
        $this->entityManagerName = $name;
    }

    public function setRegistry(Registry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * @param $currentObjectManager
     *
     * @return EntityManager
     */
    public function getObjectManagerReset(EntityManager $currentObjectManager)
    {
        if (!$currentObjectManager->isOpen()) {
            if (($currentObjectManager = $this->registry->getManager($this->entityManagerName)) && $currentObjectManager->isOpen()) {
                return $this->objectManager = $currentObjectManager;
            }

            return $this->objectManager = $this->registry->resetManager($this->entityManagerName);
        }

        return $currentObjectManager;
    }

    /**
     * @param string    $objectName
     * @param string    $field
     * @param \DateTime $olderThan
     *
     * @return int
     */
    protected function removeOlderThan($objectName, $field, \DateTime $olderThan)
    {
        /** @var EntityManager $objectManager */
        $objectManager = $this->getObjectManager();
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

    /**
     * @return ObjectManager
     */
    abstract public function getObjectManager();
}
