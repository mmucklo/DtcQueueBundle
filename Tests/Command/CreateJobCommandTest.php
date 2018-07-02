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
use Symfony\Component\DependencyInjection\ContainerInterface;

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
        $eventDispatcher = new EventDispatcher();
        $workerManager = new WorkerManager($jobManager, $eventDispatcher);

        $container->set('dtc_queue.manager.worker', $workerManager);

        $worker = new FibonacciWorker();
        $worker->setJobManager($jobManager);
        $workerManager->addWorker($worker);

        $this->runCommandException(CreateJobCommand::class, $container, [
            '--json-args' => true,
            '--php-args' => true,
            'worker_name' => 'fibonacci',
            'method' => 'fibonacci',
            'args' => [
               1,
            ],
        ]);

        $this->runCommandException(CreateJobCommand::class, $container, [
            '--json-args' => true,
            '--interpret-args' => true,
            'worker_name' => 'fibonacci',
            'method' => 'fibonacci',
            'args' => [
               1,
            ],
        ]);

        $this->runCommandException(CreateJobCommand::class, $container, [
            '--php-args' => true,
            '--interpret-args' => true,
            'worker_name' => 'fibonacci',
            'method' => 'fibonacci',
            'args' => [
               1,
            ],
        ]);

        $this->runCommandException(CreateJobCommand::class, $container, [
            '--json-args' => true,
            'worker_name' => 'fibonacci',
            'method' => 'fibonacci',
            'args' => [
                json_encode([
                    1,
                ]), 1234,
            ],
        ]);

        $this->runCommandException(CreateJobCommand::class, $container, [
            '--php-args' => true,
            'worker_name' => 'fibonacci',
            'method' => 'fibonacci',
            'args' => [
                serialize([
                    1,
                ]), 1234,
            ],
        ]);

        $this->runCommand(CreateJobCommand::class, $container, [
            '--json-args' => true,
            'worker_name' => 'fibonacci',
            'method' => 'fibonacci',
            'args' => [
                json_encode([
                    1,
                ]),
            ],
        ]);

        $idx = 0;
        self::assertTrue(isset($jobManager->calls['save'][$idx][0]));
        self::assertTrue($jobManager->calls['save'][$idx][0] instanceof Job);
        $args = $jobManager->calls['save'][$idx][0]->getArgs();
        self::assertNotNull($args);
        self::assertTrue(is_array($args));
        self::assertCount(1, $args);
        self::assertSame(1, $args[0]);
        self::assertTrue(!isset($jobManager->calls['save'][$idx][1]));

        $this->runCommand(CreateJobCommand::class, $container, [
            '--php-args' => true,
            'worker_name' => 'fibonacci',
            'method' => 'fibonacci',
            'args' => [
                serialize([
                    1,
                ]),
            ],
        ]);

        ++$idx;
        self::assertTrue(isset($jobManager->calls['save'][$idx][0]));
        self::assertTrue($jobManager->calls['save'][$idx][0] instanceof Job);
        $args = $jobManager->calls['save'][$idx][0]->getArgs();
        self::assertNotNull($args);
        self::assertTrue(is_array($args));
        self::assertCount(1, $args);
        self::assertSame(1, $args[0]);
        self::assertTrue(!isset($jobManager->calls['save'][$idx][1]));

        $this->runCommand(CreateJobCommand::class, $container, [
            '--interpret-args' => true,
            'worker_name' => 'fibonacci',
            'method' => 'fibonacci',
            'args' => [
            ],
        ]);

        ++$idx;
        $this->examineJobManagerResult($jobManager, $idx, null, 0);

        $this->runCommand(CreateJobCommand::class, $container, [
            '--interpret-args' => true,
            'worker_name' => 'fibonacci',
            'method' => 'fibonacci',
        ]);

        ++$idx;
        $this->examineJobManagerResult($jobManager, $idx, null, 0);
        $this->runCommandWithArgs($container, ['a1.1'], 'a1.1', $idx);
        $this->runCommandWithArgs($container, ['true'], true, $idx);
        $this->runCommandWithArgs($container, ['TRUE'], true, $idx);
        $this->runCommandWithArgs($container, ['false'], false, $idx);
        $this->runCommandWithArgs($container, ['FALSE'], false, $idx);
        $this->runCommandWithArgs($container, ['1.1'], 1.1, $idx);
        $this->runCommandWithArgs($container, ['1'], 1, $idx);
        $this->runCommandWithArgs($container, ['null'], null, $idx);
        $this->runCommandWithArgs($container, ['NULL'], null, $idx);
    }

    protected function runCommandWithArgs(ContainerInterface $container, $args, $expected, &$idx)
    {
        ++$idx;
        $this->runCommand(CreateJobCommand::class, $container, [
            '--interpret-args' => true,
            'worker_name' => 'fibonacci',
            'method' => 'fibonacci',
            'args' => $args,
        ]);
        $this->examineJobManagerResult($container->get('dtc_queue.manager.job'), $idx, $expected);
    }

    protected function examineJobManagerResult(StubJobManager $jobManager, $idx, $expected, $expectedCount = 1)
    {
        self::assertTrue(isset($jobManager->calls['save'][$idx][0]));
        self::assertTrue($jobManager->calls['save'][$idx][0] instanceof Job);
        $args = $jobManager->calls['save'][$idx][0]->getArgs();
        self::assertNotNull($args);
        self::assertTrue(is_array($args));
        self::assertCount($expectedCount, $args);
        if ($expectedCount > 0) {
            self::assertSame($expected, $args[0]);
        }
        self::assertTrue(!isset($jobManager->calls['save'][$idx][1]));
    }
}
