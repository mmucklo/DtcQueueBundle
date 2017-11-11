<?php

namespace Dtc\QueueBundle\Tests\DependencyInjection\Compiler;

use Dtc\QueueBundle\Beanstalkd\JobManager;
use Dtc\QueueBundle\DependencyInjection\Compiler\BeanstalkdCompilerPass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class BeanstalkdCompilerPassTest extends TestCase
{
    public function testProcess()
    {
        $container = new ContainerBuilder();

        $count = count($container->getDefinitions());
        $compilerPass = new BeanstalkdCompilerPass();
        $compilerPass->process($container);
        self::assertEquals($count, count($container->getDefinitions()));

        $container = new ContainerBuilder();
        $count = count($container->getDefinitions());
        $definition = new Definition();
        $definition->setClass(JobManager::class);
        $container->addDefinitions(['dtc_queue.job_manager.beanstalkd' => $definition]);
        $container->setParameter('dtc_queue.beanstalkd.host', 'somehost');
        $compilerPass = new BeanstalkdCompilerPass();
        $compilerPass->process($container);

        self::assertGreaterThan($count, count($container->getDefinitions()));
        self::assertTrue($container->hasDefinition('dtc_queue.beanstalkd'));

        $definition = $container->getDefinition('dtc_queue.job_manager.beanstalkd');
        self::assertNotEmpty($definition->getMethodCalls());
        self::assertCount(1, $definition->getMethodCalls());

        $container = new ContainerBuilder();
        $definition = new Definition();
        $definition->setClass(JobManager::class);
        $container->addDefinitions(['dtc_queue.job_manager.beanstalkd' => $definition]);
        $container->setParameter('dtc_queue.beanstalkd.host', 'somehost');
        $container->setParameter('dtc_queue.beanstalkd.tube', 'seomthing');
        $compilerPass = new BeanstalkdCompilerPass();
        $compilerPass->process($container);

        self::assertNotEmpty($container->getDefinitions());
        self::assertTrue($container->hasDefinition('dtc_queue.beanstalkd'));

        $definition = $container->getDefinition('dtc_queue.job_manager.beanstalkd');
        self::assertNotEmpty($definition->getMethodCalls());
        self::assertCount(2, $definition->getMethodCalls());
    }
}
