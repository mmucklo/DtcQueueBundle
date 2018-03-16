<?php

namespace Dtc\QueueBundle\Doctrine;

use Dtc\QueueBundle\Model\StallableJob;

class BaseJob extends StallableJob {
    const STATUS_ARCHIVE = 'archive';
}