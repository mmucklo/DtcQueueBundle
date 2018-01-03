<?php

namespace Dtc\QueueBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class RedisCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if ($container->hasParameter('dtc_queue.redis.snc_redis.type')) {
            $this->processSncRedis($container);

            return;
        }
        if ($container->hasParameter('dtc_queue.redis.predis.dsn')) {
            $this->processPredisDsn($container);

            return;
        }
        if ($container->hasParameter('dtc_queue.redis.predis.connection_parameters')) {
            $this->processPredisConnectionParameters($container);

            return;
        }
    }

    protected function processSncRedis(ContainerBuilder $container)
    {
        $type = $container->getParameter('dtc_queue.redis.snc_redis.type');
        $alias = $container->getParameter('dtc_queue.redis.snc_redis.alias');
        $class = 'PhpRedis';
        if ('predis' == $type) {
            $class = 'Predis';
        }

        $this->setRedis($container, $class, 'snc_redis'.$alias, $type);
    }

    protected function setRedis(ContainerBuilder $container, $class, $reference, $type)
    {
        $definition = new Definition(
            'Dtc\\QueueBundle\\Redis\\'.$class,
            [new Reference($reference)]
        );
        $container->setDefinition('dtc_queue.'.$type, $definition);

        $definition = $container->getDefinition('dtc_queue.job_manager.redis');
        $definition->addMethodCall('setRedis', [new Reference('dtc_queue.'.$type)]);
    }

    protected function processPredisDsn(ContainerBuilder $container)
    {
        $definition = new Definition(
            'Predis\\Client',
            [$container->getParameter('dtc_queue.redis.predis.dsn')]
        );
        $container->setDefinition('dtc_queue.predis.client', $definition);

        $this->setRedis($container, 'Predis', 'dtc_queue.predis.client', 'predis');
    }

    protected function processPhpRedis(ContainerBuilder $container)
    {
        $definition = new Definition(
            'Redis'
        );

        $arguments = [$container->getParameter('dtc_queue.redis.phpredis.host'),
            $container->getParameter('dtc_queue.redis.phpredis.port'),
            $container->getParameter('dtc_queue.redis.phpredis.timeout'),
            null,
            $container->getParameter('dtc_queue.redis.phpredis.retry_interval'),
            $container->getParameter('dtc_queue.redis.phpredis.read_timeout'), ];
        $definition->addMethodCall('connect', $arguments);
        if ($container->hasParameter('dtc_queue.redis.phpredis.auth') && null !== ($auth = $container->getParameter('dtc_queue.redis.phpredis.auth'))) {
            $definition->addMethodCall('auth', $auth);
        }
        $container->setDefinition('dtc_queue.phpredis.connection', $definition);

        $this->setRedis($container, 'PhpRedis', 'dtc_queue.phpredis.connection', 'phpredis');
    }

    protected function processPredisConnectionParameters(ContainerBuilder $container)
    {
        $definition = new Definition(
            'Predis\\Client',
            $container->getParameter('dtc_queue.redis.predis.connection_parameters')
        );
        $container->setDefinition('dtc_queue.predis.client', $definition);

        $this->setRedis($container, 'Predis', 'dtc_queue.predis.client', 'predis');
    }
}
