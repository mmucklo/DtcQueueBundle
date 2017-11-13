<?php

namespace Dtc\QueueBundle\Tests\Command;

use Dtc\QueueBundle\Command\CreateJobCommand;
use Dtc\QueueBundle\EventDispatcher\EventDispatcher;
use Dtc\QueueBundle\Model\Job;
use Dtc\QueueBundle\Model\WorkerManager;
use Dtc\QueueBundle\Tests\FibonacciWorker;
use Dtc\QueueBundle\Tests\StubJobManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;

class CreateJobCommandTest extends TestCase
{
    use CommandTrait;

    public function testCreateJobCommand()
    {
        $jobManager = new StubJobManager();
        $container = new Container();
        $container->set('dtc_queue.job_manager', $jobManager);
        $this->runCommandException(CreateJobCommand::class, $container, ['worker_name' => 'fibonacci', 'method' => 'fibonacci', 'args' => [1]]);

        $eventDispatcher = new EventDispatcher();
        $workerManager = new WorkerManager($jobManager, $eventDispatcher);

        $container->set('dtc_queue.worker_manager', $workerManager);
        $this->runCommandException(CreateJobCommand::class, $container, ['worker_name' => 'fibonacci', 'method' => 'fibonacci', 'args' => [1]]);

        $worker = new FibonacciWorker();
        $worker->setJobManager($jobManager);
        $workerManager->addWorker($worker);
        $this->runCommand(CreateJobCommand::class, $container, ['worker_name' => 'fibonacci', 'method' => 'fibonacci', 'args' => [1]]);

        self::assertTrue(isset($jobManager->calls['save'][0][0]));
        self::assertTrue($jobManager->calls['save'][0][0] instanceof Job);
        self::assertTrue(!isset($jobManager->calls['save'][0][1]));
    }
}
