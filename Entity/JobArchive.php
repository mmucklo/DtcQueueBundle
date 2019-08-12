<?php

namespace Dtc\QueueBundle\Entity;

use Dtc\GridBundle\Annotation as Grid;
use Doctrine\Common\Annotations\Annotation\IgnoreAnnotation;
use Doctrine\ORM\Mapping as ORM;

/**
 * @IgnoreAnnotation("Dtc\GridBundle\Annotation\Grid")
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
     * @ORM\Column(name="id", type="bigint")
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
     * @ORM\Column(name="elapsed", type="float", nullable=true)
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
     * @ORM\Column(name="priority", type="integer", nullable=true)
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
