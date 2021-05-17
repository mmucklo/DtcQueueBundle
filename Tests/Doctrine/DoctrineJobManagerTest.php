<?php

namespace Dtc\QueueBundle\Tests\Doctrine;

use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ORM\EntityManager;
use Dtc\QueueBundle\Doctrine\DoctrineJobManager;
use Dtc\QueueBundle\Doctrine\DtcQueueListener;
use Dtc\QueueBundle\Manager\StallableJobManager;
use Dtc\QueueBundle\Model\BaseJob;
use Dtc\QueueBundle\Model\Job;
use Dtc\QueueBundle\Model\RetryableJob;
use Dtc\QueueBundle\Model\StallableJob;
use Dtc\QueueBundle\ODM\JobManager;
use Dtc\QueueBundle\Tests\FibonacciWorker;
use Dtc\QueueBundle\Tests\Manager\AutoRetryTrait;
use Dtc\QueueBundle\Tests\Manager\BaseJobManagerTest;
use Dtc\QueueBundle\Tests\Manager\PriorityTestTrait;
use Dtc\QueueBundle\Tests\Manager\RetryableTrait;
use Dtc\QueueBundle\Tests\ORM\JobManagerTest;
use Dtc\QueueBundle\Util\Util;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

/**
 * @author David
 *
 * This test requires local mongodb running
 */
abstract class DoctrineJobManagerTest extends BaseJobManagerTest
{
    use AutoRetryTrait;
    use PriorityTestTrait;
    use RetryableTrait;

    protected static $dtcQueueListener;

    /** @var DocumentManager|EntityManager */
    protected static $objectManager;
    protected static $objectName;
    protected static $archiveObjectName;
    protected static $runClass;
    protected static $runArchiveClass;
    protected static $jobTimingClass;
    protected static $jobManagerClass;
    protected static $runManagerClass;
    protected static $jobTimingManagerClass;
    public static $container;
    public static $runManager;

    public static function setUpBeforeClass(): void
    {
        self::$jobTimingManager = new self::$jobTimingManagerClass(self::$objectManager, self::$jobTimingClass, true);
        self::$runManager = new self::$runManagerClass(self::$objectManager, self::$runClass, self::$runArchiveClass);
        /** @var JobManager|\Dtc\QueueBundle\ORM\JobManager $jobManager */
        $jobManager = new self::$jobManagerClass(self::$runManager, self::$jobTimingManager, self::$objectManager, self::$objectName, self::$archiveObjectName);
        self::$jobManager = $jobManager;
        $jobManager->setMaxPriority(255);

        self::assertEquals(255, $jobManager->getMaxPriority());
        self::assertEquals(JobManager::PRIORITY_DESC, $jobManager->getPriorityDirection());
        $jobManager->setPriorityDirection(JobManager::PRIORITY_ASC);
        self::assertEquals(JobManager::PRIORITY_ASC, $jobManager->getPriorityDirection());
        $jobManager->setPriorityDirection(JobManager::PRIORITY_DESC);

        $parameters = new ParameterBag();

        $container = new Container($parameters);
        $container->set('dtc_queue.manager.job', $jobManager);
        $container->set('dtc_queue.manager.run', self::$runManager);
        self::$container = $container;
        self::$dtcQueueListener = new DtcQueueListener($jobManager->getJobArchiveClass(), self::$runManager->getRunArchiveClass(), false);
        self::$objectManager->getEventManager()->addEventListener('preUpdate', self::$dtcQueueListener);
        self::$objectManager->getEventManager()->addEventListener('prePersist', self::$dtcQueueListener);
        self::$objectManager->getEventManager()->addEventListener('preRemove', self::$dtcQueueListener);

        self::$worker = new FibonacciWorker();

        parent::setUpBeforeClass();
    }

    public static function tearDownAfterClass()
    {
        self::$objectManager->getEventManager()->removeEventListener('preUpdate', self::$dtcQueueListener);
        self::$objectManager->getEventManager()->removeEventListener('prePersist', self::$dtcQueueListener);
        self::$objectManager->getEventManager()->removeEventListener('preRemove', self::$dtcQueueListener);
        parent::tearDownAfterClass();
    }

