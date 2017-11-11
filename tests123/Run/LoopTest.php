<?php

namespace Dtc\QueueBundle\Tests\Run;

use Dtc\QueueBundle\Beanstalkd\Job;
use Dtc\QueueBundle\Model\WorkerManager;
use Dtc\QueueBundle\ODM\JobManager;
use Dtc\QueueBundle\Run\Loop;
use Dtc\QueueBundle\Tests\Beanstalkd\JobManagerTest;
use Dtc\QueueBundle\Tests\FibonacciWorker;
use PHPUnit\Framework\TestCase;
use Dtc\QueueBundle\EventDispatcher\EventDispatcher;

class LoopTest extends TestCase
{
    public function testBeansstalkdRun()
    {
        JobManagerTest::setUpBeforeClass();
        $jobManager = JobManagerTest::$jobManager;
        $eventDispatcher = new EventDispatcher();
        $workerManager = new WorkerManager($jobManager, $eventDispatcher);
        $worker = new FibonacciWorker();
        $worker->setJobClass(Job::class);
        $workerManager->addWorker($worker);
        $worker->setJobManager($jobManager);

        $runClass = \Dtc\QueueBundle\Document\Run::class;
        $jobTimingClass = \Dtc\QueueBundle\Document\JobTiming::class;
        $runManager = new \Dtc\QueueBundle\Model\RunManager($runClass, $jobTimingClass, false);
        $loop = new Loop($workerManager, $jobManager, $runManager);
        $job = $worker->later()->fibonacci(1);
        self::assertNotNull($job->getId(), 'Job id should be generated');
        $start = microtime(true);
        self::assertNull($loop->getRun());
        $failed = false;
        try {
            $loop->runJobById($start, $job->getId());
            $failed = true;
        } catch (\Exception $e) {
            self::assertNotNull($loop->getRun());
            self::assertEquals(0, $loop->getRun()->getProcessed());
            self::assertEquals(gethostname(), $loop->getRun()->getHostname());
        }
        self::assertFalse($failed);

        try {
            $loop->runLoop($start, null, null, 0, 0);
            self::fail("shouldn't get here");
        } catch (\Exception $exception) {
            self::assertTrue(true);
        }

        $result = $loop->runLoop($start, null, null, 1);
        self::assertEquals(0, $result);
        self::assertNotNull($loop->getRun());
        self::assertEquals(1, $loop->getRun()->getProcessed());

        $result = $loop->runLoop($start, null, null, 1);
        self::assertEquals(0, $result);
        self::assertNotNull($loop->getRun());
        self::assertEquals(0, $loop->getRun()->getProcessed());

        $worker->later()->fibonacci(1);
        $worker->later()->fibonacci(2);

        $result = $loop->runLoop($start, null, null, 4);
        self::assertEquals(0, $result);
        self::assertNotNull($loop->getRun());
        self::assertEquals(2, $loop->getRun()->getProcessed());

        $result = $loop->runLoop($start, null, null, 4);
        self::assertEquals(0, $result);
        self::assertNotNull($loop->getRun());
        self::assertEquals(0, $loop->getRun()->getProcessed());
    }

    public function testMongoDBRun()
    {
        \Dtc\QueueBundle\Tests\ODM\JobManagerTest::setUpBeforeClass();

        /** @var JobManager $jobManager */
        $jobManager = \Dtc\QueueBundle\Tests\ODM\JobManagerTest::$jobManager;
        $eventDispatcher = new EventDispatcher();
        $workerManager = new WorkerManager($jobManager, $eventDispatcher);
        $worker = new FibonacciWorker();
        $worker->setJobClass(\Dtc\QueueBundle\Document\Job::class);
        $workerManager->addWorker($worker);
        $worker->setJobManager($jobManager);

        $runManager = \Dtc\QueueBundle\Tests\ODM\JobManagerTest::$runManager;
        $loop = new Loop($workerManager, $jobManager, $runManager);
        $job = $worker->later()->fibonacci(1);
        self::assertNotNull($job->getId(), 'Job id should be generated');
        $start = microtime(true);
        self::assertNull($loop->getRun());
        try {
            $loop->runJobById($start, $job->getId());
        } catch (\Exception $e) {
            self::fail('Should not get here');
        }

        self::assertNotNull($loop->getRun());
        self::assertNotNull($id1 = $loop->getRun()->getId());
        self::assertEquals(1, $loop->getRun()->getProcessed());
        self::assertEquals(gethostname(), $loop->getRun()->getHostname());
        $worker->later()->fibonacci(1);

        $result = $loop->runLoop($start, null, null, 1);
        self::assertEquals(0, $result);
        self::assertNotNull($loop->getRun());
        self::assertNotNull($id2 = $loop->getRun()->getId());
        self::assertNotEquals($id1, $id2);
        self::assertEquals(1, $loop->getRun()->getProcessed());

        $documentManager = $jobManager->getObjectManager();
        print_r($jobManager->getRunArchiveClass());
        $runArchiveRepository = $documentManager->getRepository($jobManager->getRunArchiveClass());
        self::assertNotNull($runArchiveRepository->find($id1));
        self::assertNotNull($runArchiveRepository->find($id2));

        $result = $loop->runLoop($start, null, null, 1);
        self::assertEquals(0, $result);
        self::assertNotNull($loop->getRun());
        self::assertEquals(0, $loop->getRun()->getProcessed());

        $worker->later()->fibonacci(1);
        $worker->later()->fibonacci(2);

        $result = $loop->runLoop($start, null, null, 4);
        self::assertEquals(0, $result);
        self::assertNotNull($loop->getRun());
        self::assertEquals(2, $loop->getRun()->getProcessed());

        $result = $loop->runLoop($start, null, null, 4);
        self::assertEquals(0, $result);
        self::assertNotNull($loop->getRun());
        self::assertEquals(0, $loop->getRun()->getProcessed());

        $timeStart = microtime(true);
        $result = $loop->runLoop($timeStart, null, null, null, 2);
        self::assertEquals(0, $result);
        self::assertNotNull($loop->getRun());
        self::assertEquals(0, $loop->getRun()->getProcessed());
        $total = time() - intval($timeStart);
        self::assertGreaterThanOrEqual(2, $total);
    }
}
