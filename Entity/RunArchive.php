<?php

namespace Dtc\QueueBundle\Entity;

use Doctrine\Common\Annotations\Annotation\IgnoreAnnotation;
use Doctrine\ORM\Mapping as ORM;
use Dtc\GridBundle\Annotation as Grid;

/**
 * @IgnoreAnnotation("Dtc\GridBundle\Annotation\Grid")
 * @ORM\Entity
 * @ORM\Table(name="dtc_queue_run_archive")
 * @ORM\Table(name="dtc_queue_run_archive",indexes={
 *                  @ORM\Index(name="run_archive_ended_at_idx", columns={"ended_at"})})
 * @Grid\Grid(actions={@Grid\ShowAction()},sort=@Grid\Sort(column="endedAt",direction="DESC"))
 */
class RunArchive extends BaseRun
{
    /**
     * @ORM\Column(name="started_at", type="datetime", nullable=true)
     */
    protected $startedAt;
    /**
     * @ORM\Column(name="duration", type="integer", nullable=true)
     */
    protected $duration; // How long to run for in seconds

    /**
     * @Grid\Column(sortable=true, order=2)
     * @ORM\Column(name="ended_at", type="datetime", nullable=true)
     */
    protected $endedAt;

    /**
     * @Grid\Column()
     * @ORM\Column(name="elapsed", type="float", nullable=true)
     */
    protected $elapsed;

    /**
     * @ORM\Column(name="max_count", type="integer", nullable=true)
     */
    protected $maxCount;

    /**
     * @ORM\Column(name="last_heartbeat_at", type="datetime", nullable=true)
     */
    protected $lastHeartbeatAt;

    /**
     * @ORM\Column(name="process_timeout", type="integer", nullable=true)
     */
    protected $processTimeout;

    /**
     * @ORM\Column(name="current_job_id", type="string", nullable=true)
     */
    protected $currentJobId;
}
