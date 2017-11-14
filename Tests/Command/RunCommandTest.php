<?php

namespace Dtc\QueueBundle\Tests\Command;

use Dtc\QueueBundle\Command\RunCommand;
use PHPUnit\Framework\TestCase;
use Dtc\QueueBundle\ODM\JobManager;
use Dtc\QueueBundle\EventDispatcher\EventDispatcher;
use Dtc\QueueBundle\Model\WorkerManager;
use Dtc\QueueBundle\Tests\FibonacciWorker;
use Dtc\QueueBundle\Run\Loop;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Tests\Fixtures\DummyOutput;
use Symfony\Component\DependencyInjection\Container;

class RunCommandTest extends TestCase
{
    use CommandTrait;

    public function testMongoDBRun()
    {
        \Dtc\QueueBundle\Tests\ODM\JobManagerTest::setUpBeforeClass();

        /** @var JobManager $jobManager */
        $jobManager = \Dtc\QueueBundle\Tests\ODM\JobManagerTest::$jobManager;
        $runManager = \Dtc\QueueBundle\Tests\ODM\JobManagerTest::$runManager;
        $eventDispatcher = new EventDispatcher();
        $workerManager = new WorkerManager($jobManager, $eventDispatcher);
        $worker = new FibonacciWorker();
        $worker->setJobClass(\Dtc\QueueBundle\Document\Job::class);
        $workerManager->addWorker($worker);
        $worker->setJobManager($jobManager);

        $loop = new Loop($workerManager, $jobManager, $runManager);
        $container = new Container();
        $container->set('dtc_queue.run.loop', $loop);
        $container->set('dtc_queue.run_manager', $runManager);
        $worker->later()->fibonacci(1);

        $this->runRunCommand($loop, $container, [], 1);

        $startTime = time();
        $this->runRunCommand($loop, $container, ['-d' => 2], 0);
        self::assertGreaterThanOrEqual(2, time() - $startTime);

        $worker->later()->fibonacci(1);
        $worker->later()->fibonacci(1);

        $this->runRunCommand($loop, $container, ['-m' => 4], 2);

        $worker->later()->fibonacci(1);
        $worker->later()->fibonacci(2);

        $this->runRunCommand($loop, $container, ['-m' => 1], 1);
        $this->runRunCommand($loop, $container, ['-m' => 1], 1);

        $runCommand = new RunCommand();
        $runCommand->setContainer($container);
        $input = new ArrayInput(['-m' => 0, '-d' => 0]);
        $output = new DummyOutput();
        $failed = false;
        try {
            $runCommand->run($input, $output);
            $failed = true;
        } catch (\Exception $exception) {
            self::assertTrue(true);
        }
        static::assertFalse($failed);
    }

    protected function runRunCommand(Loop $loop, Container $container, $params, $amountProcessed)
    {
        $command = RunCommand::class;
        $this->runCommand($command, $container, $params);
        self::assertEquals($amountProcessed, $loop->getLastRun()->getProcessed());
    }
}
