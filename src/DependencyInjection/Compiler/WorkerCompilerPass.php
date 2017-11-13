<?php

namespace Dtc\QueueBundle\DependencyInjection\Compiler;

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
        if (false === $container->hasDefinition('dtc_queue.worker_manager')) {
            return;
        }

        $this->setupAliases($container);

        $definition = $container->getDefinition('dtc_queue.worker_manager');
        $jobManagerRef = array(new Reference('dtc_queue.job_manager'));

        $jobClass = $this->getJobClass($container);
        $jobArchiveClass = $this->getJobClassArchive($container);
        $container->setParameter('dtc_queue.class_job', $jobClass);
        $container->setParameter('dtc_queue.class_job_archive', $jobArchiveClass);
        $container->setParameter('dtc_queue.class_job_timing', $this->getClass($container, 'job_timing',
            'JobTiming', JobTiming::class));
        $container->setParameter('dtc_queue.class_run', $this->getClass($container, 'run', 'Run', Run::class));
        $container->setParameter('dtc_queue.class_run_archive', $this->getClass($container, 'run_archive', 'RunArchive', Run::class));

        $this->setupTaggedServices($container, $definition, $jobManagerRef, $jobClass);
        $eventDispatcher = $container->getDefinition('dtc_queue.event_dispatcher');
        foreach ($container->findTaggedServiceIds('dtc_queue.event_subscriber') as $id => $attributes) {
            $eventSubscriber = $container->getDefinition($id);
            $eventDispatcher->addMethodCall('addSubscriber', [$eventSubscriber]);
        }
        $this->setupDoctrineManagers($container);
    }

    protected function setupAlias(ContainerBuilder $container, $defaultManagerType, $type)
    {
        $definitionName = 'dtc_queue.'.$type.'.'.$defaultManagerType;
        if (!$container->hasDefinition($definitionName) && !$container->hasAlias($definitionName)) {
            throw new InvalidConfigurationException("No job manager found for dtc_queue.'.$type.'.$defaultManagerType");
        }
        if ($container->hasDefinition($definitionName)) {
            $alias = new Alias('dtc_queue.'.$type.'.'.$defaultManagerType);
            $container->setAlias('dtc_queue.'.$type, $alias);
        } else {
            $container->setAlias('dtc_queue.'.$type, $container->getAlias($definitionName));
        }
    }

    protected function setupAliases(ContainerBuilder $container)
    {
        $defaultManagerType = $container->getParameter('dtc_queue.default_manager');
        $this->setupAlias($container, $defaultManagerType, 'job_manager');
        $defaultRunManagerType = $container->getParameter('dtc_queue.run_manager');
        $this->setupAlias($container, $defaultRunManagerType, 'run_manager');
    }

    /**
     * @param ContainerBuilder $container
     * @param Reference[]      $jobManagerRef
     * @param string           $jobClass
     */
    protected function setupTaggedServices(ContainerBuilder $container, Definition $definition, array $jobManagerRef, $jobClass)
    {
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
            $worker->addMethodCall('setJobClass', array($jobClass));

            $definition->addMethodCall('addWorker', array(new Reference($id)));
        }
    }

    protected function setupDoctrineManagers(ContainerBuilder $container)
    {
        $documentManager = $container->getParameter('dtc_queue.document_manager');

        $odmManager = "doctrine_mongodb.odm.{$documentManager}_document_manager";
        if ($container->has($odmManager)) {
            $container->setAlias('dtc_queue.document_manager', $odmManager);
        }

        $entityManager = $container->getParameter('dtc_queue.entity_manager');

        $ormManager = "doctrine.orm.{$entityManager}_entity_manager";
        if ($container->has($ormManager)) {
            $container->setAlias('dtc_queue.entity_manager', $ormManager);
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
            case 'mongodb': // deprecated remove in 3.0
            case 'odm':
                return 'Document';
            case 'beanstalkd':
                return 'Beanstalkd';
            case 'rabbit_mq':
                return 'RabbitMQ';
            case 'orm':
                return 'Entity';
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
        $jobClass = $container->getParameter('dtc_queue.class_job');
        if (!$jobClass) {
            if ($directory = $this->getDirectory($managerType = $container->getParameter('dtc_queue.default_manager'))) {
                $jobClass = 'Dtc\QueueBundle\\'.$directory.'\Job';
            } else {
                throw new InvalidConfigurationException('Unknown default_manager type '.$managerType.' - please specify a Job class in the \'class\' configuration parameter');
            }
        }

        $this->testClass($jobClass, Job::class);

        return $jobClass;
    }

    protected function getClass(ContainerBuilder $container, $type, $className, $baseClass)
    {
        $runClass = $container->hasParameter('dtc_queue.class_'.$type) ? $container->getParameter('dtc_queue.class_'.$type) : null;
        if (!$runClass) {
            switch ($container->getParameter('dtc_queue.default_manager')) {
                case 'mongodb': // deprecated remove in 3.0
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
        $jobArchiveClass = $container->getParameter('dtc_queue.class_job_archive');
        if (!$jobArchiveClass) {
            switch ($container->getParameter('dtc_queue.default_manager')) {
                case 'mongodb': // deprecated remove in 4.0
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
