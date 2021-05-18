<?php

namespace Dtc\QueueBundle\Tests\Command;

use Dtc\QueueBundle\Command\CountCommand;
use Dtc\QueueBundle\Manager\JobTimingManager;
use Dtc\QueueBundle\Manager\RunManager;
use Dtc\QueueBundle\Model\Job;
use Dtc\QueueBundle\Model\JobTiming;
use Dtc\QueueBundle\Model\Run;
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
        $runManager = new RunManager(Run::class);
        $container->set('dtc_queue.manager.job', new StubJobManager($runManager, $jobTimingManager, Job::class));
        $this->runCommand(CountCommand::class, $container, []);
    }

    public function testCountBeanstalkdCommand()
    {
        JobManagerTest::setUpBeforeClass();
        $jobManager = JobManagerTest::$jobManager;
        $container = new Container();
        $container->set('dtc_queue.manager.job', $jobManager);
        $this->runCommand(CountCommand::class, $container, []);
    }

    public function testCountMongodbCommand()
    {
        \Dtc\QueueBundle\Tests\ODM\JobManagerTest::setUpBeforeClass();
        $jobManager = \Dtc\QueueBundle\Tests\ODM\JobManagerTest::$jobManager;
        $container = new Container();
        $container->set('dtc_queue.manager.job', $jobManager);
        $this->runCommand(CountCommand::class, $container, []);
    }

    public function testCountORMCommand()
    {
        \Dtc\QueueBundle\Tests\ORM\JobManagerTest::setUpBeforeClass();
        $jobManager = \Dtc\QueueBundle\Tests\ORM\JobManagerTest::$jobManager;
        $container = new Container();
        $container->set('dtc_queue.manager.job', $jobManager);
        $this->runCommand(CountCommand::class, $container, []);
    }
}