    public function testOrdering()
    {
        // priority when at
        /** @var DoctrineJobManager $jobManager */
        $jobManager = self::$jobManager;

        $time1 = time() - 2;
        $dateTime1 = new \DateTime("@$time1");

        $time2 = time();
        $dateTime2 = new \DateTime("@$time2");

        /** @var Job $job */
        $job = new static::$jobClass(static::$worker, false, null);
        $job->fibonacci(1);
        $job->setWhenAt($dateTime1);
        $job->setPriority(3);
        $id = $job->getId();

        $job2 = new static::$jobClass(static::$worker, false, null);
        $job2->setPriority(1);
        $job2->setWhenAt($dateTime2);
        $job2->fibonacci(1);
        $id2 = $job2->getId();

        $job3 = new static::$jobClass(static::$worker, false, null);
        $job3->setPriority(1);
        $job3->setWhenAt($dateTime1);
        $job3->fibonacci(1);
        $id3 = $job3->getId();

        $job4 = new static::$jobClass(static::$worker, false, null);
        $job4->setPriority(1);
        $job4->setWhenAt($dateTime2);
        $job4->fibonacci(1);
        $id4 = $job4->getId();

        $nextJob = $jobManager->getJob();
        static::assertEquals($id3, $nextJob->getId());
        $nextNextJob = $jobManager->getJob();
        $nextNextId = $nextNextJob->getId();
        static::assertTrue($id4 == $nextNextId || $id2 == $nextNextId, "$nextNextId not equals $id4 or $id2, could be $id or $id3");

        static::assertNotNull($jobManager->getJob());
        static::assertNotNull($jobManager->getJob());

        // non-priority when at
        $time1 = time() - 2;
        $dateTime1 = new \DateTime("@$time1");

        $time2 = time();
        $dateTime2 = new \DateTime("@$time2");

        /** @var Job $job */
        $job = new static::$jobClass(static::$worker, false, null);
        $job->fibonacci(1);
        $job->setWhenAt($dateTime1);
        $job->setPriority(3);
        $id = $job->getId();

        $job2 = new static::$jobClass(static::$worker, false, null);
        $job2->setPriority(1);
        $job2->setWhenAt($dateTime2);
        $job2->fibonacci(1);

        $job3 = new static::$jobClass(static::$worker, false, null);
        $job3->setPriority(1);
        $job3->setWhenAt($dateTime2);
        $job3->fibonacci(1);

        $job4 = new static::$jobClass(static::$worker, false, null);
        $job4->setPriority(1);
        $job4->setWhenAt($dateTime2);
        $job4->fibonacci(1);

        $nextJob = $jobManager->getJob(null, null, false);
        static::assertEquals($id, $nextJob->getId());
        static::assertNotNull($jobManager->getJob());
        static::assertNotNull($jobManager->getJob());
        static::assertNotNull($jobManager->getJob());
    }

    public function getJobBy()
    {
        /** @var DoctrineJobManager $jobManager */
        $jobManager = self::$jobManager;

        /** @var Job $job */
        $job = new static::$jobClass(static::$worker, false, null);
        $job->fibonacci(1);
        $id = $job->getId();
        $nextJob = $jobManager->getJob('fibonacci', null);
        static::assertNotNull($nextJob);
        static::assertEquals($id, $nextJob->getId());

        /** @var Job $job */
        $job = new static::$jobClass(static::$worker, false, null);
        $job->fibonacci(1);
        $id = $job->getId();
        $nextJob = $jobManager->getJob('fibonacci', 'fibonacci');
        static::assertNotNull($nextJob);
        static::assertEquals($id, $nextJob->getId());

        /** @var Job $job */
        $job = new static::$jobClass(static::$worker, false, null);
        $job->fibonacci(1);
        $id = $job->getId();
        $nextJob = $jobManager->getJob(null, 'fibonacci');
        static::assertNotNull($nextJob);
        static::assertEquals($id, $nextJob->getId());

        /** @var Job $job */
        $job = new static::$jobClass(static::$worker, false, null);
        $job->fibonacci(1);
        $id = $job->getId();
        $nextJob = $jobManager->getJob(null, 'fibonaccia');
        static::assertNull($nextJob);
        $nextJob = $jobManager->getJob('fibonacci', 'fibonaccia');
        static::assertNull($nextJob);
        $nextJob = $jobManager->getJob('fibonaccii', 'fibonacci');
        static::assertNull($nextJob);
        $nextJob = $jobManager->getJob();
        static::assertNotNull($nextJob);
        static::assertEquals($id, $nextJob->getId());
    }

    public function testDeleteJob()
    {
        /** @var JobManager|\Dtc\QueueBundle\ORM\JobManager $jobManager */
        $jobManager = self::$jobManager;

        /** @var Job $job */
        $job = $this->getJob();
        $id = $job->getId();
        $jobManager->deleteJob($job);

        $nextJob = $jobManager->getJob(null, null, true);
        self::assertNull($nextJob, "Shouldn't be any jobs left in queue");

        $archiveObjectName = $jobManager->getJobArchiveClass();

        self::assertNotNull($id);
        $archiveRepository = $jobManager->getObjectManager()->getRepository($archiveObjectName);
        $result = $archiveRepository->find($id);
        self::assertNotNull($result);
        self::assertEquals($id, $result->getId());
    }

