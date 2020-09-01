<?php

namespace Dtc\QueueBundle\ORM;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Id\AssignedGenerator;
use Doctrine\ORM\Mapping\ClassMetadata;
use Dtc\QueueBundle\Entity\Job;
use Dtc\QueueBundle\Entity\JobTiming;
use Dtc\QueueBundle\Entity\Run;
use Dtc\QueueBundle\Util\Util;

trait CommonTrait
{
    protected $formerIdGenerators;

    /** @var string */
    protected $entityManagerName;

    /** @var Registry */
    protected $registry;

    protected $entityManagerReset = false;

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
    public function getObjectManagerReset()
    {
        $currentObjectManager = parent::getObjectManager();
        if (!$currentObjectManager->isOpen() && $this->registry) {
            $this->entityManagerReset = true;
            if (($currentObjectManager = $this->registry->getManager($this->entityManagerName)) && $currentObjectManager->isOpen()) {
                return $this->objectManager = $currentObjectManager;
            }

            return $this->objectManager = $this->registry->resetManager($this->entityManagerName);
        }

        return $currentObjectManager;
    }

    /**
     * @param string $objectName
     * @param string $field
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
     * @param Run|Job|JobTiming $object
     * @param string            $action
     */
    protected function persist($object, $action = 'persist')
    {
        /** @var EntityManager $objectManager */
        $objectManager = $this->getObjectManager();

        // If the entityManager gets reset somewhere midway, we may have to try to refetch the object we're persisting
        if ($this->entityManagerReset) {
            if ($object->getId() &&
                !$objectManager->getUnitOfWork()->tryGetById(['id' => $object->getId()], get_class($object))) {
                $newObject = $objectManager->find(get_class($object), $object->getId());
                if ($newObject) {
                    Util::copy($object, $newObject);
                    $object = $newObject;
                }
            }
        }

        $objectManager->$action($object);
        $objectManager->flush();
    }

    /**
     * @return ObjectManager
     */
    abstract public function getObjectManager();
}
