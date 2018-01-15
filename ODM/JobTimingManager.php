<?php

namespace Dtc\QueueBundle\ODM;

use Dtc\QueueBundle\Doctrine\DoctrineJobTimingManager;

class JobTimingManager extends DoctrineJobTimingManager
{
    use CommonTrait;
}
