<?php

namespace Dtc\QueueBundle\Tests\Doctrine;

use PHPUnit\Framework\TestCase;

/**
 * This test requires local mongodb running.
 */
abstract class BaseLiveJobGridSourceTest extends TestCase
{
    abstract protected function getLiveGridSource();

    public function testLiveJobsGridSource()
    {
        $liveJobsGridSource = $this->getLiveGridSource();
        self::assertNull($liveJobsGridSource->getDefaultSort());
        self::assertFalse($liveJobsGridSource->isRunning());
        $count = $liveJobsGridSource->getCount();
        $records = $liveJobsGridSource->getRecords();
        self::assertEquals(0, $count);
        self::assertEmpty($records);

        $job = new BaseJobManagerTest::$jobClass(BaseJobManagerTest::$worker, false, null);
        $job->fibonacci(1);

        $count = $liveJobsGridSource->getCount();
        $records = $liveJobsGridSource->getRecords();
        self::assertEquals(1, $count);
        self::assertNotEmpty($records);

        $job = new BaseJobManagerTest::$jobClass(BaseJobManagerTest::$worker, false, null);
        $job->fibonacci(1);

        $count = $liveJobsGridSource->getCount();
        $records = $liveJobsGridSource->getRecords();
        self::assertEquals(2, $count);
        self::assertNotEmpty($records);

        $columns = $liveJobsGridSource->getColumns();
        self::assertNotEmpty($columns);
        foreach ($columns as $column) {
            self::assertFalse($column->getOption('sortable'));
        }

        $liveJobsGridSource->setRunning(true);
        self::assertTrue($liveJobsGridSource->isRunning());
        $count = $liveJobsGridSource->getCount();
        $records = $liveJobsGridSource->getRecords();
        self::assertEquals(0, $count);
        self::assertEmpty($records);

        BaseJobManagerTest::$jobManager->getJob();

        $count = $liveJobsGridSource->getCount();
        $records = $liveJobsGridSource->getRecords();
        self::assertEquals(1, $count);
        self::assertNotEmpty($records);

        BaseJobManagerTest::$jobManager->getJob();

        $count = $liveJobsGridSource->getCount();
        $records = $liveJobsGridSource->getRecords();
        self::assertEquals(2, $count);
        self::assertNotEmpty($records);
    }
}
