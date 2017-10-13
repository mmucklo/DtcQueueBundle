<?php

namespace Dtc\QueueBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Dtc\GridBundle\Annotation as Grid;

/**
 * @ORM\Entity
 * @ORM\Table(name="dtc_queue_job_archive",indexes={
 *                  @ORM\Index(name="job_archive_status_idx", columns={"status"}),
 *                  @ORM\Index(name="job_archive_updated_at_idx", columns={"updated_at"})})
 * @Grid\Grid(actions={@Grid\ShowAction()})
 */
class JobArchive extends BaseJob
{
    /**
     * @ORM\Column(type="bigint")
     * @ORM\Id
     */
    protected $id;

    /**
     * When the job finished.
     *
     * @Grid\Column(sortable=true)
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $finishedAt;

    /**
     * @Grid\Column()
     * @ORM\Column(type="float", nullable=true)
     */
    protected $elapsed;
}
