<?php

namespace Dtc\QueueBundle\Tests;

use Dtc\QueueBundle\Manager\JobTimingManager;

class StubJobTimingManager extends JobTimingManager
{
    use RecordingTrait;

    public function pruneJobTimings(\DateTime $olderThan)
    {
        return $this->recordArgs(__FUNCTION__, func_get_args());
    }
}
