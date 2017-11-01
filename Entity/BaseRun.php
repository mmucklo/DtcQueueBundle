<?php

namespace Dtc\QueueBundle\Entity;

use Dtc\GridBundle\Annotation as Grid;
use Doctrine\ORM\Mapping as ORM;

abstract class BaseRun extends \Dtc\QueueBundle\Model\Run
{
    /**
     * @Grid\Column()
     * @ORM\Column(type="bigint")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;
    /**
     * @Grid\Column(sortable=true)
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $startedAt;
    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $endedAt;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    protected $elapsed;

    /**
     * @Grid\Column()
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $duration; // How long to run for in seconds

    /**
     * @Grid\Column(sortable=true)
     * @ORM\Column(type="datetime")
     */
    protected $lastHeartbeatAt;

    /**
     * @Grid\Column()
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $maxCount;
    /**
     * @Grid\Column()
     * @ORM\Column(type="integer")
     */
    protected $processed = 0; // Number of jobs processed

    /**
     * @Grid\Column(sortable=true)
     * @ORM\Column(type="string", nullable=true)
     */
    protected $hostname;
    /**
     * @Grid\Column()
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $pid;

    /**
     * @Grid\Column()
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $processTimeout;
}
