<?php

namespace Dtc\QueueBundle\Tests\ORM;

use Dtc\QueueBundle\ORM\LiveJobsGridSource;
use Dtc\QueueBundle\Tests\Doctrine\BaseJobManagerTest;
use Dtc\QueueBundle\Tests\Doctrine\BaseLiveJobGridSourceTest;

/**
 * This test requires local mongodb running.
 */
class LiveJobsGridSourceTest extends BaseLiveJobGridSourceTest
{
    public function getLiveGridSource()
    {
        return new LiveJobsGridSource(BaseJobManagerTest::$jobManager);
    }

    public static function setUpBeforeClass()
    {
        JobManagerTest::setUpBeforeClass();
    }
}
