<?php

namespace Dtc\QueueBundle\Tests\DependencyInjection\Compiler;

use Dtc\QueueBundle\RabbitMQ\JobManager;
use Dtc\QueueBundle\DependencyInjection\Compiler\RabbitMQCompilerPass;
use PhpAmqpLib\Connection\AMQPSSLConnection;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class RabbitMQCompilerPassTest extends TestCase
{
    public function testProcess()
    {
        $container = new ContainerBuilder();

        $count = count($container->getDefinitions());
        $compilerPass = new RabbitMQCompilerPass();
        $compilerPass->process($container);
        self::assertEquals($count, count($container->getDefinitions()));

        $rabbitMQOptions = [
            'host' => 'somehost',
            'port' => 1234,
            'user' => 'asdf',
            'password' => 'pass',
            'vhost' => 'vhoster',
            'queue_args' => [],
            'exchange_args' => [],
        ];
        $container = new ContainerBuilder();
        $count = count($container->getDefinitions());
        $container->setParameter('dtc_queue.rabbit_mq', $rabbitMQOptions);
        $definition = new Definition();
        $definition->setClass(JobManager::class);
        $container->addDefinitions(['dtc_queue.job_manager.rabbit_mq' => $definition]);
        $compilerPass = new RabbitMQCompilerPass();
        $compilerPass->process($container);

        self::assertGreaterThan($count, count($container->getDefinitions()));
        self::assertTrue($container->hasDefinition('dtc_queue.rabbit_mq'));
        self::assertEquals(AMQPStreamConnection::class, $container->getDefinition('dtc_queue.rabbit_mq')->getClass());

        $rabbitMQOptions = [
            'host' => 'somehost',
            'port' => 1234,
            'user' => 'asdf',
            'password' => 'pass',
            'vhost' => 'vhoster',
            'ssl' => true,
            'queue_args' => [],
            'exchange_args' => [],
        ];
        $container = new ContainerBuilder();
        $count = count($container->getDefinitions());
        $container->setParameter('dtc_queue.rabbit_mq', $rabbitMQOptions);
        $definition = new Definition();
        $definition->setClass(JobManager::class);
        $container->addDefinitions(['dtc_queue.job_manager.rabbit_mq' => $definition]);
        $compilerPass = new RabbitMQCompilerPass();
        $compilerPass->process($container);

        self::assertGreaterThan($count, count($container->getDefinitions()));
        self::assertTrue($container->hasDefinition('dtc_queue.rabbit_mq'));
        self::assertEquals(AMQPSSLConnection::class, $container->getDefinition('dtc_queue.rabbit_mq')->getClass());
    }
}
