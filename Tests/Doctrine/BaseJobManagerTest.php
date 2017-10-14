<?php

namespace Dtc\QueueBundle\Tests\Doctrine;

use Dtc\QueueBundle\Doctrine\BaseJobManager;
use Dtc\QueueBundle\Model\BaseJob;
use Dtc\QueueBundle\Model\Job;
use Dtc\QueueBundle\Tests\Model\BaseJobManagerTest as BaseBaseJobManagerTest;
use Dtc\QueueBundle\ODM\JobManager;

/**
 * @author David
 *
 * This test requires local mongodb running
 */
abstract class BaseJobManagerTest extends BaseBaseJobManagerTest
{
    public function testDeleteJob()
    {
        $job = parent::testDeleteJob();
        $jobManager = self::$jobManager;
        /** @var JobManager|\Dtc\QueueBundle\ORM\JobManager $jobManager */
        $archiveObjectName = $jobManager->getArchiveObjectName();

        $id = $job->getId();
        $archiveRepository = $jobManager->getObjectManager()->getRepository($archiveObjectName);
        $result = $archiveRepository->find($id);
        self::assertNotNull($result);
        self::assertEquals($id, $result->getId());
    }

    public function testResetErroneousJobs()
    {
        $job = parent::testDeleteJob();
        /** @var JobManager|\Dtc\QueueBundle\ORM\JobManager $jobManager */
        $jobManager = self::$jobManager;
        $archiveObjectName = $jobManager->getArchiveObjectName();

        $id = $job->getId();
        $objectManager = $jobManager->getObjectManager();

        $archiveRepository = $objectManager->getRepository($archiveObjectName);
        $result = $archiveRepository->find($id);
        self::assertNotNull($result);
        self::assertEquals($id, $result->getId());

        $result->setStatus(BaseJob::STATUS_ERROR);
        $result->setLocked(true);
        $result->setLockedAt(new \DateTime());
        $result->setFinishedAt(new \DateTime());
        $result->setElapsed(12345);
        $result->setMessage('soomething');
        $objectManager->persist($result);
        $objectManager->flush();

        $count = $jobManager->resetErroneousJobs();

        self::assertEquals(1, $count);
        $repository = $jobManager->getRepository();
        $job = $repository->find($id);

        self::assertNotNull($job);
        self::assertEquals(BaseJob::STATUS_NEW, $job->getStatus());
        self::assertNull($job->getLockedAt());
        self::assertNull($job->getFinishedAt());
        self::assertNull($job->getElapsed());
        self::assertNull($job->getMessage());
        self::assertNull($job->getLocked());

        $objectManager->remove($job);
        $objectManager->flush();
    }

    public function testResetStalledJobs()
    {
        /** @var JobManager|\Dtc\QueueBundle\ORM\JobManager $jobManager */
        $jobManager = self::$jobManager;

        $job = new self::$jobClass(self::$worker, false, null);
        $job->fibonacci(1);
        self::assertNotNull($job->getId(), 'Job id should be generated');
        $job->setStatus(BaseJob::STATUS_RUNNING);
        $job->setLocked(true);
        $time = time();
        $date = new \DateTime("@$time");
        $job->setLockedAt($date);
        $id = $job->getId();
        $job = $jobManager->getRepository()->find($id);

        self::assertNotNull($job);

        $runClass = $jobManager->getRunClass();

        $objectManager = $jobManager->getObjectManager();
        $run = new $runClass();
        $objectManager->persist($run);
        $objectManager->flush();
        $runId = $run->getId();
        self::assertNotNull($runId);
        $job->setRunId($runId);
        $objectManager->persist($job);
        $objectManager->flush();
        $objectManager->remove($run);
        $objectManager->flush();
        $id = $job->getId();
        $job = $jobManager->getRepository()->find($id);

        self::assertNotNull($job);

        $archivedRun = $objectManager->getRepository($jobManager->getRunArchiveClass())->find($runId);

        $minusTime = $time - (BaseJobManager::STALLED_SECONDS + 1);
        $archivedRun->setEndedAt(new \DateTime("@$minusTime"));

        $objectManager->persist($archivedRun);
        $objectManager->flush();

        $count = $jobManager->resetStalledJobs();

        self::assertEquals(1, $count);

        $id = $job->getId();
        $job = $jobManager->getRepository()->find($id);

        self::assertNotNull($job);
        self::assertEquals(BaseJob::STATUS_NEW, $job->getStatus());
        self::assertNull($job->getLockedAt());
        self::assertNull($job->getFinishedAt());
        self::assertNull($job->getElapsed());
        self::assertNull($job->getMessage());
        self::assertNull($job->getLocked());

        $objectManager->remove($job);
        $objectManager->flush();
    }

