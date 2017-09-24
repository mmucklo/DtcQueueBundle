<?php

namespace Dtc\QueueBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;
use Dtc\GridBundle\Annotation\GridColumn;

/**
 * @ODM\Document(db="queue", collection="run_archive")
 */
class RunArchive extends BaseRun
{
    /**
     * @ODM\Field(type="date", nullable=true)
     */
    protected $startedAt;
    /**
     * @GridColumn()
     * @ODM\Field(type="date", nullable=true)
     */
    protected $endedAt;

    /**
     * @ODM\Index(unique=false, order="asc")
     * @ODM\Field(type="date")
     */
    protected $createdAt;

    /**
     * @GridColumn()
     * @ODM\Index(unique=false, order="asc")
     * @ODM\Field(type="date")
     */
    protected $updatedAt;

    /**
     * @ODM\Field(type="int", nullable=true)
     */
    protected $maxCount;

    /**
     * @ODM\Field(type="date")
     */
    protected $lastHeartbeatAt;
}
