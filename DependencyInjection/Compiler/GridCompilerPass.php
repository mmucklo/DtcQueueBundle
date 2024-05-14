<?php

namespace Dtc\QueueBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class GridCompilerPass implements CompilerPassInterface
{
    use WorkerCompilerTrait;

    public function process(ContainerBuilder $container): void
    {
        if (!class_exists('Dtc\GridBundle\DtcGridBundle')) {
            $container->getDefinition('dtc_queue.grid_source.jobs_waiting.odm')->setClass('Dtc\QueueBundle\ODM\StubLiveJobsGridSource');
            $container->getDefinition('dtc_queue.grid_source.jobs_waiting.orm')->setClass('Dtc\QueueBundle\ORM\StubLiveJobsGridSource');
            $container->getDefinition('dtc_queue.grid_source.jobs_running.odm')->setClass('Dtc\QueueBundle\ODM\StubLiveJobsGridSource');
            $container->getDefinition('dtc_queue.grid_source.jobs_running.orm')->setClass('Dtc\QueueBundle\ORM\StubLiveJobsGridSource');

            return;
        }
        $container->getDefinition('dtc_queue.grid_source.jobs_waiting.odm')->setClass('Dtc\QueueBundle\ODM\LiveJobsGridSource');
        $container->getDefinition('dtc_queue.grid_source.jobs_waiting.orm')->setClass('Dtc\QueueBundle\ORM\LiveJobsGridSource');
        $container->getDefinition('dtc_queue.grid_source.jobs_running.odm')->setClass('Dtc\QueueBundle\ODM\LiveJobsGridSource');
        $container->getDefinition('dtc_queue.grid_source.jobs_running.orm')->setClass('Dtc\QueueBundle\ORM\LiveJobsGridSource');
        $defaultManagerType = $container->getParameter('dtc_queue.manager.job');
        $runManagerType = $container->getParameter($this->getRunManagerType($container));
        if ('orm' === $defaultManagerType || 'orm' === $runManagerType || 'odm' === $defaultManagerType || 'odm' === $runManagerType) {
            $filename = __DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'Resources'.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'dtc_grid.yaml';
            $cacheDir = $container->getParameter('kernel.cache_dir');
            if (class_exists('Dtc\GridBundle\Util\ColumnUtil')) {
                \Dtc\GridBundle\Util\ColumnUtil::cacheClassesFromFile($cacheDir, $filename);
            }
        }

        $this->addLiveJobs($container);
    }

    /**
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
