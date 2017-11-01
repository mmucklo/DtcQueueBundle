<?php

namespace Dtc\QueueBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Dtc\GridBundle\Annotation as Grid;

/**
 * @ORM\Entity
 * @ORM\Table(name="dtc_queue_run_archive")
 * @Grid\Grid(actions={@Grid\ShowAction()})
 */
class RunArchive extends BaseRun
{
    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $startedAt;
    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $duration; // How long to run for in seconds

    /**
     * @Grid\Column(sortable=true)
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $endedAt;

    /**
     * @Grid\Column()
     * @ORM\Column(type="float", nullable=true)
     */
    protected $elapsed;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $maxCount;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $lastHeartbeatAt;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $processTimeout;
}
