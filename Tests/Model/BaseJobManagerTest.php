<?php

namespace Dtc\QueueBundle\Tests\Model;

use Dtc\QueueBundle\Model\Job;

class BaseJobManagerTest extends \PHPUnit_Framework_TestCase
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
        $this->assertEquals($job->getId(), $jobInQueue->getId(),
                'Job id returned by manager should be the same');
    }

    public function testDeleteJob()
    {
        $job = new self::$jobClass(self::$worker, false, null);
        $job->fibonacci(1);
        $this->assertNotNull($job->getId(), 'Job id should be generated');

        self::$jobManager->deleteJob($job);

        $nextJob = self::$jobManager->getJob();
        $this->assertNull($nextJob, "Shouldn't be any jobs left in queue");
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
        $this->assertEquals($secondJob->getId(), $jobInQueue->getId(),
                'Second job id should be returned - lower number unload first');

        $jobInQueue = self::$jobManager->getJob();
        $this->assertEquals($thirdJob->getId(), $jobInQueue->getId(),
                'Third job id should be returned - lower number unload first');

        $jobInQueue = self::$jobManager->getJob();
        $this->assertEquals($firstJob->getId(), $jobInQueue->getId(),
                'First job id should be returned - lower number unload first');
    }

    public function testGetJobByWorker()
    {
        $job = new self::$jobClass(self::$worker, false, null);
        $job->fibonacci(1);
        $this->assertNotNull($job->getId(), 'Job id should be generated');

        $jobInQueue = self::$jobManager->getJob(self::$worker->getName());
        $this->assertEquals($job->getId(), $jobInQueue->getId(),
                'Job id returned by manager should be the same');
    }

    public function testPerformance()
    {
        $start = microtime(true);
        $jobsTotal = 1000;
        self::$jobManager->enableSorting = false;    // Ignore priority

        for ($i = 0; $i < $jobsTotal; ++$i) {
            self::$worker->later()->fibonacci(1);
        }

        $total = microtime(true) - $start;
        echo "\nTotal of {$jobsTotal} jobs enqueued in {$total} seconds\n";

        $start = microtime(true);
        for ($i = 0; $i < $jobsTotal; ++$i) {
            $job = self::$jobManager->getJob();
        }
        $total = microtime(true) - $start;
        echo "Total of {$jobsTotal} jobs dequeued in {$total} seconds\n";
        $this->assertNotNull($job, 'The last job in queue...');

        $nextJob = self::$jobManager->getJob();
        $this->assertNull($nextJob, "Shouldn't be any jobs left in queue");
    }
}
