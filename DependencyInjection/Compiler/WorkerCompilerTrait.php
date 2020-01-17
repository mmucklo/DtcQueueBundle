<?php

namespace Dtc\QueueBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;

trait WorkerCompilerTrait
{
    protected function getRunManagerType(ContainerBuilder $container)
    {
        $managerType = 'dtc_queue.manager.job';
        if ($container->hasParameter('dtc_queue.manager.run')) {
            $managerType = 'dtc_queue.manager.run';
        }

        return $managerType;
    }
}
