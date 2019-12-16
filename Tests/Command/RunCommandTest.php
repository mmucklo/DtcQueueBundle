<?php

namespace Dtc\QueueBundle\Tests\Command;

use Dtc\QueueBundle\Command\RunCommand;
use PHPUnit\Framework\TestCase;
use Dtc\QueueBundle\ODM\JobManager;
use Dtc\QueueBundle\EventDispatcher\EventDispatcher;
use Dtc\QueueBundle\Manager\WorkerManager;
use Dtc\QueueBundle\Tests\FibonacciWorker;
use Dtc\QueueBundle\Run\Loop;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\DependencyInjection\Container;

class RunCommandTest extends TestCase
{
    use CommandTrait;

    public function testORMRun()
    {
        \Dtc\QueueBundle\Tests\ORM\JobManagerTest::setUpBeforeClass();

        /** @var JobManager $jobManager */
        $jobManager = \Dtc\QueueBundle\Tests\ORM\JobManagerTest::$jobManager;
        $runManager = \Dtc\QueueBundle\Tests\ORM\JobManagerTest::$runManager;
        $eventDispatcher = new EventDispatcher();
        $workerManager = new WorkerManager($jobManager, $eventDispatcher);
        $worker = new FibonacciWorker();
        $workerManager->addWorker($worker);
        $worker->setJobManager($jobManager);

        $loop = new Loop($workerManager, $jobManager, $runManager);
        $container = new Container();
        $container->set('dtc_queue.run.loop', $loop);
        $container->set('dtc_queue.manager.run', $runManager);
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
        $output = new NullOutput();
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
