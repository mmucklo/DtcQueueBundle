<?php

namespace Dtc\QueueBundle\Tests\Manager;

use Dtc\QueueBundle\Exception\UnsupportedException;
use Dtc\QueueBundle\Manager\JobTimingManager;
use PHPUnit\Framework\TestCase;

class JobTimingManagerTest extends TestCase
{
    public function testPruneJobTimings()
    {
        $jobTimingManager = new JobTimingManager('a', false);
        $failure = false;
        try {
            $jobTimingManager->pruneJobTimings(new \DateTime());
            $failure = true;
        } catch (UnsupportedException $exception) {
            self::assertNotNull($exception);
        }
        self::assertFalse($failure);
    }
}
