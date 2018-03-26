<?php

namespace Dtc\QueueBundle\Tests\Manager;

use Dtc\QueueBundle\Model\Job;
use Dtc\QueueBundle\Model\BaseJob;

trait SaveJobTrait
{
    public function testSaveJob()
    {
        $this->drain();
        // Make sure a job proper type
        $failed = false;
        try {
            $job = new Job();
            self::$jobManager->save($job);
            $failed = true;
        } catch (\Exception $exception) {
            self::assertTrue(true);
        }
        self::assertFalse($failed);

        if (null !== self::$jobManager->getMaxPriority()) {
            $job = new self::$jobClass(self::$worker, false, null);
            try {
                $job->setPriority(256)->fibonacci(1);
                $failed = true;
            } catch (\Exception $exception) {
                self::assertTrue(true);
            }
            self::assertFalse($failed);

            $job = new self::$jobClass(self::$worker, false, null);
            $job->setPriority(100)->fibonacci(1);
            self::assertNotNull($job->getId(), 'Job id should be generated');

            $jobInQueue = self::$jobManager->getJob();
            self::assertNotNull($jobInQueue, 'There should be a job.');
            self::$jobManager->saveHistory($jobInQueue);
        }

        $job = new self::$jobClass(self::$worker, false, null);
        $job->fibonacci(1);
        self::assertNotNull($job->getId(), 'Job id should be generated');

        $failed = false;
        try {
            self::$jobManager->getJob('fibonacci');
            $failed = true;
        } catch (\Exception $exception) {
            self::assertTrue(true);
        }
        self::assertFalse($failed);

        $failed = false;
        try {
            self::$jobManager->getJob(null, 'fibonacci');
            $failed = true;
        } catch (\Exception $exception) {
            self::assertTrue(true);
        }
        self::assertFalse($failed);

        $jobInQueue = self::$jobManager->getJob();
        self::assertNotNull($jobInQueue, 'There should be a job.');
        self::assertEquals(
            $job->getId(),
            $jobInQueue->getId(),
            'Job id returned by manager should be the same'
        );

        $job->setStatus(BaseJob::STATUS_SUCCESS);
        $badJob = new Job();
        $failed = false;
        try {
            self::$jobManager->saveHistory($badJob);
            $failed = true;
        } catch (\Exception $exception) {
            self::assertTrue(true);
        }
        self::assertFalse($failed);
        self::$jobManager->saveHistory($jobInQueue);
    }
}
