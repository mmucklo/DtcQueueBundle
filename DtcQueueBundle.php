<?php
namespace Dtc\QueueBundle;

use Dtc\QueueBundle\DependencyInjection\Compiler\WorkerCompilerPass;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class DtcQueueBundle
	extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);
        $container->addCompilerPass(new WorkerCompilerPass());
    }
}
