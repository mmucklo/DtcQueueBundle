<?php

namespace Dtc\QueueBundle\Documents;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

/**
 * @ODM\Document(db="queue", collection="job")
 */
class Job extends BaseJob
{
}
