<?php

namespace Dtc\QueueBundle\Redis;

use Dtc\QueueBundle\Model\MessageableJobWithId;
use Dtc\QueueBundle\Model\MicrotimeTrait;

class Job extends MessageableJobWithId
{
    use MicrotimeTrait;
}