    public function testCountLiveJobs()
    {
        $this->drain();

        /** @var JobManager|\Dtc\QueueBundle\ORM\JobManager $jobManager */
        $jobManager = self::$jobManager;

        $this->getJob();

        $count = $jobManager->countLiveJobs();
        self::assertEquals(1, $count);

        $this->getJob();

        $count = $jobManager->countLiveJobs();
        self::assertEquals(2, $count);

        $this->getJob();

        $count = $jobManager->countLiveJobs();
        self::assertEquals(3, $count);

        $count = $jobManager->countLiveJobs('asdf');
        self::assertEquals(0, $count);

        $count = $jobManager->countLiveJobs('fibonacci');
        self::assertEquals(3, $count);

        $count = $jobManager->countLiveJobs('fibonacci', 'test');
        self::assertEquals(0, $count);

        $count = $jobManager->countLiveJobs('fibonacci', 'fibonacci');
        self::assertEquals(3, $count);

        $this->drain();
    }

    public function testArchiveAllJobs()
    {
        $this->drain();

        /** @var JobManager|\Dtc\QueueBundle\ORM\JobManager $jobManager */
        $jobManager = self::$jobManager;

        $this->getJob();

        $count = $jobManager->countLiveJobs();
        $archiveCount = $this->runCountQuery($jobManager->getJobArchiveClass());
        self::assertEquals(1, $count);
        $allCount = $this->runCountQuery($jobManager->getJobClass());
        $counter = 0;
        $countJobs = function ($count) use (&$counter) {
            $counter += $count;
        };
        $jobManager->archiveAllJobs(null, null, $countJobs);
        self::assertEquals(0, $jobManager->countLiveJobs());
        self::assertEquals($allCount - 1, $this->runCountQuery($jobManager->getJobClass()));
        self::assertEquals($archiveCount + 1, $this->runCountQuery($jobManager->getJobArchiveClass()));
        self::assertEquals(1, $counter);

        $this->getJob();
        $this->getJob();

        $count = $jobManager->countLiveJobs();
        self::assertEquals(2, $count);
        $archiveCount = $this->runCountQuery($jobManager->getJobArchiveClass());
        $counter = 0;
        $jobManager->archiveAllJobs('fibonacci', null, $countJobs);
        self::assertEquals(0, $jobManager->countLiveJobs());
        self::assertEquals(2, $counter);
        self::assertEquals($archiveCount + 2, $this->runCountQuery($jobManager->getJobArchiveClass()));

        $this->getJob();
        $this->getJob();

        $count = $jobManager->countLiveJobs();
        self::assertEquals(2, $count);

        $jobManager->archiveAllJobs('fibonacc', null, $countJobs);
        self::assertEquals(2, $jobManager->countLiveJobs());

        $jobManager->archiveAllJobs('fibonacci', 'fibo', $countJobs);
        self::assertEquals(2, $jobManager->countLiveJobs());

        $jobManager->archiveAllJobs('fibonacci', 'fibonacci', $countJobs);
        self::assertEquals(0, $jobManager->countLiveJobs());

        $this->drain();
    }

    abstract protected function runCountQuery($class);

    public function testResetExceptionJobs()
    {
        /** @var JobManager|\Dtc\QueueBundle\ORM\JobManager $jobManager */
        $jobManager = self::$jobManager;

        $id = $this->createExceptionJob();
        $archiveObjectName = $jobManager->getJobArchiveClass();
        $objectManager = $jobManager->getObjectManager();
        $archiveRepository = $objectManager->getRepository($archiveObjectName);
        $result = $archiveRepository->find($id);
        self::assertNotNull($result);
        self::assertEquals(BaseJob::STATUS_EXCEPTION, $result->getStatus());
        if ($objectManager instanceof EntityManager) {
            JobManagerTest::createObjectManager();
            $jobManager = new self::$jobManagerClass(self::$runManager, self::$jobTimingManager, self::$objectManager, self::$objectName, self::$archiveObjectName);
            $jobManager->getObjectManager()->clear();
            $objectManager = $jobManager->getObjectManager();
        }

        $count = $jobManager->resetExceptionJobs();

        self::assertEquals(1, $count);
        $repository = $jobManager->getRepository();
        $job = $repository->find($id);

        self::assertNotNull($job);
        self::assertEquals(BaseJob::STATUS_NEW, $job->getStatus());
        self::assertNull($job->getStartedAt());
        self::assertNull($job->getFinishedAt());
        self::assertNull($job->getElapsed());
        self::assertNull($job->getMessage());

        $objectManager->remove($job);
        $objectManager->flush();

        $id = $this->createExceptionJob();
        $archiveObjectName = $jobManager->getJobArchiveClass();
        $objectManager = $jobManager->getObjectManager();
        $archiveRepository = $objectManager->getRepository($archiveObjectName);
        $result = $archiveRepository->find($id);
        $result->setMaxRetries(10);
        $result->setRetries(10);
        $objectManager->persist($result);
        $objectManager->flush();
        $count = $jobManager->resetExceptionJobs();
        self::assertEquals(0, $count);
        $job = $repository->find($id);
        self::assertNull($job);
        $job = $archiveRepository->find($id);
        self::assertNotNull($job);
        $objectManager->remove($job);
        $objectManager->flush();
    }

