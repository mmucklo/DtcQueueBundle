<?php

namespace Dtc\QueueBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Dtc\GridBundle\Annotation as Grid;

/**
 * @ORM\Entity
 * @ORM\Table(name="dtc_queue_job_archive",indexes={
 *                  @ORM\Index(name="job_archive_status_idx", columns={"status"}),
 *                  @ORM\Index(name="job_archive_updated_at_idx", columns={"updated_at"})})
 * @Grid\Grid(actions={@Grid\ShowAction()},sort=@Grid\Sort(column="updatedAt",direction="DESC"))
 */
class JobArchive extends BaseJob
{
    /**
     * @Grid\Column(sortable=true, order=1)
     * @ORM\Column(type="bigint")
     * @ORM\Id
     */
    protected $id;

    /**
     * When the job finished.
     *
     * @Grid\Column(sortable=true, order=2)
     * @ORM\Column(name="finished_at", type="datetime", nullable=true)
     */
    protected $finishedAt;

    /**
     * @Grid\Column(sortable=true)
     * @ORM\Column(type="float", nullable=true)
     */
    protected $elapsed;

    /**
     * When the job started.
     *
     * @ORM\Column(name="started_at", type="datetime", nullable=true)
     */
    protected $startedAt;

    /**
     * @ORM\Column(name="when_us", type="decimal", precision=18, scale=0, nullable=true)
     */
    protected $whenUs;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $priority;

    /**
     * @ORM\Column(name="expires_at", type="datetime", nullable=true)
     */
    protected $expiresAt;

    /**
     * @Grid\Column(sortable=true, order=3)
     * @ORM\Column(name="updated_at", type="datetime")
     */
    protected $updatedAt;
}
