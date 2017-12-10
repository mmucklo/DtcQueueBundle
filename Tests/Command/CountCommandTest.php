<?php

namespace Dtc\QueueBundle\Tests\Command;

use Dtc\QueueBundle\Command\CountCommand;
use Dtc\QueueBundle\Model\Job;
use Dtc\QueueBundle\Model\JobTiming;
use Dtc\QueueBundle\Model\JobTimingManager;
use Dtc\QueueBundle\Model\Run;
use Dtc\QueueBundle\Model\RunManager;
use Dtc\QueueBundle\Tests\Beanstalkd\JobManagerTest;
use Dtc\QueueBundle\Tests\StubJobManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;

class CountCommandTest extends TestCase
{
    use CommandTrait;

    public function testCountCommand()
    {
        $container = new Container();
        $jobTimingManager = new JobTimingManager(JobTiming::class, false);
        $runManager = new RunManager($jobTimingManager, Run::class);
        $container->set('dtc_queue.job_manager', new StubJobManager($runManager, $jobTimingManager, Job::class));
        $this->runCommandException(CountCommand::class, $container, []);
    }

    public function testCountBeanstalkdCommand()
    {
        JobManagerTest::setUpBeforeClass();
        $jobManager = JobManagerTest::$jobManager;
        $container = new Container();
        $container->set('dtc_queue.job_manager', $jobManager);
        $this->runCommand(CountCommand::class, $container, []);
    }

    public function testCountMongodbCommand()
    {
        \Dtc\QueueBundle\Tests\ODM\JobManagerTest::setUpBeforeClass();
        $jobManager = \Dtc\QueueBundle\Tests\ODM\JobManagerTest::$jobManager;
        $container = new Container();
        $container->set('dtc_queue.job_manager', $jobManager);
        $this->runCommand(CountCommand::class, $container, []);
    }

    public function testCountORMCommand()
    {
        \Dtc\QueueBundle\Tests\ORM\JobManagerTest::setUpBeforeClass();
        $jobManager = \Dtc\QueueBundle\Tests\ORM\JobManagerTest::$jobManager;
        $container = new Container();
        $container->set('dtc_queue.job_manager', $jobManager);
        $this->runCommand(CountCommand::class, $container, []);
    }
}
