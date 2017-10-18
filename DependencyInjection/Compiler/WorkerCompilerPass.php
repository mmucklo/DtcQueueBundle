<?php

namespace Dtc\QueueBundle\DependencyInjection\Compiler;

use Dtc\QueueBundle\Model\Job;
use Dtc\QueueBundle\Model\Run;
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

        $defaultManagerType = $container->getParameter('dtc_queue.default_manager');
        if (!$container->hasDefinition('dtc_queue.job_manager.'.$defaultManagerType)) {
            throw new \Exception("No job manager found for dtc_queue.job_manager.$defaultManagerType");
        }

        $alias = new Alias('dtc_queue.job_manager.'.$defaultManagerType);
        $container->setAlias('dtc_queue.job_manager', $alias);

        // Setup beanstalkd if configuration is present
        $this->setupBeanstalkd($container);
        $this->setupRabbitMQ($container);

        $definition = $container->getDefinition('dtc_queue.worker_manager');
        $jobManagerRef = array(new Reference('dtc_queue.job_manager'));

        $jobClass = $this->getJobClass($container);
        $jobArchiveClass = $this->getJobClassArchive($container);
        $container->setParameter('dtc_queue.class_job', $jobClass);
        $container->setParameter('dtc_queue.class_job_archive', $jobArchiveClass);
        $container->setParameter('dtc_queue.class_run', $this->getRunArchiveClass($container, 'run', 'Run'));
        $container->setParameter('dtc_queue.class_run_archive', $this->getRunArchiveClass($container, 'run_archive', 'RunArchive'));

        $this->setupTaggedServices($container, $definition, $jobManagerRef, $jobClass);
        $eventDispatcher = $container->getDefinition('dtc_queue.event_dispatcher');
        foreach ($container->findTaggedServiceIds('dtc_queue.event_subscriber') as $id => $attributes) {
            $eventSubscriber = $container->getDefinition($id);
            $eventDispatcher->addMethodCall('addSubscriber', [$eventSubscriber]);
        }
        $this->setupDoctrineManagers($container);
    }

    /**
     * @param ContainerBuilder $container
     * @param array            $jobManagerRef
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

    /**
     * Sets up beanstalkd instance if appropriate.
     *
     * @param ContainerBuilder $container
     */
    protected function setupBeanstalkd(ContainerBuilder $container)
    {
        if ($container->hasParameter('dtc_queue.beanstalkd.host')) {
            $definition = new Definition('Pheanstalk\\Pheanstalk', [$container->getParameter('dtc_queue.beanstalkd.host')]);
            $container->setDefinition('dtc_queue.beanstalkd', $definition);
            $definition = $container->getDefinition('dtc_queue.job_manager.beanstalkd');
            $definition->addMethodCall('setBeanstalkd', [new Reference('dtc_queue.beanstalkd')]);
            if ($container->hasParameter('dtc_queue.beanstalkd.tube')) {
                $definition->addMethodCall('setTube', [$container->getParameter('dtc_queue.beanstalkd.tube')]);
            }
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
     * Sets up RabbitMQ instance if appropriate.
     *
     * @param ContainerBuilder $container
     */
    protected function setupRabbitMQ(ContainerBuilder $container)
    {
        if ($container->hasParameter('dtc_queue.rabbit_mq')) {
            $class = 'PhpAmqpLib\\Connection\\AMQPStreamConnection';
            $rabbitMqConfig = $container->getParameter('dtc_queue.rabbit_mq');
            $arguments = [
                $rabbitMqConfig['host'],
                $rabbitMqConfig['port'],
                $rabbitMqConfig['user'],
                $rabbitMqConfig['password'],
                $rabbitMqConfig['vhost'],
            ];

            $this->setupRabbitMQOptions($container, $arguments, $class);
            $definition = new Definition($class, $arguments);
            $container->setDefinition('dtc_queue.rabbit_mq', $definition);
            $definition = $container->getDefinition('dtc_queue.job_manager.rabbit_mq');
            $definition->addMethodCall('setAMQPConnection', [new Reference('dtc_queue.rabbit_mq')]);
            $definition->addMethodCall('setQueueArgs', array_values($rabbitMqConfig['queue_args']));
            $definition->addMethodCall('setExchangeArgs', array_values($rabbitMqConfig['exchange_args']));
        }
    }

    /**
     * @param ContainerBuilder $container
     * @param array            $arguments
     * @param $class
     */
    protected function setupRabbitMQOptions(ContainerBuilder $container, array &$arguments, &$class)
    {
        if ($container->hasParameter('dtc_queue.rabbit_mq.ssl') && $container->getParameter('dtc_queue.rabbit_mq.ssl')) {
            $class = 'PhpAmqpLib\\Connection\\AMQPSSLConnection';
            if ($container->hasParameter('dtc_queue.rabbit_mq.ssl_options')) {
                $arguments[] = $container->getParameter('dtc_queue.rabbit_mq.ssl_options');
            } else {
                $arguments[] = [];
            }
            if ($container->hasParameter('dtc_queue.rabbit_mq.options')) {
                $arguments[] = $container->getParameter('dtc_queue.rabbit_mq.options');
            }
        } else {
            if ($container->hasParameter('dtc_queue.rabbit_mq.options')) {
                $options = $container->getParameter('dtc_queue.rabbit_mq.options');
                $this->setRabbitMqOptionsPt1($arguments, $options);
                $this->setRabbitMqOptionsPt2($arguments, $options);
            }
        }
    }

    protected function setRabbitMqOptionsPt1(array &$arguments, array $options)
    {
        if (isset($options['insist'])) {
            $arguments[] = $options['insist'];
        } else {
            $arguments[] = false;
        }
        if (isset($options['login_method'])) {
            $arguments[] = $options['login_method'];
        } else {
            $arguments[] = 'AMQPLAIN';
        }
        if (isset($options['login_response'])) {
            $arguments[] = $options['login_response'];
        } else {
            $arguments[] = null;
        }
        if (isset($options['locale'])) {
            $arguments[] = $options['locale'];
        } else {
            $arguments[] = 'en_US';
        }
    }

    protected function setRabbitMqOptionsPt2(array &$arguments, array $options)
    {
        if (isset($options['connection_timeout'])) {
            $arguments[] = $options['connection_timeout'];
        } else {
            $arguments[] = 3.0;
        }
        if (isset($options['read_write_timeout'])) {
            $arguments[] = $options['read_write_timeout'];
        } else {
            $arguments[] = 3.0;
        }
        if (isset($options['context'])) {
            $arguments[] = $options['context'];
        } else {
            $arguments[] = null;
        }
        if (isset($options['keepalive'])) {
            $arguments[] = $options['keepalive'];
        } else {
            $arguments[] = false;
        }
        if (isset($options['heartbeat'])) {
            $arguments[] = $options['heartbeat'];
        } else {
            $arguments[] = 0;
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
                case 'mongodb':
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

        $this->testJobClass($jobClass);

        return $jobClass;
    }

    protected function getRunArchiveClass(ContainerBuilder $container, $type, $className)
    {
        $runArchiveClass = $container->hasParameter('dtc_queue.class_'.$type) ? $container->getParameter('dtc_queue.class_'.$type) : null;
        if (!$runArchiveClass) {
            switch ($container->getParameter('dtc_queue.default_manager')) {
                case 'mongodb':
                    $runArchiveClass = 'Dtc\\QueueBundle\\Document\\'.$className;
                    break;
                case 'orm':
                    $runArchiveClass = 'Dtc\\QueueBundle\\Entity\\'.$className;
                    break;
                default:
                    $runArchiveClass = 'Dtc\\QueueBundle\\Model\\Run';
            }
        }

        $this->testRunClass($runArchiveClass);

        return $runArchiveClass;
    }

    /**
     * @param string $runClass
     *
     * @throws \Exception
     */
    protected function testRunClass($runClass)
    {
        if (!class_exists($runClass)) {
            throw new \Exception("Can't find class $runClass");
        }

        $test = new $runClass();
        if (!$test instanceof Run) {
            throw new \Exception("$runClass must be instance of (or derived from) Dtc\\QueueBundle\\Model\\Run");
        }
    }

    /**
     * @param string|null $jobArchiveClass
     *
     * @throws \Exception
     */
    protected function testJobClass($jobClass)
    {
        if ($jobClass) {
            if (!class_exists($jobClass)) {
                throw new \Exception("Can't find class $jobClass");
            }

            $test = new $jobClass();
            if (!$test instanceof Job) {
                throw new \Exception("$jobClass must be instance of (or derived from) Dtc\\QueueBundle\\Model\\Job");
            }
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
                case 'mongodb':
                    $jobArchiveClass = 'Dtc\\QueueBundle\\Document\\JobArchive';
                    break;
                case 'orm':
                    $jobArchiveClass = 'Dtc\\QueueBundle\\Entity\\JobArchive';
                    break;
            }
        }
        $this->testJobClass($jobArchiveClass);

        return $jobArchiveClass;
    }
}
