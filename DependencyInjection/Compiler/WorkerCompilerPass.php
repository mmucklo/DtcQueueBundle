<?php

namespace Dtc\QueueBundle\DependencyInjection\Compiler;

use Dtc\QueueBundle\Model\Job;
use Dtc\QueueBundle\Model\JobTiming;
use Dtc\QueueBundle\Model\Run;
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
            'JobTimingTest', JobTiming::class));
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

    protected function setupAliases(ContainerBuilder $container)
    {
        $defaultManagerType = $container->getParameter('dtc_queue.default_manager');
        if (!$container->hasDefinition('dtc_queue.job_manager.'.$defaultManagerType) && !$container->hasAlias('dtc_queue.job_manager.'.$defaultManagerType)) {
            throw new \Exception("No job manager found for dtc_queue.job_manager.$defaultManagerType");
        }

        $defaultRunManagerType = $container->getParameter('dtc_queue.run_manager');
        if (!$container->hasDefinition('dtc_queue.run_manager.'.$defaultRunManagerType)) {
            throw new \Exception("No run manager found for dtc_queue.run_manager.$defaultRunManagerType");
        }

        $alias = new Alias('dtc_queue.job_manager.'.$defaultManagerType);
        $container->setAlias('dtc_queue.job_manager', $alias);

        $alias = new Alias('dtc_queue.run_manager.'.$defaultRunManagerType);
        $container->setAlias('dtc_queue.run_manager', $alias);
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
     * Determines the job class based on the queue manager type.
     *
     * @param ContainerBuilder $container
     *
     * @return mixed|string
     *
     * @throws \Exception
     */
    protected function getJobClass(ContainerBuilder $container)
    {
        $jobClass = $container->getParameter('dtc_queue.class_job');
        if (!$jobClass) {
            switch ($defaultType = $container->getParameter('dtc_queue.default_manager')) {
                case 'mongodb': // deprecated remove in 4.0
                case 'odm':
                    $jobClass = 'Dtc\\QueueBundle\\Document\\Job';
                    break;
                case 'beanstalkd':
                    $jobClass = 'Dtc\\QueueBundle\\Beanstalkd\\Job';
                    break;
                case 'rabbit_mq':
                    $jobClass = 'Dtc\\QueueBundle\\RabbitMQ\\Job';
                    break;
                case 'orm':
                    $jobClass = 'Dtc\\QueueBundle\\Entity\\Job';
                    break;
                default:
                    throw new \Exception('Unknown default_manager type '.$defaultType.' - please specify a Job class in the \'class\' configuration parameter');
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
                case 'mongodb': // deprecated remove in 4.0
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
     * @throws \Exception
     */
    protected function testClass($className, $parent)
    {
        if (!class_exists($className)) {
            throw new \Exception("Can't find class $className");
        }

        $test = new $className();
        if (!$test instanceof $className) {
            throw new \Exception("$className must be instance of (or derived from) $parent");
        }
    }

    /**
     * Determines the job class based on the queue manager type.
     *
     * @param ContainerBuilder $container
     *
     * @return mixed|string
     *
     * @throws \Exception
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
