<?php

namespace Dtc\QueueBundle\Tests\ORM;

use Dtc\QueueBundle\ORM\LiveJobsGridSource;
use Dtc\QueueBundle\Tests\Doctrine\BaseLiveJobGridSourceTest;
use Dtc\QueueBundle\Tests\Doctrine\DoctrineJobManagerTest;

/**
 * This test requires local mongodb running.
 */
class LiveJobsGridSourceTest extends BaseLiveJobGridSourceTest
{
    public function getLiveGridSource()
    {
        return new LiveJobsGridSource(DoctrineJobManagerTest::$jobManager);
    }

    public static function setUpBeforeClass(): void
    {
        JobManagerTest::setUpBeforeClass();
    }
}
