<?php

namespace Dtc\QueueBundle\Doctrine;

use Dtc\QueueBundle\Model\Job;
use Dtc\QueueBundle\Model\JobTiming;
use Dtc\QueueBundle\Model\Run;
use Dtc\QueueBundle\Util\Util;

trait CommonTrait
{
    /**
     * @param Run|Job|JobTiming $object
     * @param string            $action
     */
    protected function persist($object, $action = 'persist')
    {
        $objectManager = $this->getObjectManager();
        if ($objId = $object->getId()) {
            $newObj = $objectManager->getRepository(get_class($object))->find($objId);
            if (null !== $newObj && spl_object_hash($newObj) !== spl_object_hash($object)) {
                Util::copy($object, $newObj);
                $object = $newObj;
            }
        }
        $objectManager->$action($object);
        $objectManager->flush();
    }
}