    protected function createExceptionJob()
    {
        /** @var JobManager|\Dtc\QueueBundle\ORM\JobManager $jobManager */
        $jobManager = self::$jobManager;

        /** @var Job $job */
        $job = $this->getJob();
        $id = $job->getId();
        $jobManager->deleteJob($job);

        /** @var JobManager|\Dtc\QueueBundle\ORM\JobManager $jobManager */
        $archiveObjectName = $jobManager->getJobArchiveClass();

        $objectManager = $jobManager->getObjectManager();

        $archiveRepository = $objectManager->getRepository($archiveObjectName);
        $result = $archiveRepository->find($id);
        self::assertNotNull($result);
        self::assertEquals($id, $result->getId());

        $result->setStatus(BaseJob::STATUS_EXCEPTION);
        $result->setStartedAt(Util::getMicrotimeDateTime());
        $result->setFinishedAt(new \DateTime());
        $result->setElapsed(12345);
        $result->setMessage('soomething');
        $objectManager->persist($result);
        $objectManager->flush();

        return $id;
    }

    /**
     * @param bool $endRun
     * @param bool $setId
     *
     * @return mixed
     */
    public function createStalledJob($endRun, $setId)
    {
        /** @var JobManager|\Dtc\QueueBundle\ORM\JobManager $jobManager */
        $jobManager = self::$jobManager;

        $job = new self::$jobClass(self::$worker, false, null);
        $job->fibonacci(1);
        self::assertNotNull($job->getId(), 'Job id should be generated');
        $job->setStatus(BaseJob::STATUS_RUNNING);
        $time = time();
        $date = new \DateTime("@$time");
        $job->setStartedAt($date);
        $id = $job->getId();
        $job = $jobManager->getRepository()->find($id);

        self::assertNotNull($job);

        $runClass = self::$runManager->getRunClass();

        $objectManager = $jobManager->getObjectManager();
        $run = new $runClass();
        $run->setLastHeartbeatAt(new \DateTime());
        if ($setId) {
            $run->setCurrentJobId($job->getId());
        }
        $objectManager->persist($run);
        $objectManager->flush();
        $runId = $run->getId();
        self::assertNotNull($runId);
        $job->setRunId($runId);
        $objectManager->persist($job);
        $objectManager->flush();
        if ($endRun) {
            $objectManager->remove($run);
            $objectManager->flush();
        }
        $id = $job->getId();
        $job = $jobManager->getRepository()->find($id);

        self::assertNotNull($job);

        if ($endRun) {
            $archivedRun = $objectManager->getRepository(self::$runManager->getRunArchiveClass())->find($runId);

            $minusTime = $time - (DoctrineJobManager::STALLED_SECONDS + 1);
            $archivedRun->setEndedAt(new \DateTime("@$minusTime"));

            $objectManager->persist($archivedRun);
            $objectManager->flush();
        }
        $id = $job->getId();

        return $id;
    }

