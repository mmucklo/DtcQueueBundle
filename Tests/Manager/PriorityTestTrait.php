<?php

namespace Dtc\QueueBundle\Tests\Manager;

use Dtc\QueueBundle\Manager\PriorityJobManager;

trait PriorityTestTrait
{
    public function testPriorityJobs()
    {
        $jobManager = static::$jobManager;

        while ($jobManager->getJob()) {
            static::assertTrue(true);
        }

        // Null vs priority case

        /** @var Job $job */
        $job = new static::$jobClass(static::$worker, false, null);
        $job->fibonacci(1);
        $id = $job->getId();

        $job2 = new static::$jobClass(static::$worker, false, null);
        $job2->setPriority(1);
        $job2->fibonacci(1);
        $id2 = $job2->getId();

        $nextJob = $jobManager->getJob();
        static::assertEquals($id2, $nextJob->getId());
        static::assertEquals($id, $jobManager->getJob()->getId());

        // priority vs priority case

        /** @var Job $job */
        $job = new static::$jobClass(static::$worker, false, null);
        $job->fibonacci(1);
        $job->setPriority(3);
        $id = $job->getId();

        $job2 = new static::$jobClass(static::$worker, false, null);
        $job2->setPriority(1);
        $job2->fibonacci(1);
        $id2 = $job2->getId();

        $nextJob = $jobManager->getJob();
        static::assertEquals($id2, $nextJob->getId());
        static::assertEquals($id, $jobManager->getJob()->getId());

        // priority too high case

        /** @var Job $job */
        $failed = false;
        try {
            $job = new static::$jobClass(static::$worker, false, null);
            $job->setPriority(999);
            $job->fibonacci(1);
            $failed = true;
        } catch (\Exception $exception) {
            static::assertTrue(true);
        }
        static::assertFalse($failed);

        // Flip direction
        $jobManager->setPriorityDirection(PriorityJobManager::PRIORITY_ASC);

        // priority vs priority case

        /** @var Job $job */
        $job = new static::$jobClass(static::$worker, false, null);
        $job->fibonacci(1);
        $job->setPriority(1);
        $id = $job->getId();

        $job2 = new static::$jobClass(static::$worker, false, null);
        $job2->setPriority(3);
        $job2->fibonacci(3);
        $id2 = $job2->getId();

        $nextJob = $jobManager->getJob();
        static::assertEquals($id2, $nextJob->getId());
        static::assertEquals($id, $jobManager->getJob()->getId());

        // Null vs priority case

        /** @var Job $job */
        $job = new static::$jobClass(static::$worker, false, null);
        $job->fibonacci(1);
        $id = $job->getId();

        $job2 = new static::$jobClass(static::$worker, false, null);
        $job2->setPriority(1);
        $job2->fibonacci(1);
        $id2 = $job2->getId();

        $nextJob = $jobManager->getJob();
        static::assertEquals($id2, $nextJob->getId());
        static::assertEquals($id, $jobManager->getJob()->getId());
    }
}
