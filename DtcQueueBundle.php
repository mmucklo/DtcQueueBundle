<?php

namespace Dtc\QueueBundle;

use Dtc\QueueBundle\DependencyInjection\Compiler\BeanstalkdCompilerPass;
use Dtc\QueueBundle\DependencyInjection\Compiler\GridCompilerPass;
use Dtc\QueueBundle\DependencyInjection\Compiler\RabbitMQCompilerPass;
use Dtc\QueueBundle\DependencyInjection\Compiler\RedisCompilerPass;
use Dtc\QueueBundle\DependencyInjection\Compiler\WorkerCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class DtcQueueBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new WorkerCompilerPass());
        $container->addCompilerPass(new RabbitMQCompilerPass());
        $container->addCompilerPass(new BeanstalkdCompilerPass());
        $container->addCompilerPass(new RedisCompilerPass());
        $container->addCompilerPass(new GridCompilerPass());
    }
}