    public function testPruneErroneousJobs()
    {
        $job = new self::$jobClass(self::$worker, false, null);
        $job->fibonacci(1);

        /** @var JobManager|\Dtc\QueueBundle\ORM\JobManager $jobManager */
        $jobManager = self::$jobManager;
        $jobManager->deleteJob($job);
        $archiveObjectName = $jobManager->getArchiveObjectName();

        $id = $job->getId();
        $objectManager = $jobManager->getObjectManager();

        $archiveRepository = $objectManager->getRepository($archiveObjectName);
        $result = $archiveRepository->find($id);
        self::assertNotNull($result);
        self::assertEquals($id, $result->getId());

        $result->setStatus(BaseJob::STATUS_ERROR);
        $result->setLocked(true);
        $result->setLockedAt(new \DateTime());
        $result->setFinishedAt(new \DateTime());
        $result->setElapsed(12345);
        $result->setMessage('soomething');
        $objectManager->persist($result);
        $objectManager->flush();

        $count = $jobManager->pruneErroneousJobs('asdf');
        self::assertEquals(0, $count);
        $count = $jobManager->pruneErroneousJobs(null, 'asdf');
        self::assertEquals(0, $count);
        $count = $jobManager->pruneErroneousJobs('fibonacci', 'asdf');
        self::assertEquals(0, $count);
        $count = $jobManager->pruneErroneousJobs('fibonacci', 'asdf');
        self::assertEquals(0, $count);
        $count = $jobManager->pruneErroneousJobs('fibonacci', 'fibonacci');
        self::assertEquals(1, $count);
        $repository = $jobManager->getRepository();
        $job = $repository->find($id);
        $objectManager->clear();
        self::assertNull($job);
        $archiveJob = $archiveRepository->find($id);
        self::assertNull($archiveJob);

        $job = parent::testDeleteJob();
        /** @var JobManager|\Dtc\QueueBundle\ORM\JobManager $jobManager */
        $jobManager = self::$jobManager;
        $archiveObjectName = $jobManager->getArchiveObjectName();

        $id = $job->getId();
        $objectManager = $jobManager->getObjectManager();

        $archiveRepository = $objectManager->getRepository($archiveObjectName);
        $result = $archiveRepository->find($id);
        self::assertNotNull($result);
        self::assertEquals($id, $result->getId());

        $result->setStatus(BaseJob::STATUS_ERROR);
        $result->setLocked(true);
        $result->setLockedAt(new \DateTime());
        $result->setFinishedAt(new \DateTime());
        $result->setElapsed(12345);
        $result->setMessage('soomething');
        $objectManager->persist($result);
        $objectManager->flush();

        $job = parent::testDeleteJob();
        /** @var JobManager|\Dtc\QueueBundle\ORM\JobManager $jobManager */
        $jobManager = self::$jobManager;
        $archiveObjectName = $jobManager->getArchiveObjectName();

        $id = $job->getId();
        $objectManager = $jobManager->getObjectManager();

        $archiveRepository = $objectManager->getRepository($archiveObjectName);
        $result = $archiveRepository->find($id);
        self::assertNotNull($result);
        self::assertEquals($id, $result->getId());

        $result->setStatus(BaseJob::STATUS_ERROR);
        $result->setLocked(true);
        $result->setLockedAt(new \DateTime());
        $result->setFinishedAt(new \DateTime());
        $result->setElapsed(12345);
        $result->setMessage('soomething');
        $objectManager->persist($result);
        $objectManager->flush();
        $count = $jobManager->pruneErroneousJobs();
        self::assertEquals(2, $count);
    }

