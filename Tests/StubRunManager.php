<?php

namespace Dtc\QueueBundle\Tests;

use Dtc\QueueBundle\Model\RunManager;

class StubRunManager extends RunManager
{
    use RecordingTrait;

    public function pruneArchivedRuns(\DateTime $olderThan)
    {
        return $this->recordArgs(__FUNCTION__, func_get_args());
    }

    public function pruneJobTimings(\DateTime $olderThan)
    {
        return $this->recordArgs(__FUNCTION__, func_get_args());
    }
}
