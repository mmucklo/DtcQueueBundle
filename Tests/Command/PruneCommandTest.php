<?php

namespace Dtc\QueueBundle\Tests\Command;

use Doctrine\ODM\MongoDB\DocumentRepository;
use Dtc\QueueBundle\Command\PruneCommand;
use Dtc\QueueBundle\Document\Job;
use Dtc\QueueBundle\EventDispatcher\EventDispatcher;
use Dtc\QueueBundle\Manager\JobTimingManager;
use Dtc\QueueBundle\Manager\RunManager;
use Dtc\QueueBundle\Manager\WorkerManager;
use Dtc\QueueBundle\ODM\JobManager;
use Dtc\QueueBundle\Tests\FibonacciWorker;
use Dtc\QueueBundle\Util\Util;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;

class PruneCommandTest extends TestCase
{
    use CommandTrait;

    public function testPruneCommandExpired()
    {
        \Dtc\QueueBundle\Tests\ODM\JobManagerTest::setUpBeforeClass();

        /** @var JobManager $jobManager */
        $jobManager = \Dtc\QueueBundle\Tests\ODM\JobManagerTest::$jobManager;
        /** @var RunManager $runManager */
        $runManager = \Dtc\QueueBundle\Tests\ORM\JobManagerTest::$runManager;
        $jobTimingManager = new JobTimingManager(JobTimingManager::class, false);
        $eventDispatcher = new EventDispatcher();
        $workerManager = new WorkerManager($jobManager, $eventDispatcher);
        $worker = new FibonacciWorker();
        $workerManager->addWorker($worker);
        $worker->setJobManager($jobManager);

        $container = new Container();
        $container->set('dtc_queue.manager.job', $jobManager);
        $container->set('dtc_queue.manager.run', $runManager);
        $container->set('dtc_queue.manager.job_timing', $jobTimingManager);

        /** @var Job $job */
        $time = time() - 1;
        $date = new \DateTime("@$time");
        $job = $worker->later()->setExpiresAt($date)->fibonacci(1);
        self::assertNotNull($job->getId(), 'Job id should be generated');

        /** @var JobManager $jobManager $ */
        /** @var DocumentRepository $repository */
        $repository = $jobManager->getRepository();
        $count = $repository->createQueryBuilder()->count()->getQuery()->execute();
        self::assertEquals(1, $count);
        $archiveRepository = $jobManager->getObjectManager()->getRepository($jobManager->getJobArchiveClass());
        $countArchive = $archiveRepository->createQueryBuilder()->count()->getQuery()->execute();
        self::assertEquals(0, $countArchive);

        $this->runCommand('\Dtc\QueueBundle\Command\PruneCommand', $container, ['type' => 'expired']);

        $count = $repository->createQueryBuilder()->count()->getQuery()->execute();
        self::assertEquals(0, $count);

        $countArchive = $archiveRepository->createQueryBuilder()->count()->getQuery()->execute();
        self::assertEquals(1, $countArchive);
    }

    public function testPruneCommandError()
    {
        $this->runPruneCommand(['type' => 'exception'], 'pruneExceptionJobs');
    }

    public function testPruneCommandStalled()
    {
        $this->runPruneCommand(['type' => 'stalled'], 'pruneStalledJobs');
    }

    public function testPruneCommandStalledRuns()
    {
        $this->runPruneCommand(['type' => 'stalled_runs'], 'pruneStalledRuns');
    }

    protected function getPruneCommandOlderDateDiff($older, $type = 'old', $call = 'pruneArchivedJobs')
    {
        $startDate = Util::getMicrotimeDateTime();
        /** @var \DateTime $dateVal */
        $dateVal = $this->runPruneCommandOlder($older, 0, $type, $call);
        $endTime = time();
        $dateDiff = $startDate->diff($dateVal);

        return [$dateDiff, ($endTime - $startDate->getTimestamp()) + 1];
    }

    protected function getPruneCommandOlderDateDays($older, $type = 'old', $call = 'pruneArchivedJobs')
    {
        list($dateDiff) = $this->getPruneCommandOlderDateDiff($older, $type, $call);

        return $dateDiff->format('%a');
    }

    protected function getPruneCommandOlderDateSeconds($older, $type = 'old', $call = 'pruneArchivedJobs')
    {
        list($dateDiff, $varianceSeconds) = $this->getPruneCommandOlderDateDiff($older, $type, $call);
        $date1 = Util::getMicrotimeDateTime();
        $date2 = clone $date1;
        $date2->sub($dateDiff);

        return [$date2->getTimestamp() - $date1->getTimestamp(), $varianceSeconds];
    }

    public function testPruneOldRuns()
    {
        // Test invalid
        $this->runPruneOld();
        $this->runPruneOld('old_runs', 'pruneArchivedRuns');
        $this->runPruneOld('old_job_timings', 'pruneJobTimings');
    }

    public function runPruneOld($type = 'old', $call = 'pruneArchivedJobs')
    {
        // Test invalid
        $this->runPruneCommandOlder(null, 1, $type, $call);
        $this->runPruneCommandOlder('1x', 1, $type, $call);
        $this->runPruneCommandOlder('1dd', 1, $type, $call);

        // Test by day / month / year
        $result = $this->getPruneCommandOlderDateDays('1d', $type, $call);
        self::assertEquals(1, intval($result));
        $result = $this->getPruneCommandOlderDateDays('1m', $type, $call);
        self::assertGreaterThanOrEqual(28, intval($result));
        self::assertLessThanOrEqual(31, intval($result));
        $result = $this->getPruneCommandOlderDateDays('1y', $type, $call);
        self::assertGreaterThanOrEqual(364, intval($result));
        self::assertLessThanOrEqual(366, intval($result));

        // Test by time
        $this->runPruneCommandOlderSeconds('2h', 7200, $type, $call);
        $this->runPruneCommandOlderSeconds('1h', 3600, $type, $call);
        $this->runPruneCommandOlderSeconds('5i', 300, $type, $call);
        $this->runPruneCommandOlderSeconds('1i', 60, $type, $call);
        $this->runPruneCommandOlderSeconds('5s', 5, $type, $call);
        $this->runPruneCommandOlderSeconds('1s', 1, $type, $call);

        // Test timestamps
        $this->runPruneCommandOlderSeconds(time() - 1, 1, $type, $call);
        $this->runPruneCommandOlderSeconds(time() - 60, 60, $type, $call);
        $this->runPruneCommandOlderSeconds(time() - 86400, 86400, $type, $call);
    }

    protected function runPruneCommandOlderSeconds($older, $expected, $type = 'old', $call = 'pruneArchivedJobs')
    {
        list($result, $variance) = $this->getPruneCommandOlderDateSeconds($older, $type, $call);
        self::assertGreaterThanOrEqual($expected - $variance, intval($result));
        self::assertLessThanOrEqual($expected + $variance, intval($result));
    }

    protected function runPruneCommand($params, $call, $expectedResult = 0)
    {
        return $this->runStubCommand(PruneCommand::class, $params, $call, $expectedResult);
    }

    protected function runPruneCommandOlder($older, $expectedResult = 0, $type = 'old', $call = 'pruneArchivedJobs')
    {
        $params = ['type' => $type];
        if (null !== $older) {
            $params['--older'] = $older;
        }
        $manager = $this->runPruneCommand($params, $call, $expectedResult);
        if (0 === $expectedResult) {
            self::assertTrue(isset($manager->calls[$call][0][0]));
            self::assertTrue(!isset($manager->calls[$call][0][1]));

            return $manager->calls[$call][0][0];
        }
    }
}
