<?php

namespace Dtc\QueueBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

class GridCompilerPass implements CompilerPassInterface
{
    use WorkerCompilerTrait;
    public function process(ContainerBuilder $container)
    {
        if (!class_exists('Dtc\GridBundle\DtcGridBundle')) {
            return;
        }

        $defaultManagerType = $container->getParameter('dtc_queue.manager.job');
        $runManagerType = $container->getParameter($this->getRunManagerType($container));
        if ($defaultManagerType === 'orm' || $runManagerType === 'orm' || $defaultManagerType === 'odm' || $runManagerType === 'odm') {
            $filename =__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'Resources' . DIRECTORY_SEPARATOR . 'dtc_grid.yaml';
            $cacheDir = $container->getParameter('kernel.cache_dir');
            if (class_exists('Dtc\GridBundle\Grid\Source\ColumnSource')) {
                \Dtc\GridBundle\Grid\Source\ColumnSource::cacheClassesFromFile($cacheDir, $filename);
            }
        }

        $this->addLiveJobs($container);
    }


    /**
     * @param ContainerBuilder $container
     * @throws
     */
    protected function addLiveJobs(ContainerBuilder $container)
    {
        $jobReflection = new \ReflectionClass($container->getParameter('dtc_queue.class.job'));

        // Custom grid sources for waiting and running jobs.
        if ($jobReflection->isSubclassOf(\Dtc\QueueBundle\Document\BaseJob::class)) {
            \Dtc\GridBundle\DependencyInjection\Compiler\GridSourceCompilerPass::addGridSource($container, 'dtc_queue.grid_source.jobs_waiting.odm');
            \Dtc\GridBundle\DependencyInjection\Compiler\GridSourceCompilerPass::addGridSource($container, 'dtc_queue.grid_source.jobs_running.odm');
        }
        if ($jobReflection->isSubclassOf(\Dtc\QueueBundle\Entity\BaseJob::class)) {
            \Dtc\GridBundle\DependencyInjection\Compiler\GridSourceCompilerPass::addGridSource($container, 'dtc_queue.grid_source.jobs_waiting.orm');
            \Dtc\GridBundle\DependencyInjection\Compiler\GridSourceCompilerPass::addGridSource($container, 'dtc_queue.grid_source.jobs_running.orm');
        }
    }


}
