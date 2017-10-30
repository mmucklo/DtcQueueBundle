<?php

namespace Dtc\QueueBundle\Tests\DependencyInjection\Compiler;

use Dtc\QueueBundle\DependencyInjection\Compiler\WorkerCompilerPass;
use Dtc\QueueBundle\EventDispatcher\EventDispatcher;
use Dtc\QueueBundle\Model\WorkerManager;
use Dtc\QueueBundle\ODM\JobManager;
use Dtc\QueueBundle\ODM\RunManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class WorkerCompilerPassTest extends TestCase
{
    public function testProcess()
    {
        $container = new ContainerBuilder();

        $count = count($container->getDefinitions());
        $compilerPass = new WorkerCompilerPass();
        $compilerPass->process($container);
        self::assertEquals($count, count($container->getDefinitions()));

        $container = new ContainerBuilder();
        $definition1 = new Definition();
        $definition1->setClass(WorkerManager::class);
        $container->setParameter('dtc_queue.default_manager', 'odm');
        $container->setParameter('dtc_queue.run_manager', 'odm');
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
        $container->addDefinitions([
            'dtc_queue.worker_manager' => $definition1,
            'dtc_queue.job_manager.odm' => $definition2,
            'dtc_queue.run_manager.odm' => $definition3,
            'dtc_queue.event_dispatcher' => $definition4,
        ]);

        $count = count($container->getAliases());
        $compilerPass = new WorkerCompilerPass();
        $compilerPass->process($container);

        self::assertNotEquals($count, count($container->getAliases()));
    }
}