    public function testPruneStalledJobs()
    {
        /** @var JobManager|\Dtc\QueueBundle\ORM\JobManager $jobManager */
        $jobManager = self::$jobManager;

        $job = new self::$jobClass(self::$worker, false, null);
        $job->fibonacci(1);
        self::assertNotNull($job->getId(), 'Job id should be generated');
        $job->setStatus(BaseJob::STATUS_RUNNING);
        $job->setLocked(true);
        $time = time();
        $date = new \DateTime("@$time");
        $job->setLockedAt($date);
        $id = $job->getId();
        $job = $jobManager->getRepository()->find($id);

        self::assertNotNull($job);

        $runClass = $jobManager->getRunClass();

        $objectManager = $jobManager->getObjectManager();
        $run = new $runClass();
        $objectManager->persist($run);
        $objectManager->flush();
        $runId = $run->getId();
        self::assertNotNull($runId);
        $job->setRunId($runId);
        $objectManager->persist($job);
        $objectManager->flush();
        $objectManager->remove($run);
        $objectManager->flush();
        $id = $job->getId();
        $job = $jobManager->getRepository()->find($id);

        self::assertNotNull($job);

        $archivedRun = $objectManager->getRepository($jobManager->getRunArchiveClass())->find($runId);

        $minusTime = $time - (BaseJobManager::STALLED_SECONDS + 1);
        $archivedRun->setEndedAt(new \DateTime("@$minusTime"));

        $objectManager->persist($archivedRun);
        $objectManager->flush();

        $count = $jobManager->pruneStalledJobs('asdf');
        self::assertEquals(0, $count);
        $count = $jobManager->pruneStalledJobs(null, 'asdf');
        self::assertEquals(0, $count);
        $count = $jobManager->pruneStalledJobs('fibonacci', 'asdf');
        self::assertEquals(0, $count);
        $count = $jobManager->pruneStalledJobs('fibonacci', 'fibonacci');
        self::assertEquals(1, $count);

        $id = $job->getId();
        $job = $jobManager->getRepository()->find($id);

        self::assertNull($job);

        $archivedJob = $jobManager->getObjectManager()->getRepository($jobManager->getArchiveObjectName())->find($id);

        self::assertNotNull($archivedJob);
        self::assertEquals(BaseJob::STATUS_ERROR, $archivedJob->getStatus());
        $objectManager->remove($archivedJob);
        $objectManager->flush();

        // multiple

        /** @var JobManager|\Dtc\QueueBundle\ORM\JobManager $jobManager */
        $jobManager = self::$jobManager;

        $job = new self::$jobClass(self::$worker, false, null);
        $job->fibonacci(1);
        self::assertNotNull($job->getId(), 'Job id should be generated');
        $job->setStatus(BaseJob::STATUS_RUNNING);
        $job->setLocked(true);
        $time = time();
        $date = new \DateTime("@$time");
        $job->setLockedAt($date);
        $id = $job->getId();
        $job = $jobManager->getRepository()->find($id);

        self::assertNotNull($job);

        $runClass = $jobManager->getRunClass();

        $objectManager = $jobManager->getObjectManager();
        $run = new $runClass();
        $objectManager->persist($run);
        $objectManager->flush();
        $runId = $run->getId();
        self::assertNotNull($runId);
        $job->setRunId($runId);
        $objectManager->persist($job);
        $objectManager->flush();
        $objectManager->remove($run);
        $objectManager->flush();
        $id = $job->getId();
        $job = $jobManager->getRepository()->find($id);

        self::assertNotNull($job);

        $archivedRun = $objectManager->getRepository($jobManager->getRunArchiveClass())->find($runId);

        $minusTime = $time - (BaseJobManager::STALLED_SECONDS + 1);
        $archivedRun->setEndedAt(new \DateTime("@$minusTime"));

        $objectManager->persist($archivedRun);
        $objectManager->flush();

        $job = new self::$jobClass(self::$worker, false, null);
        $job->fibonacci(1);
        self::assertNotNull($job->getId(), 'Job id should be generated');
        $job->setStatus(BaseJob::STATUS_RUNNING);
        $job->setLocked(true);
        $time = time();
        $date = new \DateTime("@$time");
        $job->setLockedAt($date);
        $id = $job->getId();
        $job = $jobManager->getRepository()->find($id);

        self::assertNotNull($job);

        $runClass = $jobManager->getRunClass();

        $objectManager = $jobManager->getObjectManager();
        $run = new $runClass();
        $objectManager->persist($run);
        $objectManager->flush();
        $runId = $run->getId();
        self::assertNotNull($runId);
        $job->setRunId($runId);
        $objectManager->persist($job);
        $objectManager->flush();
        $objectManager->remove($run);
        $objectManager->flush();
        $id = $job->getId();
        $job = $jobManager->getRepository()->find($id);

        self::assertNotNull($job);

        $archivedRun = $objectManager->getRepository($jobManager->getRunArchiveClass())->find($runId);

        $minusTime = $time - (BaseJobManager::STALLED_SECONDS + 1);
        $archivedRun->setEndedAt(new \DateTime("@$minusTime"));

        $objectManager->persist($archivedRun);
        $objectManager->flush();
        $count = $jobManager->pruneStalledJobs();
        self::assertEquals(2, $count);
    }

