<?php

namespace Dtc\QueueBundle\Tests\Command;

use Dtc\QueueBundle\Command\CreateJobCommand;
use Dtc\QueueBundle\EventDispatcher\EventDispatcher;
use Dtc\QueueBundle\Model\Job;
use Dtc\QueueBundle\Manager\JobTimingManager;
use Dtc\QueueBundle\Model\Run;
use Dtc\QueueBundle\Manager\RunManager;
use Dtc\QueueBundle\Manager\WorkerManager;
use Dtc\QueueBundle\Tests\FibonacciWorker;
use Dtc\QueueBundle\Tests\StubJobManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;

class CreateJobCommandTest extends TestCase
{
    use CommandTrait;

    public function testCreateJobCommand()
    {
        $jobTimingManager = new JobTimingManager(JobTimingManager::class, false);
        $runManager = new RunManager($jobTimingManager, Run::class);
        $jobManager = new StubJobManager($runManager, $jobTimingManager, Job::class);
        $container = new Container();
        $container->set('dtc_queue.manager.job', $jobManager);
        $this->runCommandException(CreateJobCommand::class, $container, [
            '--json-args' => null,
            'worker_name' => 'fibonacci',
            'method' => 'fibonacci',
            'args' => [
                json_encode([
                    1,
                ]),
            ],
        ]);

        $eventDispatcher = new EventDispatcher();
        $workerManager = new WorkerManager($jobManager, $eventDispatcher);

        $container->set('dtc_queue.manager.worker', $workerManager);
        $this->runCommandException(CreateJobCommand::class, $container, [
            '--json-args' => null,
            'worker_name' => 'fibonacci',
            'method' => 'fibonacci',
            'args' => [
                json_encode([
                    1,
                ]),
            ],
        ]);

        $worker = new FibonacciWorker();
        $worker->setJobManager($jobManager);
        $workerManager->addWorker($worker);
        $this->runCommand(CreateJobCommand::class, $container, [
            '--json-args' => null,
            'worker_name' => 'fibonacci',
            'method' => 'fibonacci',
            'args' => [
                json_encode([
                    1,
                ]),
            ],
        ]);

        self::assertTrue(isset($jobManager->calls['save'][0][0]));
        self::assertTrue($jobManager->calls['save'][0][0] instanceof Job);
        self::assertTrue(!isset($jobManager->calls['save'][0][1]));
    }
}
