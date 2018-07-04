<?php

namespace Dtc\QueueBundle\Entity;

use Dtc\GridBundle\Annotation as Grid;
use Doctrine\ORM\Mapping as ORM;

abstract class BaseRun extends \Dtc\QueueBundle\Model\Run
{
    /**
     * @Grid\Column(sortable=true,order=1)
     * @ORM\Column(name="id", type="bigint")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;
    /**
     * @Grid\Column(sortable=true, order=2)
     * @ORM\Column(name="started_at", type="datetime", nullable=true)
     */
    protected $startedAt;
    /**
     * @ORM\Column(name="ended_at", type="datetime", nullable=true)
     */
    protected $endedAt;

    /**
     * @ORM\Column(name="elapsed", type="float", nullable=true)
     */
    protected $elapsed;

    /**
     * @Grid\Column()
     * @ORM\Column(name="duration", type="integer", nullable=true)
     */
    protected $duration; // How long to run for in seconds

    /**
     * @Grid\Column(sortable=true)
     * @ORM\Column(name="last_heartbeat_at", type="datetime")
     */
    protected $lastHeartbeatAt;

    /**
     * @Grid\Column()
     * @ORM\Column(name="max_count", type="integer", nullable=true)
     */
    protected $maxCount;
    /**
     * @Grid\Column()
     * @ORM\Column(name="processed", type="integer")
     */
    protected $processed = 0; // Number of jobs processed

    /**
     * @Grid\Column(sortable=true)
     * @ORM\Column(name="hostname", type="string", nullable=true)
     */
    protected $hostname;
    /**
     * @Grid\Column()
     * @ORM\Column(name="pid", type="integer", nullable=true)
     */
    protected $pid;

    /**
     * @Grid\Column()
     * @ORM\Column(name="process_timeout", type="integer", nullable=true)
     */
    protected $processTimeout;

    /**
     * @Grid\Column()
     * @ORM\Column(name="current_job_id", type="string", nullable=true)
     */
    protected $currentJobId;
}
