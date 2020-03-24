<?php

namespace Dtc\QueueBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

/**
 * @ODM\Document(db="dtc_queue", collection="run")
 */
class Run extends BaseRun
{
}