    public function testResetStalledJobs()
    {
        $this->drain();

        /** @var JobManager|\Dtc\QueueBundle\ORM\JobManager $jobManager */
        $jobManager = self::$jobManager;
        $jobManager->pruneStalledJobs();

        $id = $this->createStalledJob(true, false);

        $objectManager = $jobManager->getObjectManager();
        $count = $jobManager->resetStalledJobs();
        self::assertEquals(1, $count);

        $job = $jobManager->getRepository()->find($id);

        self::assertNotNull($job);
        self::assertEquals(BaseJob::STATUS_NEW, $job->getStatus());
        self::assertNull($job->getStartedAt());
        self::assertNull($job->getFinishedAt());
        self::assertNull($job->getElapsed());
        self::assertNull($job->getMessage());
        self::assertEquals(1, $job->getStalls());

        $objectManager->remove($job);
        $objectManager->flush();

        /** @var JobManager|\Dtc\QueueBundle\ORM\JobManager $jobManager */
        $jobManager = self::$jobManager;
        $id = $this->createStalledJob(true, true);

        $objectManager = $jobManager->getObjectManager();
        $count = $jobManager->resetStalledJobs();
        self::assertEquals(1, $count);

        $job = $jobManager->getRepository()->find($id);

        self::assertNotNull($job);
        $objectManager->remove($job);
        $objectManager->flush();

        /** @var JobManager|\Dtc\QueueBundle\ORM\JobManager $jobManager */
        $id = $this->createStalledJob(false, false);

        $objectManager = $jobManager->getObjectManager();
        $count = $jobManager->resetStalledJobs();
        self::assertEquals(1, $count);

        $job = $jobManager->getRepository()->find($id);

        self::assertNotNull($job);
        self::assertEquals(BaseJob::STATUS_NEW, $job->getStatus());
        self::assertNull($job->getStartedAt());
        self::assertNull($job->getFinishedAt());
        self::assertNull($job->getElapsed());
        self::assertNull($job->getMessage());
        self::assertEquals(1, $job->getStalls());

        $objectManager->remove($job);
        $objectManager->flush();

        /** @var JobManager|\Dtc\QueueBundle\ORM\JobManager $jobManager */
        $jobManager = self::$jobManager;
        $id = $this->createStalledJob(false, true);

        $job = $jobManager->getRepository()->find($id);
        $objectManager = $jobManager->getObjectManager();
        $count = $jobManager->resetStalledJobs();
        self::assertEquals(0, $count);

        $objectManager->remove($job);
        $objectManager->flush();

        $id = $this->createStalledJob(true, false);
        $job = $jobManager->getRepository()->find($id);
        $job->setMaxRetries(10);
        $job->setRetries(10);
        $objectManager->persist($job);
        $objectManager->flush();

        $count = $jobManager->resetStalledJobs();
        self::assertEquals(0, $count);
        $job = $jobManager->getRepository()->find($id);
        self::assertNull($job);
        $job = $objectManager->getRepository($jobManager->getJobArchiveClass())->find($id);
        self::assertNotNull($job);
        $objectManager->remove($job);
        $objectManager->flush();

        $id = $this->createStalledJob(true, false);
        $job = $jobManager->getRepository()->find($id);
        $job->setMaxStalls(10);
        $job->setStalls(10);
        $objectManager->persist($job);
        $objectManager->flush();

        $count = $jobManager->resetStalledJobs();
        self::assertEquals(0, $count);
        $job = $jobManager->getRepository()->find($id);
        self::assertNull($job);
        $job = $objectManager->getRepository($jobManager->getJobArchiveClass())->find($id);
        self::assertNotNull($job);
        $objectManager->remove($job);
        $objectManager->flush();
    }

    public function testpruneExceptionJobs()
    {
        $job = $this->getJob();
        $id = $job->getId();

        /** @var JobManager|\Dtc\QueueBundle\ORM\JobManager $jobManager */
        $jobManager = self::$jobManager;
        $jobManager->deleteJob($job);
        $archiveObjectName = $jobManager->getJobArchiveClass();

        $objectManager = $jobManager->getObjectManager();

        $archiveRepository = $objectManager->getRepository($archiveObjectName);
        $result = $archiveRepository->find($id);
        self::assertNotNull($result);
        self::assertEquals($id, $result->getId());

        $result->setStatus(BaseJob::STATUS_EXCEPTION);
        $result->setStartedAt(new \DateTime());
        $result->setFinishedAt(new \DateTime());
        $result->setElapsed(12345);
        $result->setMessage('soomething');
        $objectManager->persist($result);
        $objectManager->flush();

        $count = $jobManager->pruneExceptionJobs('asdf');
        self::assertEquals(0, $count);
        $count = $jobManager->pruneExceptionJobs(null, 'asdf');
        self::assertEquals(0, $count);
        $count = $jobManager->pruneExceptionJobs('fibonacci', 'asdf');
        self::assertEquals(0, $count);
        $count = $jobManager->pruneExceptionJobs('fibonacci', 'asdf');
        self::assertEquals(0, $count);
        $count = $jobManager->pruneExceptionJobs('fibonacci', 'fibonacci');
        self::assertEquals(1, $count);
        $repository = $jobManager->getRepository();
        $job = $repository->find($id);
        $objectManager->clear();
        self::assertNull($job);
        $archiveJob = $archiveRepository->find($id);
        self::assertNull($archiveJob);

        $job = $this->getJob();
        $id = $job->getId();
        $objectManager->remove($job);
        $objectManager->flush();
        /** @var JobManager|\Dtc\QueueBundle\ORM\JobManager $jobManager */
        $jobManager = self::$jobManager;
        $archiveObjectName = $jobManager->getJobArchiveClass();

        $objectManager = $jobManager->getObjectManager();

        $archiveRepository = $objectManager->getRepository($archiveObjectName);
        $result = $archiveRepository->find($id);
        self::assertNotNull($result);
        self::assertEquals($id, $result->getId());

        $result->setStatus(BaseJob::STATUS_EXCEPTION);
        $result->setStartedAt(new \DateTime());
        $result->setFinishedAt(new \DateTime());
        $result->setElapsed(12345);
        $result->setMessage('soomething');
        $objectManager->persist($result);
        $objectManager->flush();

        $job = $this->getJob();
        $id = $job->getId();
        $objectManager->remove($job);
        $objectManager->flush();

        /** @var JobManager|\Dtc\QueueBundle\ORM\JobManager $jobManager */
        $jobManager = self::$jobManager;
        $archiveObjectName = $jobManager->getJobArchiveClass();
        $objectManager = $jobManager->getObjectManager();

        $archiveRepository = $objectManager->getRepository($archiveObjectName);
        $result = $archiveRepository->find($id);
        self::assertNotNull($result);
        self::assertEquals($id, $result->getId());

        $result->setStatus(BaseJob::STATUS_EXCEPTION);
        $result->setStartedAt(new \DateTime());
        $result->setFinishedAt(new \DateTime());
        $result->setElapsed(12345);
        $result->setMessage('soomething');
        $objectManager->persist($result);
        $objectManager->flush();
        $count = $jobManager->pruneExceptionJobs();
        self::assertEquals(2, $count);
    }

