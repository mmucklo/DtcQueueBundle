<?php

namespace Dtc\QueueBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;
use Dtc\GridBundle\Annotation as Grid;

abstract class BaseRun extends \Dtc\QueueBundle\Model\Run
{
    /**
     * @Grid\Column(sortable=true)
     * @ODM\Id
     */
    protected $id;

    /**
     * @Grid\Column()
     * @ODM\Field(type="date", nullable=true)
     */
    protected $startedAt;

    /**
     * @ODM\Field(type="date", nullable=true)
     */
    protected $endedAt;

    /**
     * @ODM\Field(type="float", nullable=true)
     */
    protected $elapsed;

    /**
     * @Grid\Column()
     * @ODM\Field(type="int", nullable=true)
     */
    protected $duration; // How long to run for in seconds

    /**
     * @Grid\Column()
     * @ODM\Field(type="date")
     */
    protected $lastHeartbeatAt;

    /**
     * @Grid\Column()
     * @ODM\Field(type="int", nullable=true)
     */
    protected $maxCount;
    /**
     * @Grid\Column()
     * @ODM\Field(type="int", nullable=true)
     */
    protected $processed; // Number of jobs processed

    /**
     * @Grid\Column()
     * @ODM\Field(type="string", nullable=true)
     */
    protected $hostname;
    /**
     * @Grid\Column()
     * @ODM\Field(type="int", nullable=true)
     */
    protected $pid;
}
