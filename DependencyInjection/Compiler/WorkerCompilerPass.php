<?php

namespace Dtc\QueueBundle\DependencyInjection\Compiler;

use Dtc\GridBundle\DependencyInjection\Compiler\GridSourceCompilerPass;
use Dtc\QueueBundle\Model\Job;
use Dtc\QueueBundle\Model\JobTiming;
use Dtc\QueueBundle\Model\Run;
use Dtc\QueueBundle\Exception\ClassNotFoundException;
use Dtc\QueueBundle\Exception\ClassNotSubclassException;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class WorkerCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (false === $container->hasDefinition('dtc_queue.manager.worker')) {
            return;
        }

        $this->setupAliases($container);

        $definition = $container->getDefinition('dtc_queue.manager.worker');

        $jobClass = $this->getJobClass($container);
        $jobArchiveClass = $this->getJobClassArchive($container);
        $container->setParameter('dtc_queue.class.job', $jobClass);
        $container->setParameter('dtc_queue.class.job_archive', $jobArchiveClass);

        $managerType = $this->getRunManagerType($container);
        $jobTimingManagerType = $this->getJobTimingManagerType($container);
        $container->setParameter('dtc_queue.class.job_timing', $this->getClass(
            $container,
            $jobTimingManagerType,
            'job_timing',
            'JobTiming',
            JobTiming::class
        ));
        $container->setParameter('dtc_queue.class.run', $this->getClass($container, $managerType, 'run', 'Run', Run::class));
        $container->setParameter('dtc_queue.class.run_archive', $this->getClass($container, $managerType, 'run_archive', 'RunArchive', Run::class));

        $this->setupTaggedServices($container, $definition);
        $eventDispatcher = $container->getDefinition('dtc_queue.event_dispatcher');
        foreach ($container->findTaggedServiceIds('dtc_queue.event_subscriber') as $id => $attributes) {
            $eventSubscriber = $container->getDefinition($id);
            $eventDispatcher->addMethodCall('addSubscriber', [$eventSubscriber]);
        }
        $this->setupDoctrineManagers($container);
        $this->addLiveJobs($container);
    }

    /**
     * Add any extra method calls needed
     * @param ContainerBuilder $container
     * @param string $defaultManagerType
     */
    protected function addMethodCalls(ContainerBuilder $container, $defaultManagerType) {
        if ($defaultManagerType === 'orm') {
            $doctrine = $container->getDefinition('doctrine');
            $container->getDefinition('dtc_queue.doctrine_listener')->addMethodCall('setRegistry', [$doctrine]);
            $container->getDefinition('dtc_queue.manager.job.orm')->addMethodCall('setRegistry', [$doctrine]);
            $container->getDefinition('dtc_queue.manager.run.orm')->addMethodCall('setRegistry', [$doctrine]);
            $container->getDefinition('dtc_queue.manager.job_timing.orm')->addMethodCall('setRegistry', [$doctrine]);
        }
    }

    /**
     * @param string $type
     */
    protected function setupAlias(ContainerBuilder $container, $defaultManagerType, $type)
    {
        $definitionName = 'dtc_queue.'.$type.'.'.$defaultManagerType;
        if (!$container->hasDefinition($definitionName) && !$container->hasAlias($definitionName)) {
            throw new InvalidConfigurationException("No $type manager found for dtc_queue.$type.$defaultManagerType");
        }
        if ($container->hasDefinition($definitionName)) {
            $alias = new Alias('dtc_queue.'.$type.'.'.$defaultManagerType);
            $alias->setPublic(true);
            $container->setAlias('dtc_queue.'.$type, $alias);

            return;
        }

        $container->getAlias($definitionName)->setPublic(true);
        $container->setAlias('dtc_queue.'.$type, $container->getAlias($definitionName));
    }

    protected function setupAliases(ContainerBuilder $container)
    {
        $defaultManagerType = $container->getParameter('dtc_queue.manager.job');
        $this->addMethodCalls($container, $defaultManagerType);
        $this->setupAlias($container, $defaultManagerType, 'manager.job');
        $runManagerType = $container->getParameter($this->getRunManagerType($container));
        $this->addMethodCalls($container, $runManagerType);
        $this->setupAlias($container, $runManagerType, 'manager.run');
        $jobTimingManagerType = $container->getParameter($this->getJobTimingManagerType($container));
        $this->addMethodCalls($container, $jobTimingManagerType);
        $this->setupAlias($container, $jobTimingManagerType, 'manager.job_timing');
    }

    /**
     * @param ContainerBuilder $container
     * @param Definition       $definition
     */
    protected function setupTaggedServices(ContainerBuilder $container, Definition $definition)
    {
        $jobManagerRef = array(new Reference('dtc_queue.manager.job'));
        // Add each worker to workerManager, make sure each worker has instance to work
        foreach ($container->findTaggedServiceIds('dtc_queue.worker') as $id => $attributes) {
            $worker = $container->getDefinition($id);
            $class = $container->getDefinition($id)->getClass();

            $refClass = new \ReflectionClass($class);
            $workerClass = 'Dtc\QueueBundle\Model\Worker';
            if (!$refClass->isSubclassOf($workerClass)) {
                throw new \InvalidArgumentException(sprintf('Service "%s" must extend class "%s".', $id, $workerClass));
            }

            // Give each worker access to job manager
            $worker->addMethodCall('setJobManager', $jobManagerRef);
            $definition->addMethodCall('addWorker', array(new Reference($id)));
        }
    }

    /**
     * @param ContainerBuilder $container
     */
    protected function setupDoctrineManagers(ContainerBuilder $container)
    {
        $documentManager = $container->getParameter('dtc_queue.odm.document_manager');

        $odmManager = "doctrine_mongodb.odm.{$documentManager}_document_manager";
        if ($container->has($odmManager)) {
            $container->setAlias('dtc_queue.document_manager', $odmManager);
        }

        $entityManager = $container->getParameter('dtc_queue.orm.entity_manager');

        $ormManager = "doctrine.orm.{$entityManager}_entity_manager";
        if ($container->has($ormManager)) {
            $container->setAlias('dtc_queue.entity_manager', $ormManager);
        }
    }

    /**
     * @param ContainerBuilder $container
     */
    protected function addLiveJobs(ContainerBuilder $container)
    {
        $jobReflection = new \ReflectionClass($container->getParameter('dtc_queue.class.job'));
        if ($jobReflection->isInstance(new \Dtc\QueueBundle\Document\Job())) {
            GridSourceCompilerPass::addGridSource($container, 'dtc_queue.grid_source.jobs_waiting.odm');
            GridSourceCompilerPass::addGridSource($container, 'dtc_queue.grid_source.jobs_running.odm');
        }
        if ($jobReflection->isInstance(new \Dtc\QueueBundle\Entity\Job())) {
            GridSourceCompilerPass::addGridSource($container, 'dtc_queue.grid_source.jobs_waiting.orm');
            GridSourceCompilerPass::addGridSource($container, 'dtc_queue.grid_source.jobs_running.orm');
        }
    }

    /**
     * @param $managerType
     *
     * @return null|string
     */
    protected function getDirectory($managerType)
    {
        switch ($managerType) {
            case 'odm':
                return 'Document';
            case 'beanstalkd':
                return 'Beanstalkd';
            case 'rabbit_mq':
                return 'RabbitMQ';
            case 'orm':
                return 'Entity';
            case 'redis':
                return 'Redis';
        }

        return null;
    }

    /**
     * Determines the job class based on the queue manager type.
     *
     * @param ContainerBuilder $container
     *
     * @return mixed|string
     *
     * @throws InvalidConfigurationException
     */
    protected function getJobClass(ContainerBuilder $container)
    {
        $jobClass = $container->getParameter('dtc_queue.class.job');
        if (!$jobClass) {
            if ($directory = $this->getDirectory($managerType = $container->getParameter('dtc_queue.manager.job'))) {
                $jobClass = 'Dtc\QueueBundle\\'.$directory.'\Job';
            } else {
                throw new InvalidConfigurationException('Unknown manager.job type '.$managerType.' - please specify a Job class in the \'class\' configuration parameter');
            }
        }

        $this->testClass($jobClass, Job::class);

        return $jobClass;
    }

    protected function getRunManagerType(ContainerBuilder $container)
    {
        $managerType = 'dtc_queue.manager.job';
        if ($container->hasParameter('dtc_queue.manager.run')) {
            $managerType = 'dtc_queue.manager.run';
        }

        return $managerType;
    }

    protected function getJobTimingManagerType(ContainerBuilder $container)
    {
        $managerType = $this->getRunManagerType($container);
        if ($container->hasParameter('dtc_queue.manager.job_timing')) {
            $managerType = 'dtc_queue.manager.job_timing';
        }

        return $managerType;
    }

    /**
     * @param string $managerType
     * @param string $type
     * @param string $className
     */
    protected function getClass(ContainerBuilder $container, $managerType, $type, $className, $baseClass)
    {
        $runClass = $container->hasParameter('dtc_queue.class.'.$type) ? $container->getParameter('dtc_queue.class.'.$type) : null;
        if (!$runClass) {
            switch ($container->getParameter($managerType)) {
                case 'odm':
                    $runClass = 'Dtc\\QueueBundle\\Document\\'.$className;
                    break;
                case 'orm':
                    $runClass = 'Dtc\\QueueBundle\\Entity\\'.$className;
                    break;
                default:
                    $runClass = $baseClass;
            }
        }

        $this->testClass($runClass, $baseClass);

        return $runClass;
    }

    /**
     * @throws ClassNotFoundException
     * @throws ClassNotSubclassException
     */
    protected function testClass($className, $parent)
    {
        if (!class_exists($className)) {
            throw new ClassNotFoundException("Can't find class $className");
        }

        $test = new $className();
        if (!$test instanceof $className) {
            throw new ClassNotSubclassException("$className must be instance of (or derived from) $parent");
        }
    }

    /**
     * Determines the job class based on the queue manager type.
     *
     * @param ContainerBuilder $container
     *
     * @return mixed|string
     *
     * @throws ClassNotFoundException
     * @throws ClassNotSubclassException
     */
    protected function getJobClassArchive(ContainerBuilder $container)
    {
        $jobArchiveClass = $container->getParameter('dtc_queue.class.job_archive');
        if (!$jobArchiveClass) {
            switch ($container->getParameter('dtc_queue.manager.job')) {
                case 'odm':
                    $jobArchiveClass = 'Dtc\\QueueBundle\\Document\\JobArchive';
                    break;
                case 'orm':
                    $jobArchiveClass = 'Dtc\\QueueBundle\\Entity\\JobArchive';
                    break;
            }
        }
        if (null !== $jobArchiveClass) {
            $this->testClass($jobArchiveClass, Job::class);
        }

        return $jobArchiveClass;
    }
}
