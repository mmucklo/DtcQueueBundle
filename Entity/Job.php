<?php

namespace Dtc\QueueBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Dtc\GridBundle\Annotation as Grid;

/**
 * Note: the number of indexes was purposefully kept smaller than it could be (such as adding an expires index)
 *   This was done to keep the number of indexes reasonably minimal for insert performance considerations.
 *
 * @ORM\Entity
 * @ORM\Table(name="dtc_queue_job", indexes={@ORM\Index(name="job_crc_hash_idx", columns={"crc_hash","status"}),
 *                  @ORM\Index(name="job_priority_idx", columns={"priority","when_at"}),
 *                  @ORM\Index(name="job_when_idx", columns={"when_at","locked"}),
 *                  @ORM\Index(name="job_status_idx", columns={"status","locked","when_at"})})
 * @Grid\Grid(actions={@Grid\ShowAction(), @Grid\DeleteAction(label="Archive")})
 */
class Job extends BaseJob
{
    /**
     * @Grid\Column(sortable=true)
     * @ORM\Column(type="bigint")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;
}
