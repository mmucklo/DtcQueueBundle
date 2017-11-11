<?php

namespace Dtc\QueueBundle\Tests\ORM;

use PHPUnit\Framework\TestCase;

class RunManagerTest extends TestCase
{
    public function testPruneStaleRuns()
    {
        JobManagerTest::setUpBeforeClass();
        $jobManager = JobManagerTest::$jobManager;
        $runClass = \Dtc\QueueBundle\Entity\Run::class;
        $runArchiveClass = \Dtc\QueueBundle\Entity\RunArchive::class;
        $runManager = new \Dtc\QueueBundle\ORM\RunManager($jobManager->getObjectManager(), $runClass, \Dtc\QueueBundle\Entity\JobTiming::class, true);
        $runManager->setRunArchiveClass($runArchiveClass);
        $objectManager = $runManager->getObjectManager();
        $runRepository = $objectManager->getRepository($runClass);
        self::assertEmpty($runRepository->findAll());
        $runArchiveRepository = $objectManager->getRepository($runArchiveClass);
        self::assertEmpty($runArchiveRepository->findAll());

        $run = new $runClass();
        $time = time() - 96400;
        $date = new \DateTime("@$time");

        $run->setStartedAt($date);
        $run->setLastHeartbeatAt($date);
        $objectManager->persist($run);
        $objectManager->flush($run);
        self::assertCount(1, $runRepository->findAll());

        $count = $runManager->pruneStalledRuns();
        self::assertEquals(1, $count);
        self::assertEmpty($runRepository->findAll());
        $count = $runManager->pruneStalledRuns();
        self::assertEquals(0, $count);
    }
}