    public function testPruneExpriedJobs()
    {
        /** @var JobManager|\Dtc\QueueBundle\ORM\JobManager $jobManager */
        $jobManager = self::$jobManager;
        $objectManager = $jobManager->getObjectManager();

        $job = new self::$jobClass(self::$worker, false, null);
        $job->fibonacci(1);
        self::assertNotNull($job->getId(), 'Job id should be generated');
        $time = time() - 1;
        $date = new \DateTime("@$time");
        $job->setExpiresAt($date);
        $objectManager->persist($job);
        $objectManager->flush();

        $count = $jobManager->pruneExpiredJobs('asdf');
        self::assertEquals(0, $count);
        $count = $jobManager->pruneExpiredJobs(null, 'asdf');
        self::assertEquals(0, $count);
        $count = $jobManager->pruneExpiredJobs(null, 'fibonacci');
        self::assertEquals(1, $count);

        $job = new self::$jobClass(self::$worker, false, null);
        $job->fibonacci(1);
        self::assertNotNull($job->getId(), 'Job id should be generated');
        $time = time() - 1;
        $date = new \DateTime("@$time");
        $job->setExpiresAt($date);
        $objectManager->persist($job);
        $objectManager->flush();

        $job = new self::$jobClass(self::$worker, false, null);
        $job->fibonacci(1);
        self::assertNotNull($job->getId(), 'Job id should be generated');
        $time = time() - 1;
        $date = new \DateTime("@$time");
        $job->setExpiresAt($date);
        $objectManager->persist($job);
        $objectManager->flush();

        $count = $jobManager->pruneExpiredJobs(null, 'fibonacci');
        self::assertEquals(2, $count);

        $job = new self::$jobClass(self::$worker, false, null);
        $job->fibonacci(1);
        self::assertNotNull($job->getId(), 'Job id should be generated');
        $time = time() - 1;
        $date = new \DateTime("@$time");
        $job->setExpiresAt($date);
        $objectManager->persist($job);
        $objectManager->flush();

        $job = new self::$jobClass(self::$worker, false, null);
        $job->fibonacci(1);
        self::assertNotNull($job->getId(), 'Job id should be generated');
        $time = time() - 1;
        $date = new \DateTime("@$time");
        $job->setExpiresAt($date);
        $objectManager->persist($job);
        $objectManager->flush();

        $count = $jobManager->pruneExpiredJobs('fibonacci', 'fibonacci');
        self::assertEquals(2, $count);

        $job = new self::$jobClass(self::$worker, false, null);
        $job->fibonacci(1);
        self::assertNotNull($job->getId(), 'Job id should be generated');
        $time = time() - 1;
        $date = new \DateTime("@$time");
        $job->setExpiresAt($date);
        $objectManager->persist($job);
        $objectManager->flush();

        $job = new self::$jobClass(self::$worker, false, null);
        $job->fibonacci(1);
        self::assertNotNull($job->getId(), 'Job id should be generated');
        $time = time() - 1;
        $date = new \DateTime("@$time");
        $job->setExpiresAt($date);
        $objectManager->persist($job);
        $objectManager->flush();

        $count = $jobManager->pruneExpiredJobs('fibonacci');
        self::assertEquals(2, $count);

        $job = new self::$jobClass(self::$worker, false, null);
        $job->fibonacci(1);
        self::assertNotNull($job->getId(), 'Job id should be generated');
        $time = time() - 1;
        $date = new \DateTime("@$time");
        $job->setExpiresAt($date);
        $objectManager->persist($job);
        $objectManager->flush();

        $job = new self::$jobClass(self::$worker, false, null);
        $job->fibonacci(1);
        self::assertNotNull($job->getId(), 'Job id should be generated');
        $time = time() - 1;
        $date = new \DateTime("@$time");
        $job->setExpiresAt($date);
        $objectManager->persist($job);
        $objectManager->flush();

        $count = $jobManager->pruneExpiredJobs();
        self::assertEquals(2, $count);
    }

