<?php

namespace Dtc\QueueBundle\Document;

use Dtc\GridBundle\Annotation\GridColumn;

/**
 * @ORM\Entity
 * @ORM\Table(name="run_archive")
 */
class RunArchive extends BaseRun
{
    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $startedAt;
    /**
     * @GridColumn()
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $endedAt;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $maxCount;

    /**
     * @ORM\Column(type="datetime")
     */
    protected $lastHeartbeatAt;
}