    public function testPruneStalledJobs()
    {
        static::setUpBeforeClass();

        /** @var JobManager|\Dtc\QueueBundle\ORM\JobManager $jobManager */
        $jobManager = self::$jobManager;

        $job = new self::$jobClass(self::$worker, false, null);
        $job->fibonacci(1);
        self::assertNotNull($job->getId(), 'Job id should be generated');
        $job->setStatus(BaseJob::STATUS_RUNNING);
        $time = time();
        $date = new \DateTime("@$time");
        $job->setStartedAt($date);
        $id = $job->getId();
        $job = $jobManager->getRepository()->find($id);

        self::assertNotNull($job);

        $runClass = self::$runManager->getRunClass();

        $objectManager = $jobManager->getObjectManager();
        $run = new $runClass();
        $run->setLastHeartbeatAt(new \DateTime());
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

        $archivedRun = $objectManager->getRepository(self::$runManager->getRunArchiveClass())->find($runId);

        $minusTime = $time - (DoctrineJobManager::STALLED_SECONDS + 1);
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

        $job = $jobManager->getRepository()->find($id);

        self::assertNull($job);

        $archivedJob = $jobManager->getObjectManager()->getRepository($jobManager->getJobArchiveClass())->find($id);

        self::assertNotNull($archivedJob);
        self::assertEquals(StallableJob::STATUS_STALLED, $archivedJob->getStatus());
        self::assertEquals(1, $archivedJob->getStalls());
        $objectManager->remove($archivedJob);
        $objectManager->flush();

        // multiple

        /** @var JobManager|\Dtc\QueueBundle\ORM\JobManager $jobManager */
        $jobManager = self::$jobManager;

        $job = new self::$jobClass(self::$worker, false, null);
        $job->fibonacci(1);
        self::assertNotNull($job->getId(), 'Job id should be generated');
        $job->setStatus(BaseJob::STATUS_RUNNING);
        $time = time();
        $date = new \DateTime("@$time");
        $job->setStartedAt($date);
        $id = $job->getId();
        $job = $jobManager->getRepository()->find($id);

        self::assertNotNull($job);

        $runClass = self::$runManager->getRunClass();

        $objectManager = $jobManager->getObjectManager();
        $run = new $runClass();
        $run->setLastHeartbeatAt(new \DateTime());
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

        $archivedRun = $objectManager->getRepository(self::$runManager->getRunArchiveClass())->find($runId);

        $minusTime = $time - (DoctrineJobManager::STALLED_SECONDS + 1);
        $archivedRun->setEndedAt(new \DateTime("@$minusTime"));

        $objectManager->persist($archivedRun);
        $objectManager->flush();

        $job = new self::$jobClass(self::$worker, false, null);
        $job->fibonacci(1);
        self::assertNotNull($job->getId(), 'Job id should be generated');
        $job->setStatus(BaseJob::STATUS_RUNNING);
        $time = time();
        $date = new \DateTime("@$time");
        $job->setStartedAt($date);
        $id = $job->getId();
        $job = $jobManager->getRepository()->find($id);

        self::assertNotNull($job);

        $runClass = self::$runManager->getRunClass();

        $objectManager = $jobManager->getObjectManager();
        $run = new $runClass();
        $run->setLastHeartbeatAt(new \DateTime());
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

        $archivedRun = $objectManager->getRepository(self::$runManager->getRunArchiveClass())->find($runId);

        $minusTime = $time - (DoctrineJobManager::STALLED_SECONDS + 1);
        $archivedRun->setEndedAt(new \DateTime("@$minusTime"));

        $objectManager->persist($archivedRun);
        $objectManager->flush();
        $count = $jobManager->pruneStalledJobs();
        self::assertEquals(2, $count);
    }

