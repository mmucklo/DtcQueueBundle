<?php

namespace Dtc\QueueBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;
use Dtc\GridBundle\Annotation\GridColumn;
use Dtc\QueueBundle\Model\Run;

abstract class BaseRun extends Run
{
    /**
     * @GridColumn()
     * @ODM\Id
     */
    protected $id;
    /**
     * @GridColumn()
     * @ODM\Field(type="date", nullable=true)
     */
    protected $startedAt;
    /**
     * @ODM\Field(type="date", nullable=true)
     */
    protected $endedAt;
    /**
     * @GridColumn()
     * @ODM\Field(type="int", nullable=true)
     */
    protected $duration; // How long to run for in seconds

    /**
     * @GridColumn()
     * @ODM\Field(type="date")
     */
    protected $lastHeartbeatAt;

    /**
     * @GridColumn()
     * @ODM\Field(type="int", nullable=true)
     */
    protected $maxCount;
    /**
     * @GridColumn()
     * @ODM\Field(type="int", nullable=true)
     */
    protected $processed; // Number of jobs processed

    /**
     * @GridColumn()
     * @ODM\Field(type="string", nullable=true)
     */
    protected $hostname;
    /**
     * @GridColumn()
     * @ODM\Field(type="int", nullable=true)
     */
    protected $pid;
}
