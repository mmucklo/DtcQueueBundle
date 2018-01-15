<?php

namespace Dtc\QueueBundle\Tests\Command;

use Dtc\QueueBundle\Model\Job;
use Dtc\QueueBundle\Model\JobTiming;
use Dtc\QueueBundle\Manager\JobTimingManager;
use Dtc\QueueBundle\Tests\StubJobManager;
use Dtc\QueueBundle\Tests\StubRunManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Tests\Fixtures\DummyOutput;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerInterface;

trait CommandTrait
{
    /**
     * @param string             $commandClass
     * @param ContainerInterface $container
     * @param array              $params
     */
    protected function runCommand($commandClass, ContainerInterface $container, array $params)
    {
        $this->runCommandExpect($commandClass, $container, $params, 0);
    }

    /**
     * @param string             $commandClass
     * @param ContainerInterface $container
     * @param array              $params
     */
    private function prepCommand($commandClass, ContainerInterface $container, array $params)
    {
        $command = new $commandClass();
        $command->setContainer($container);
        $input = new ArrayInput($params);
        $output = new DummyOutput();

        return [$command, $input, $output];
    }

    /**
     * @param string             $commandClass
     * @param ContainerInterface $container
     * @param array              $params
     */
    protected function runCommandException($commandClass, ContainerInterface $container, array $params)
    {
        list($command, $input, $output) = $this->prepCommand($commandClass, $container, $params);
        $failed = false;
        try {
            $command->run($input, $output);
            $failed = true;
        } catch (\Exception $exception) {
            TestCase::assertTrue(true);
        }
        TestCase::assertFalse($failed);
    }

    /**
     * @param string             $commandClass
     * @param ContainerInterface $container
     * @param array              $params
     * @param int                $expectedResult
     */
    protected function runCommandExpect($commandClass, ContainerInterface $container, array $params, $expectedResult)
    {
        list($command, $input, $output) = $this->prepCommand($commandClass, $container, $params);
        try {
            $result = $command->run($input, $output);
        } catch (\Exception $exception) {
            TestCase::fail("Shouldn't throw exception: ".get_class($exception).' - '.$exception->getMessage());

            return;
        }
        TestCase::assertEquals($expectedResult, $result);
    }

    protected function runStubCommand($className, $params, $call, $expectedResult = 0)
    {
        $managerType = 'job';
        if (false !== strrpos($call, 'Runs') || false !== strrpos($call, 'Timings')) {
            $managerType = 'run';
        }

        $jobTimingManager = new JobTimingManager(JobTiming::class, false);
        $runManager = new StubRunManager($jobTimingManager, \Dtc\QueueBundle\Model\Run::class);
        $jobManager = new StubJobManager($runManager, $jobTimingManager, Job::class);
        $container = new Container();
        $container->set('dtc_queue.job_manager', $jobManager);
        $container->set('dtc_queue.run_manager', $runManager);
        $this->runCommandExpect($className, $container, $params, $expectedResult);
        $manager = "${managerType}Manager";
        if (0 === $expectedResult) {
            self::assertTrue(isset($$manager->calls[$call][0]));
            self::assertTrue(!isset($$manager->calls[$call][1]));
        }

        return $$manager;
    }
}
