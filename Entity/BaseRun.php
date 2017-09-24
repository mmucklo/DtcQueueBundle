<?php

namespace Dtc\QueueBundle\Entity;

use Dtc\QueueBundle\Model\Run;
use Dtc\GridBundle\Annotation\GridColumn;
use Doctrine\ORM\Mapping as ORM;

abstract class BaseRun extends Run
{
    /**
     * @GridColumn()
     * @ORM\Column(type="bigint")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;
    /**
     * @GridColumn()
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $startedAt;
    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $endedAt;
    /**
     * @GridColumn()
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $duration; // How long to run for in seconds

    /**
     * @GridColumn()
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $lastHeartbeatAt;

    /**
     * @GridColumn()
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $maxCount;
    /**
     * @GridColumn()
     * @ORM\Column(type="integer")
     */
    protected $processed = 0; // Number of jobs processed

    /**
     * @GridColumn()
     * @ORM\Column(type="string", nullable=true)
     */
    protected $hostname;
    /**
     * @GridColumn()
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $pid;
}
