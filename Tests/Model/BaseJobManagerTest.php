<?php

namespace Dtc\QueueBundle\Tests\Model;

use PHPUnit\Framework\TestCase;

abstract class BaseJobManagerTest extends TestCase
{
    public static $worker;
    public static $jobClass;
    public static $jobManager;

    public static function setUpBeforeClass()
    {
        self::$jobClass = self::$worker->getJobClass();
        self::$worker->setJobManager(self::$jobManager);
    }

    public function testSaveJob()
    {
        $job = $this->getJob();
        $jobInQueue = self::$jobManager->getJob();
        self::assertNotNull($jobInQueue, 'There should be a job.');
        self::assertEquals(
            $job->getId(),
            $jobInQueue->getId(),
            'Job id returned by manager should be the same'
        );
        self::$jobManager->deleteJob($job);
    }

    protected function getJob()
    {
        $job = new self::$jobClass(self::$worker, false, null);
        $job->fibonacci(1);
        self::assertNotNull($job->getId(), 'Job id should be generated');

        return $job;
    }

    public function testGetJobByWorker()
    {
        $job = $this->getJob();
        $jobInQueue = self::$jobManager->getJob(self::$worker->getName());
        self::assertEquals(
            $job->getId(),
            $jobInQueue->getId(),
            'Job id returned by manager should be the same'
        );
    }

    protected function getNewJob()
    {
        $job = new self::$jobClass(self::$worker, false, null);
        $job->fibonacci(1);
        self::assertNotNull($job->getId(), 'Job id should be generated');

        return $job;
    }

    public function testDeleteJob()
    {
        $job = $this->getNewJob();
        self::$jobManager->deleteJob($job);

        $nextJob = self::$jobManager->getJob();
        self::assertNull($nextJob, "Shouldn't be any jobs left in queue");

        return $job;
    }

    /**
     * Not all managers will support job priority.
     */
    public function testJobPriority()
    {
        $firstJob = new self::$jobClass(self::$worker, false, 12);
        $firstJob->fibonacci(1);
        self::assertNotNull($firstJob->getId(), 'Job id should be generated');

        $secondJob = new self::$jobClass(self::$worker, false, 1);
        $secondJob->fibonacci(1);
        self::assertNotNull($secondJob->getId(), 'Job id should be generated');

        $thirdJob = new self::$jobClass(self::$worker, false, 5);
        $thirdJob->fibonacci(1);
        self::assertNotNull($thirdJob->getId(), 'Job id should be generated');

        $jobInQueue = self::$jobManager->getJob();
        self::assertNotNull($jobInQueue, 'There should be a job.');
        self::assertEquals(
            $secondJob->getId(),
            $jobInQueue->getId(),
            'Second job id should be returned - lower number unload first'
        );

        $jobInQueue = self::$jobManager->getJob();
        self::assertEquals(
            $thirdJob->getId(),
            $jobInQueue->getId(),
            'Third job id should be returned - lower number unload first'
        );

        $jobInQueue = self::$jobManager->getJob();
        self::assertEquals(
            $firstJob->getId(),
            $jobInQueue->getId(),
            'First job id should be returned - lower number unload first'
        );
    }

    public function testResetErroneousJobs()
    {
        $this->expectingException('resetErroneousJobs');
    }

    public function testResetStalledJobs()
    {
        $this->expectingException('resetStalledJobs');
    }

    public function testPruneStalledJobs()
    {
        $this->expectingException('pruneStalledJobs');
    }

    public function testPruneErroneousJobs()
    {
        $this->expectingException('pruneErroneousJobs');
    }

    public function testPruneExpiredJobs()
    {
        $this->expectingException('pruneExpiredJobs');
    }

    public function testPruneArchivedJobs()
    {
        try {
            $time = time() - 86400;
            self::$jobManager->pruneArchivedJobs(new \DateTime("@$time"));
            self::fail('Expected Exception');
        } catch (\Exception $exception) {
            self::assertTrue(true);
        }
    }

    /**
     * @param string $method
     */
    protected function expectingException($method)
    {
        try {
            self::$jobManager->$method();
            self::fail('Expected Exception');
        } catch (\Exception $exception) {
            self::assertTrue(true);
        }
    }

    /**
     * @outputBuffering disabled
     */
    public function testPerformance()
    {
        echo "\n".static::class.": Testing Performance\n";
        flush();

        $start = microtime(true);
        $jobsTotal = 100; // have to trim this down as Travis is slow.
        self::$jobManager->enableSorting = false; // Ignore priority

        for ($i = 0; $i < $jobsTotal; ++$i) {
            self::$worker->later()->fibonacci(1);
        }

        $total = microtime(true) - $start;
        echo "\nTotal of {$jobsTotal} jobs enqueued in {$total} seconds\n";

        try {
            $count = self::$jobManager->getJobCount();
            self::assertEquals($jobsTotal, $count);
        } catch (\Exception $e) {
            if ('Unsupported' !== $e->getMessage()) {
                throw $e;
            }
        }

        $start = microtime(true);
        $job = null;
        for ($i = 0; $i < $jobsTotal; ++$i) {
            $job = self::$jobManager->getJob();
        }
        $total = microtime(true) - $start;
        echo "Total of {$jobsTotal} jobs dequeued in {$total} seconds\n";
        self::assertNotNull($job, 'The last job in queue...');

        $nextJob = self::$jobManager->getJob();
        self::assertNull($nextJob, "Shouldn't be any jobs left in queue");
    }
}
