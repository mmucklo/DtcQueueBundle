<?php

namespace Dtc\QueueBundle\Tests\DependencyInjection\Compiler;

use Dtc\QueueBundle\DependencyInjection\DtcQueueExtension;
use Dtc\QueueBundle\Redis\JobManager;
use Dtc\QueueBundle\DependencyInjection\Compiler\RedisCompilerPass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class RedisCompilerPassTest extends TestCase
{
    public function testProcess()
    {
        $container = new ContainerBuilder();

        $count = count($container->getDefinitions());
        $compilerPass = new RedisCompilerPass();
        $compilerPass->process($container);
        self::assertEquals($count, count($container->getDefinitions()));
    }

    public function testSncRedis()
    {
        $container = new ContainerBuilder();
        $count = count($container->getDefinitions());
        $definition = new Definition();
        $definition->setClass(JobManager::class);
        $container->addDefinitions(['dtc_queue.job_manager.redis' => $definition]);
        $container->setParameter('dtc_queue.redis.snc_redis.type', 'predis');
        $container->setParameter('dtc_queue.redis.snc_redis.alias', 'default');
        $compilerPass = new RedisCompilerPass();
        $compilerPass->process($container);

        self::assertGreaterThan($count, count($container->getDefinitions()));
        self::assertTrue($container->hasDefinition('dtc_queue.predis'));

        $definition = $container->getDefinition('dtc_queue.job_manager.redis');
        self::assertNotEmpty($definition->getMethodCalls());
        self::assertCount(1, $definition->getMethodCalls());

        $container = new ContainerBuilder();
        $count = count($container->getDefinitions());
        $definition = new Definition();
        $definition->setClass(JobManager::class);
        $container->addDefinitions(['dtc_queue.job_manager.redis' => $definition]);
        $container->setParameter('dtc_queue.redis.snc_redis.type', 'phpredis');
        $container->setParameter('dtc_queue.redis.snc_redis.alias', 'default');
        $compilerPass = new RedisCompilerPass();
        $compilerPass->process($container);

        self::assertGreaterThan($count, count($container->getDefinitions()));
        self::assertTrue($container->hasDefinition('dtc_queue.phpredis'));

        $definition = $container->getDefinition('dtc_queue.job_manager.redis');
        self::assertNotEmpty($definition->getMethodCalls());
        self::assertCount(1, $definition->getMethodCalls());
    }

    public function testPredis()
    {
        $container = new ContainerBuilder();
        $definition = new Definition();
        $definition->setClass(JobManager::class);
        $container->addDefinitions(['dtc_queue.job_manager.redis' => $definition]);
        $container->setParameter('dtc_queue.redis.predis.dsn', 'redis://localhost');
        $compilerPass = new RedisCompilerPass();
        $compilerPass->process($container);

        self::assertGreaterThan($count, count($container->getDefinitions()));
        self::assertTrue($container->hasDefinition('dtc_queue.predis'));

        $definition = $container->getDefinition('dtc_queue.job_manager.redis');
        self::assertNotEmpty($definition->getMethodCalls());
        self::assertCount(1, $definition->getMethodCalls());

        $container = new ContainerBuilder();
        $definition = new Definition();
        $definition->setClass(JobManager::class);
        $container->addDefinitions(['dtc_queue.job_manager.redis' => $definition]);
        $container->setParameter('dtc_queue.redis.predis.connection_parameters', ['host' => 'localhost', 'port' => 6379]);
        $compilerPass = new RedisCompilerPass();
        $compilerPass->process($container);

        self::assertGreaterThan($count, count($container->getDefinitions()));
        self::assertTrue($container->hasDefinition('dtc_queue.predis'));

        $definition = $container->getDefinition('dtc_queue.job_manager.redis');
        self::assertNotEmpty($definition->getMethodCalls());
        self::assertCount(1, $definition->getMethodCalls());
    }

    public function testPhpRedis()
    {
        $container = new ContainerBuilder();
        $dtcQueueExtension = new DtcQueueExtension();
        $configs = ['config' => ['redis' => ['phpredis' => ['host' => 'localhost']]]];
        $dtcQueueExtension->load($configs, $container);

        $definition = new Definition();
        $definition->setClass(JobManager::class);
        $container->addDefinitions(['dtc_queue.job_manager.redis' => $definition]);
        $compilerPass = new RedisCompilerPass();
        $compilerPass->process($container);

        self::assertGreaterThan($count, count($container->getDefinitions()));
        self::assertTrue($container->hasDefinition('dtc_queue.phpredis'));

        $definition = $container->getDefinition('dtc_queue.job_manager.redis');
        self::assertNotEmpty($definition->getMethodCalls());
        self::assertCount(1, $definition->getMethodCalls());
    }
}
