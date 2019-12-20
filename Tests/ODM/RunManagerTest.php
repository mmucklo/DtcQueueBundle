<?php

namespace Dtc\QueueBundle\Tests\ODM;

use Dtc\QueueBundle\Document\RunArchive;
use PHPUnit\Framework\TestCase;

class RunManagerTest extends TestCase
{
    public function testPruneArchivedRuns()
    {
        JobManagerTest::setUpBeforeClass();
        $jobManager = JobManagerTest::$jobManager;
        $runClass = \Dtc\QueueBundle\Document\Run::class;
        $runManager = new \Dtc\QueueBundle\ODM\RunManager($jobManager->getObjectManager(), $runClass, RunArchive::class);
        $objectManager = $runManager->getObjectManager();
        $runRepository = $objectManager->getRepository($runClass);
        self::assertEmpty($runRepository->findAll());
        $runArchiveRepository = $objectManager->getRepository(RunArchive::class);
        self::assertEmpty($runArchiveRepository->findAll());

        $run = new $runClass();
        $time = time() - 86400;

        $run->setStartedAt(new \DateTime("@$time"));
        $objectManager->persist($run);
        $objectManager->flush();
        self::assertCount(1, $runRepository->findAll());

        $run->setEndedAt(new \DateTime("@$time"));
        $objectManager->remove($run);
        $objectManager->flush();

        self::assertEmpty($runRepository->findAll());
        self::assertCount(1, $runArchiveRepository->findAll());

        $time1 = $time + 1;
        $runManager->pruneArchivedRuns(new \DateTime("@$time1"));
        self::assertEmpty($runArchiveRepository->findAll());
        self::assertEmpty($runRepository->findAll());
    }

    public function testPruneStaleRuns()
    {
        JobManagerTest::setUpBeforeClass();
        $jobManager = JobManagerTest::$jobManager;
        $runClass = \Dtc\QueueBundle\Document\Run::class;
        $runArchiveClass = \Dtc\QueueBundle\Document\RunArchive::class;
        $runManager = new \Dtc\QueueBundle\ODM\RunManager($jobManager->getObjectManager(), $runClass, RunArchive::class);
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
        $objectManager->flush();
        self::assertCount(1, $runRepository->findAll());

        $count = $runManager->pruneStalledRuns();
        self::assertEquals(1, $count);
        self::assertEmpty($runRepository->findAll());
        $count = $runManager->pruneStalledRuns();
        self::assertEquals(0, $count);
    }
}
