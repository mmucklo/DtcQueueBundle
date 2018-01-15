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
        $container = $this->setupContainer($rabbitMQOptions);

        self::assertGreaterThan(0, count($container->getDefinitions()));
        self::assertTrue($container->hasDefinition('dtc_queue.rabbit_mq'));
        self::assertEquals(AMQPStreamConnection::class, $container->getDefinition('dtc_queue.rabbit_mq')->getClass());
        self::assertCount(5, $container->getDefinition('dtc_queue.rabbit_mq')->getArguments());

        $rabbitMQOptions['ssl'] = true;
        $container = $this->setupContainer($rabbitMQOptions);
        self::assertEquals(AMQPSSLConnection::class, $container->getDefinition('dtc_queue.rabbit_mq')->getClass());
        self::assertCount(6, $container->getDefinition('dtc_queue.rabbit_mq')->getArguments());

        $rabbitMQOptions['ssl_options']['verify_peer'] = false;
        $container = $this->setupContainer($rabbitMQOptions);
        self::assertEquals(AMQPSSLConnection::class, $container->getDefinition('dtc_queue.rabbit_mq')->getClass());
        $arguments = $container->getDefinition('dtc_queue.rabbit_mq')->getArguments();
        self::assertCount(6, $arguments);
        self::assertArrayHasKey('verify_peer', $arguments[5]);

        $rabbitMQOptions['options']['insist'] = true;
        $container = $this->setupContainer($rabbitMQOptions);
        self::assertEquals(AMQPSSLConnection::class, $container->getDefinition('dtc_queue.rabbit_mq')->getClass());
        $arguments = $container->getDefinition('dtc_queue.rabbit_mq')->getArguments();
        self::assertCount(7, $arguments);
        self::assertArrayHasKey('insist', $arguments[6]);

        $rabbitMQOptions['ssl'] = false;
        $container = $this->setupContainer($rabbitMQOptions);
        $arguments = $container->getDefinition('dtc_queue.rabbit_mq')->getArguments();
        self::assertCount(14, $arguments);
        self::assertTrue($arguments[5]);
    }

    public function setupContainer(array $options)
    {
        $container = new ContainerBuilder();
        $container->setParameter('dtc_queue.rabbit_mq', $options);
        $definition = new Definition();
        $definition->setClass(JobManager::class);
        $container->addDefinitions(['dtc_queue.manager.job.rabbit_mq' => $definition]);
        $compilerPass = new RabbitMQCompilerPass();
        $compilerPass->process($container);

        return $container;
    }
}
