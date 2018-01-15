<?php

namespace Dtc\QueueBundle;

use Dtc\QueueBundle\DependencyInjection\Compiler\BeanstalkdCompilerPass;
use Dtc\QueueBundle\DependencyInjection\Compiler\RabbitMQCompilerPass;
use Dtc\QueueBundle\DependencyInjection\Compiler\RedisCompilerPass;
use Dtc\QueueBundle\DependencyInjection\Compiler\WorkerCompilerPass;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class DtcQueueBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);
        $container->addCompilerPass(new WorkerCompilerPass());
        $container->addCompilerPass(new RabbitMQCompilerPass());
        $container->addCompilerPass(new BeanstalkdCompilerPass());
        $container->addCompilerPass(new RedisCompilerPass());
    }
}