    public function testBatchJobs()
    {
        /** @var JobManager|\Dtc\QueueBundle\ORM\JobManager $jobManager */
        $jobManager = self::$jobManager;

        $jobs = $jobManager->getRepository()->findAll();
        foreach ($jobs as $job) {
            $jobManager->getObjectManager()->remove($job);
        }
        $jobManager->getObjectManager()->flush();
        $jobManager->getObjectManager()->clear();

        /** @var JobManager|\Dtc\QueueBundle\ORM\JobManager $jobManager */
        $worker = self::$worker;
        $job1 = $worker->later()->fibonacci(1);
        $job2 = $worker->batchLater()->fibonacci(1);
        self::assertEquals($job1, $job2);

        $jobs = $jobManager->getRepository()->findAll();
        self::assertCount(1, $jobs);
        self::assertEquals($job1, $jobs[0]);
        self::assertNull($jobs[0]->getPriority());
        $jobManager->getObjectManager()->remove($jobs[0]);
        $jobManager->getObjectManager()->flush();
        $jobManager->getObjectManager()->clear();

        $job1 = $worker->later()->fibonacci(1);
        self::assertNull($job1->getPriority());
        $job2 = $worker->batchLater()->setPriority(3)->fibonacci(1);
        self::assertEquals($job1, $job2);
        self::assertNotNull($job2->getPriority());

        $jobs = $jobManager->getRepository()->findAll();
        self::assertCount(1, $jobs);
        self::assertEquals($job1, $jobs[0]);
        self::assertNotNull($jobs[0]->getPriority());

        // Not
        $jobs = $jobManager->getRepository()->findAll();
        foreach ($jobs as $job) {
            $jobManager->getObjectManager()->remove($job);
        }
        $jobManager->getObjectManager()->remove($jobs[0]);
        $jobManager->getObjectManager()->flush();
        $jobManager->getObjectManager()->clear();

        $job1 = $worker->later(100)->fibonacci(1);

        $time1 = new \DateTime('@'.time());
        $job2 = $worker->batchLater(0)->fibonacci(1);
        $time2 = Util::getDateTimeFromDecimalFormat(Util::getMicrotimeInteger());

        self::assertEquals($job1, $job2);
        self::assertGreaterThanOrEqual($time1, $job2->getWhenAt());
        self::assertLessThanOrEqual($time2, $job2->getWhenAt());

        $jobs = $jobManager->getRepository()->findAll();
        self::assertCount(1, $jobs);
        self::assertEquals($job1, $jobs[0]);
        self::assertGreaterThanOrEqual($time1, $jobs[0]->getWhenAt());
        self::assertLessThanOrEqual($time2, $jobs[0]->getWhenAt());
        $jobManager->getObjectManager()->remove($jobs[0]);
        $jobManager->getObjectManager()->flush();
        $jobManager->getObjectManager()->clear();

        $job1 = $worker->later(100)->setPriority(3)->fibonacci(1);
        $priority1 = $job1->getPriority();
        $time1 = Util::getDateTimeFromDecimalFormat(Util::getMicrotimeInteger());
        $job2 = $worker->batchLater(0)->setPriority(1)->fibonacci(1);
        $time2 = Util::getDateTimeFromDecimalFormat(Util::getMicrotimeInteger());
        self::assertEquals($job1, $job2);
        self::assertNotEquals($priority1, $job2->getPriority());

        self::assertEquals($job1, $job2);
        self::assertGreaterThanOrEqual($time1, $job2->getWhenAt());
        self::assertLessThanOrEqual($time2, $job2->getWhenAt());
    }

    public function testPruneExpiredJobs()
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

        $jobId1 = $job->getId();

        $job = new self::$jobClass(self::$worker, false, null);
        $job->fibonacci(1);
        self::assertNotNull($job->getId(), 'Job id should be generated');
        $time = time() - 1;
        $date = new \DateTime("@$time");
        $job->setExpiresAt($date);
        $objectManager->persist($job);
        $objectManager->flush();

        $jobId2 = $job->getId();

        $count = $jobManager->pruneExpiredJobs();
        self::assertEquals(2, $count);

        $archiveRepository = $jobManager->getObjectManager()->getRepository($jobManager->getJobArchiveClass());

        $job = $archiveRepository->find($jobId1);
        self::assertNotNull($job);
        self::assertEquals(Job::STATUS_EXPIRED, $job->getStatus());