    public function testPruneArchivedJobs()
    {
        /** @var JobManager|\Dtc\QueueBundle\ORM\JobManager $jobManager */
        $jobManager = self::$jobManager;
        $objectManager = $jobManager->getObjectManager();
        $jobArchiveClass = $jobManager->getArchiveObjectName();
        $jobArchiveRepository = $objectManager->getRepository($jobArchiveClass);

        $job = new self::$jobClass(self::$worker, false, null);
        $job->fibonacci(1);
        $objectManager->remove($job);
        $objectManager->flush();

        $jobArchive = $jobArchiveRepository->find($job->getId());
        self::assertNotNull($jobArchive);
        $time = time() - 86401;
        $jobArchive->setUpdatedAt(new \DateTime("@$time"));
        $objectManager->persist($jobArchive);
        $objectManager->flush();

        $older = $time + 1;
        $count = $jobManager->pruneArchivedJobs(new \DateTime("@$time"));
        self::assertEquals(0, $count);
        $count = $jobManager->pruneArchivedJobs(new \DateTime("@$older"));
        self::assertEquals(1, $count);

        $job = new self::$jobClass(self::$worker, false, null);
        $job->fibonacci(1);
        $objectManager->remove($job);
        $objectManager->flush();

        $jobArchive = $jobArchiveRepository->find($job->getId());
        self::assertNotNull($jobArchive);
        $time = time() - 86401;
        $jobArchive->setUpdatedAt(new \DateTime("@$time"));
        $objectManager->persist($jobArchive);
        $objectManager->flush();

        $job = new self::$jobClass(self::$worker, false, null);
        $job->fibonacci(1);
        $objectManager->remove($job);
        $objectManager->flush();

        $jobArchive = $jobArchiveRepository->find($job->getId());
        self::assertNotNull($jobArchive);
        $jobArchive->setUpdatedAt(new \DateTime("@$time"));
        $objectManager->persist($jobArchive);
        $objectManager->flush();
        $older = $time + 1;
        $count = $jobManager->pruneArchivedJobs(new \DateTime("@$time"));
        self::assertEquals(0, $count);
        $count = $jobManager->pruneArchivedJobs(new \DateTime("@$older"));
        self::assertEquals(2, $count);
    }

    public function testPerformance()
    {
        $jobs = self::$jobManager->getRepository()->findAll();
        foreach ($jobs as $job) {
            self::$jobManager->getObjectManager()->remove($job);
        }
        self::$jobManager->getObjectManager()->flush();

        self::$jobManager->getObjectManager()->clear();
        parent::testPerformance();
    }
}
