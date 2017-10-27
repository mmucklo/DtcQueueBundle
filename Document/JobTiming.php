<?php

namespace Dtc\QueueBundle\Document;

use Dtc\QueueBundle\Model\JobTiming as BaseJobTiming;
use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

/**
 * @ODM\Document(db="dtc_queue", collection="job_timing")
 */
class JobTiming extends BaseJobTiming
{
    /**
     * @ODM\Id
     */
    protected $id;

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param mixed $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @ODM\Field(type="date")
     * @ODM\Index(unique=false, order="asc")
     */
    protected $finishedAt;
}
