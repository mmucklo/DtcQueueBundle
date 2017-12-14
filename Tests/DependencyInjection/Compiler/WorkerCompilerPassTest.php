<?php

namespace Dtc\QueueBundle\Tests\DependencyInjection\Compiler;

use Dtc\QueueBundle\DependencyInjection\Compiler\WorkerCompilerPass;
use Dtc\QueueBundle\EventDispatcher\EventDispatcher;
use Dtc\QueueBundle\Model\WorkerManager;
use Dtc\QueueBundle\ODM\JobManager;
use Dtc\QueueBundle\ODM\JobTimingManager;
use Dtc\QueueBundle\ODM\RunManager;
use Dtc\QueueBundle\Tests\FibonacciWorker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class WorkerCompilerPassTest extends TestCase
{
    protected function getBaseContainer($type = 'odm', $runManagerType = 'odm', $jobTimingManagerType = 'odm')
    {
        $container = new ContainerBuilder();

        $count = count($container->getDefinitions());
        $compilerPass = new WorkerCompilerPass();
        $compilerPass->process($container);
        self::assertEquals($count, count($container->getDefinitions()));

        $container = new ContainerBuilder();
        $definition1 = new Definition();
        $definition1->setClass(WorkerManager::class);
        $container->setParameter('dtc_queue.default_manager', $type);
        $container->setParameter('dtc_queue.run_manager', $runManagerType);
        $container->setParameter('dtc_queue.job_timing_manager', $jobTimingManagerType);
        $container->setParameter('dtc_queue.class_job', null);
        $container->setParameter('dtc_queue.class_job_archive', null);
        $container->setParameter('dtc_queue.document_manager', 'default');
        $container->setParameter('dtc_queue.entity_manager', 'default');
        $definition2 = new Definition();
        $definition2->setClass(JobManager::class);
        $definition3 = new Definition();
        $definition3->setClass(RunManager::class);
        $definition4 = new Definition();
        $definition4->setClass(EventDispatcher::class);
        $definition5 = new Definition();
        $definition5->setClass(JobTimingManager::class);
        $container->addDefinitions([
            'dtc_queue.worker_manager' => $definition1,
            'dtc_queue.job_manager.' . $type => $definition2,
            'dtc_queue.job_timing_manager.' . $jobTimingManagerType => $definition5,
            'dtc_queue.run_manager.' . $runManagerType => $definition3,
            'dtc_queue.event_dispatcher' => $definition4,
        ]);

        return $container;
    }

    public function testProcess()
    {
        $container = $this->getBaseContainer();
        $count = count($container->getAliases());
        $compilerPass = new WorkerCompilerPass();
        $compilerPass->process($container);

        self::assertNotEquals($count, count($container->getAliases()));
    }

    public function testProcessInvalidWorker()
    {
        $container = $this->getBaseContainer();
        $definition5 = new Definition();
        $definition5->setClass(JobManager::class);
        $definition5->addTag('dtc_queue.worker');
        $container->addDefinitions(['some.worker' => $definition5]);

        $count = count($container->getAliases());
        $compilerPass = new WorkerCompilerPass();
        $failed = false;
        try {
            $compilerPass->process($container);
            $failed = true;
        } catch (\Exception $e) {
            self::assertTrue(true);
        }
        self::assertFalse($failed);
        self::assertNotEquals($count, count($container->getAliases()));
    }

    public function testProcessValidWorker() {
        $this->runProcessValidWorker('odm');
        $this->runProcessValidWorker('odm', 'orm', 'orm');
        $this->runProcessValidWorker('orm');
        $this->runProcessValidWorker('orm', 'odm', 'orm');
        $this->runProcessValidWorker('beanstalkd');
        $this->runProcessValidWorker('rabbit_mq');
    }

    public function testBadManagerType()
    {
        $failure = false;
        try {
            $this->runProcessValidWorker('bad');
        }
        catch (\Exception $e) {
            $failure = true;
        }
        self::assertTrue($failure);
    }

    public function runProcessValidWorker($type, $runManagerType = 'odm', $jobTimingManagerType = 'odm')
    {
        $container = $this->getBaseContainer($type, $runManagerType, $jobTimingManagerType);
        $definition5 = new Definition();
        $definition5->setClass(FibonacciWorker::class);
        $definition5->addTag('dtc_queue.worker');
        $container->addDefinitions(['some.worker' => $definition5]);

        $count = count($container->getAliases());
        $compilerPass = new WorkerCompilerPass();
        $compilerPass->process($container);
        self::assertNotEquals($count, count($container->getAliases()));
    }
}
