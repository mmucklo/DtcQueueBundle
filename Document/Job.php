<?php

namespace Dtc\QueueBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

/**
 * @ODM\Document(db="dtc_queue", collection="job")
 */
class Job extends BaseJob
{
    public const STATUS_ARCHIVE = 'archive';
}
