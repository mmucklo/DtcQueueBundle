<?php

namespace Dtc\QueueBundle\DependencyInjection\Compiler;

use Dtc\QueueBundle\Model\Job;
use Pheanstalk\Pheanstalk;
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

        $defaultManagerType = $container->getParameter('dtc_queue.default');
        if (!$container->hasDefinition('dtc_queue.job_manager.' . $defaultManagerType)) {
            throw new \Exception("No job manager found for dtc_queue.job_manager.$defaultManagerType");
        }

        $alias = new Alias('dtc_queue.job_manager.'.$defaultManagerType);
        $container->setAlias('dtc_queue.job_manager', $alias);

        // Setup beanstalkd if configuration is present
        if ($container->hasParameter('dtc_queue.beanstalkd.host')) {
            $definition = new Definition('Pheanstalk\\Pheanstalk', [$container->getParameter('dtc_queue.beanstalkd.host')]);
            $container->setDefinition('dtc_queue.beanstalkd', $definition);
            $definition = $container->getDefinition('dtc_queue.job_manager.beanstalkd');
            $definition->addMethodCall('setBeanstalkd', [new Reference('dtc_queue.beanstalkd')]);
            if ($container->hasParameter('dtc_queue.beanstalkd.tube')) {
                $definition->addMethodCall('setTube', [$container->getParameter('dtc_queue.beanstalkd.tube')]);
            }
        }

        $definition = $container->getDefinition('dtc_queue.worker_manager');
        $jobManagerRef = array(new Reference('dtc_queue.job_manager'));

        $jobClass = $this->getJobClass($container);

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

        $eventDispatcher = $container->getDefinition('dtc_queue.event_dispatcher');
        foreach ($container->findTaggedServiceIds('dtc_queue.event_subscriber') as $id => $attributes) {
            $eventSubscriber = $container->getDefinition($id);
            $eventDispatcher->addMethodCall('addSubscriber', [$eventSubscriber]);
        }
    }

    public function getJobClass(ContainerBuilder $container) {
        $jobClass = $container->getParameter('dtc_queue.job_class');
        if (!$jobClass) {
            switch ($defaultType = $container->getParameter('dtc_queue.default')) {
                case 'mongodb':
                    $jobClass = "Dtc\\QueueBundle\\Documents\\Job";
                    break;
                case 'beanstalkd':
                    $jobClass = "Dtc\\QueueBundle\\Beanstalkd\\Job";
                    break;
                default:
                    throw new \Exception("Unknown type $defaultType - please specify a Job class in the 'class' configuration parameter");
            }
        }

        if (!class_exists($jobClass)) {
            throw new \Exception("Can't find Job class $jobClass");
        }
        return $jobClass;
    }
}
