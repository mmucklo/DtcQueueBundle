<?php

namespace Dtc\QueueBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Class BaseRun.
 */
abstract class BaseRun extends \Dtc\QueueBundle\Model\Run
{
    /**
     * @ORM\Column(name="id", type="bigint")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;
    /**
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
     * @ORM\Column(name="duration", type="integer", nullable=true)
     */
    protected $duration; // How long to run for in seconds

    /**
     * @ORM\Column(name="last_heartbeat_at", type="datetime")
     */
    protected $lastHeartbeatAt;

    /**
     * @ORM\Column(name="max_count", type="integer", nullable=true)
     */
    protected $maxCount;
    /**
     * @ORM\Column(name="processed", type="integer")
     */
    protected $processed = 0; // Number of jobs processed

    /**
     * @ORM\Column(name="hostname", type="string", nullable=true)
     */
    protected $hostname;
    /**
     * @ORM\Column(name="pid", type="integer", nullable=true)
     */
    protected $pid;

    /**
     * @ORM\Column(name="process_timeout", type="integer", nullable=true)
     */
    protected $processTimeout;

    /**
     * @ORM\Column(name="current_job_id", type="string", nullable=true)
     */
    protected $currentJobId;
}
