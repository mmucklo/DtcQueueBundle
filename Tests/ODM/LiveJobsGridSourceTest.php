<?php

namespace Dtc\QueueBundle\Tests\ODM;

use Dtc\QueueBundle\ODM\LiveJobsGridSource;
use Dtc\QueueBundle\Tests\Doctrine\BaseLiveJobGridSourceTest;
use Dtc\QueueBundle\Tests\Doctrine\DoctrineJobManagerTest;

/**
 * This test requires local mongodb running.
 */
class LiveJobsGridSourceTest extends BaseLiveJobGridSourceTest
{
    protected function getLiveGridSource()
    {
        $jobManager = DoctrineJobManagerTest::$jobManager;
        $liveJobsGridSource = new LiveJobsGridSource($jobManager);

        return $liveJobsGridSource;
    }

    public static function setUpBeforeClass(): void
    {
        JobManagerTest::setUpBeforeClass();
    }
}
