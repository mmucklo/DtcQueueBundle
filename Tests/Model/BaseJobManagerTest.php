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
        $job = new self::$jobClass(self::$worker, false, null);
        $job->fibonacci(1);
        $this->assertNotNull($job->getId(), 'Job id should be generated');

        $jobInQueue = self::$jobManager->getJob();
        $this->assertNotNull($jobInQueue, 'There should be a job.');
        $this->assertEquals(
            $job->getId(),
            $jobInQueue->getId(),
            'Job id returned by manager should be the same'
        );
        self::$jobManager->deleteJob($job);
    }

    public function testGetJobByWorker()
    {
        $job = new self::$jobClass(self::$worker, false, null);
        $job->fibonacci(1);
        $this->assertNotNull($job->getId(), 'Job id should be generated');

        $jobInQueue = self::$jobManager->getJob(self::$worker->getName());
        $this->assertEquals(
            $job->getId(),
            $jobInQueue->getId(),
            'Job id returned by manager should be the same'
        );
    }

    public function testDeleteJob()
    {
        $job = new self::$jobClass(self::$worker, false, null);
        $job->fibonacci(1);
        $this->assertNotNull($job->getId(), 'Job id should be generated');

        self::$jobManager->deleteJob($job);

        $nextJob = self::$jobManager->getJob();
        $this->assertNull($nextJob, "Shouldn't be any jobs left in queue");

        return $job;
    }

    /**
     * Not all managers will support job priority.
     */
    public function testJobPriority()
    {
        $firstJob = new self::$jobClass(self::$worker, false, 12);
        $firstJob->fibonacci(1);
        $this->assertNotNull($firstJob->getId(), 'Job id should be generated');

        $secondJob = new self::$jobClass(self::$worker, false, 1);
        $secondJob->fibonacci(1);
        $this->assertNotNull($secondJob->getId(), 'Job id should be generated');

        $thirdJob = new self::$jobClass(self::$worker, false, 5);
        $thirdJob->fibonacci(1);
        $this->assertNotNull($thirdJob->getId(), 'Job id should be generated');

        $jobInQueue = self::$jobManager->getJob();
        $this->assertNotNull($jobInQueue, 'There should be a job.');
        $this->assertEquals(
            $secondJob->getId(),
            $jobInQueue->getId(),
            'Second job id should be returned - lower number unload first'
        );

        $jobInQueue = self::$jobManager->getJob();
        $this->assertEquals(
            $thirdJob->getId(),
            $jobInQueue->getId(),
            'Third job id should be returned - lower number unload first'
        );

        $jobInQueue = self::$jobManager->getJob();
        $this->assertEquals(
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
            $startLater = microtime(true);
            self::$worker->later()->fibonacci(1);
            echo "set $i: ".(microtime(true) - $startLater)."\n";
            try {
                $count = self::$jobManager->getJobCount();
                self::assertEquals($i + 1, $count);
                echo "\n$count Jobs found\n";
            } catch (\Exception $e) {
                if ('Unsupported' !== $e->getMessage()) {
                    throw $e;
                }
            }
        }

        $total = microtime(true) - $start;
        echo "\nTotal of {$jobsTotal} jobs enqueued in {$total} seconds\n";

        try {
            $count = self::$jobManager->getJobCount();
            echo "\n$count Jobs found\n";
        } catch (\Exception $e) {
            if ('Unsupported' !== $e->getMessage()) {
                throw $e;
            }
        }
        $start = microtime(true);
        $job = null;
        for ($i = 0; $i < $jobsTotal; ++$i) {
            $startTime = microtime(true);
            $job = self::$jobManager->getJob();
            echo "Job {$job->getId()}\n";
            echo "get $i: ".(microtime(true) - $startTime)."\n";
        }
        $total = microtime(true) - $start;
        echo "Total of {$jobsTotal} jobs dequeued in {$total} seconds\n";
        $this->assertNotNull($job, 'The last job in queue...');

        $nextJob = self::$jobManager->getJob();
        $this->assertNull($nextJob, "Shouldn't be any jobs left in queue");
    }
}
