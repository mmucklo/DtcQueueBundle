<?php

namespace Dtc\QueueBundle\Tests;

use Dtc\QueueBundle\Manager\RunManager;

class StubRunManager extends RunManager
{
    use RecordingTrait;

    public function pruneStalledRuns()
    {
        return $this->recordArgs(__FUNCTION__, func_get_args());
    }

    public function pruneArchivedRuns(\DateTime $olderThan)
    {
        return $this->recordArgs(__FUNCTION__, func_get_args());
    }

    public function pruneJobTimings(\DateTime $olderThan)
    {
        return $this->recordArgs(__FUNCTION__, func_get_args());
    }
}