        $job = $archiveRepository->find($jobId2);
        self::assertNotNull($job);
        self::assertEquals(Job::STATUS_EXPIRED, $job->getStatus());
    }

    public function testPruneArchivedJobs()
    {
        /** @var JobManager|\Dtc\QueueBundle\ORM\JobManager $jobManager */
        $jobManager = self::$jobManager;
        $objectManager = $jobManager->getObjectManager();
        $jobArchiveClass = $jobManager->getJobArchiveClass();
        $jobArchiveRepository = $objectManager->getRepository($jobArchiveClass);

        self::$objectManager->getEventManager()->removeEventListener('preUpdate', self::$dtcQueueListener);

        $job = new self::$jobClass(self::$worker, false, null);
        $job->fibonacci(1);
        $id = $job->getId();
        $objectManager->remove($job);
        $objectManager->flush();

        $jobArchive = $jobArchiveRepository->find($id);
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
        $id = $job->getId();
        $objectManager->remove($job);
        $objectManager->flush();

        $jobArchive = $jobArchiveRepository->find($id);
        self::assertNotNull($jobArchive);
        $time = time() - 86401;
        $jobArchive->setUpdatedAt(new \DateTime("@$time"));
        $objectManager->persist($jobArchive);
        $objectManager->flush();

        $job = new self::$jobClass(self::$worker, false, null);
        $job->fibonacci(1);
        $id = $job->getId();
        $objectManager->remove($job);
        $objectManager->flush();

        $jobArchive = $jobArchiveRepository->find($id);
        self::assertNotNull($jobArchive);
        $jobArchive->setUpdatedAt(new \DateTime("@$time"));
        $objectManager->persist($jobArchive);
        $objectManager->flush();
        $older = $time + 1;
        $count = $jobManager->pruneArchivedJobs(new \DateTime("@$time"));
        self::assertEquals(0, $count);
        $count = $jobManager->pruneArchivedJobs(new \DateTime("@$older"));
        self::assertEquals(2, $count);

        self::$objectManager->getEventManager()->addEventListener('preUpdate', self::$dtcQueueListener);
    }

    public function testPerformance()
    {
        /** @var JobManager|\Dtc\QueueBundle\ORM\JobManager $jobManager */
        $jobManager = self::$jobManager;

        $jobs = $jobManager->getRepository()->findAll();
        foreach ($jobs as $job) {
            $jobManager->getObjectManager()->remove($job);
        }
        $jobManager->getObjectManager()->flush();

        $jobManager->getObjectManager()->clear();
        parent::testPerformance();
    }

    protected function getBaseStatus()
    {
        /** @var JobManager|\Dtc\QueueBundle\ORM\JobManager $jobManager */
        $jobManager = self::$jobManager;
        $job = new self::$jobClass(self::$worker, false, null);
        $job->fibonacci(1);
        $status = $jobManager->getStatus();
        self::assertArrayHasKey('fibonacci->fibonacci()', $status);
        $fibonacciStatus = $status['fibonacci->fibonacci()'];

        self::assertArrayHasKey(BaseJob::STATUS_NEW, $fibonacciStatus);
        self::assertArrayHasKey(BaseJob::STATUS_EXCEPTION, $fibonacciStatus);
        self::assertArrayHasKey(BaseJob::STATUS_RUNNING, $fibonacciStatus);
        self::assertArrayHasKey(BaseJob::STATUS_SUCCESS, $fibonacciStatus);
        self::assertArrayHasKey(Job::STATUS_EXPIRED, $fibonacciStatus);
        self::assertArrayHasKey(StallableJob::STATUS_MAX_STALLS, $fibonacciStatus);
        self::assertArrayHasKey(RetryableJob::STATUS_MAX_EXCEPTIONS, $fibonacciStatus);
        self::assertArrayHasKey(RetryableJob::STATUS_MAX_FAILURES, $fibonacciStatus);
        self::assertArrayHasKey(RetryableJob::STATUS_MAX_RETRIES, $fibonacciStatus);

        return [$job, $status];
    }

    public function testGetStatus()
    {
        list($job1, $status1) = $this->getBaseStatus();
        list($job2, $status2) = $this->getBaseStatus();
        $fibonacciStatus1 = $status1['fibonacci->fibonacci()'];
        $fibonacciStatus2 = $status2['fibonacci->fibonacci()'];

        self::assertEquals($fibonacciStatus1[BaseJob::STATUS_NEW] + 1, $fibonacciStatus2[BaseJob::STATUS_NEW]);
        /** @var JobManager|\Dtc\QueueBundle\ORM\JobManager $jobManager */
        $jobManager = self::$jobManager;
        $objectManager = $jobManager->getObjectManager();
        $objectManager->remove($job1);
        $objectManager->remove($job2);
    }

    public function testStallableJobManager()
    {
        /** @var StallableJobManager $jobManager */
        $jobManager = self::$jobManager;

        $defaultMaxStalls = $jobManager->getDefaultMaxStalls();
        $jobManager->setDefaultMaxStalls(123);
        self::assertEquals(123, $jobManager->getDefaultMaxStalls());
        $jobManager->setDefaultMaxStalls($defaultMaxStalls);
        self::assertEquals($defaultMaxStalls, $jobManager->getDefaultMaxStalls());
    }
}
