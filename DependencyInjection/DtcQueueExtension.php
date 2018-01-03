<?php

namespace Dtc\QueueBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Loader;

class DtcQueueExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $processor = new Processor();
        $configuration = new Configuration();

        $config = $processor->processConfiguration($configuration, $configs);
        $this->configBeanstalkd($config, $container);
        $this->configRabbitMQ($config, $container);

        $container->setParameter('dtc_queue.default_manager', $config['default_manager']);
        $container->setParameter('dtc_queue.document_manager', $config['document_manager']);
        $container->setParameter('dtc_queue.entity_manager', $config['entity_manager']);
        $container->setParameter('dtc_queue.run_manager', isset($config['run_manager']) ? $config['run_manager'] : $config['default_manager']);
        $container->setParameter('dtc_queue.priority.direction', $config['priority']['direction']);
        $container->setParameter('dtc_queue.priority.max', $config['priority']['max']);
        $container->setParameter('dtc_queue.retry.max.retry', $config['retry']['max']['retry']);
        $container->setParameter('dtc_queue.retry.max.failure', $config['retry']['max']['failure']);
        $container->setParameter('dtc_queue.retry.max.exception', $config['retry']['max']['exception']);
        $container->setParameter('dtc_queue.retry.max.stalled', $config['retry']['max']['stalled']);
        $container->setParameter('dtc_queue.retry.auto.failure', $config['retry']['auto']['failure']);
        $container->setParameter('dtc_queue.retry.auto.exception', $config['retry']['auto']['exception']);
        $this->configClasses($config, $container);
        $this->configRecordTimings($config, $container);
        $this->configAdmin($config, $container);

        // Load Grid if Dtc\GridBundle Bundle is registered
        $yamlLoader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));

        $yamlLoader->load('queue.yml');
    }

    protected function configRedis(array $config, ContainerBuilder $container)
    {
        $container->setParameter('dtc_queue.redis.prefix', $config['redis']['prefix']);
        if (isset($config['redis']['snc_redis']['type'])) {
            $container->setParameter('dtc_queue.redis.snc_redis.type', $config['redis']['snc_redis']['type']);
            $container->setParameter('dtc_queue.redis.snc_redis.alias', $config['redis']['snc_redis']['alias']);
        } elseif (isset($config['redis']['predis']['dsn'])) {
            $container->setParameter('dtc_queue.redis.predis.dsn', $config['redis']['predis']['dsn']);
        } elseif (isset($config['redis']['predis']['connection_parameters']['host'])) {
            $container->setParameter('dtc_queue.redis.predis.connection_parameters', $config['redis']['predis']['connection_parameters']);
        } elseif (isset($config['redis']['phpredis']['host'])) {
            $container->setParameter('dtc_queue.redis.phpredis', $config['redis']['phpredis']);
        }
    }

    protected function configAdmin(array $config, ContainerBuilder $container)
    {
        $container->setParameter('dtc_queue.admin.chartjs', $config['admin']['chartjs']);
    }

    protected function configClasses(array $config, ContainerBuilder $container)
    {
        $container->setParameter('dtc_queue.class.job', isset($config['class']['job']) ? $config['class']['job'] : null);
        $container->setParameter('dtc_queue.class.job_archive', isset($config['class']['job_archive']) ? $config['class']['job_archive'] : null);
        $container->setParameter('dtc_queue.class.run', isset($config['class']['run']) ? $config['class']['run'] : null);
        $container->setParameter('dtc_queue.class.run_archive', isset($config['class']['run_archive']) ? $config['class']['run_archive'] : null);
        $container->setParameter('dtc_queue.class.job_timing', isset($config['class']['job_timing']) ? $config['class']['job_timing'] : null);
    }

    protected function configRecordTimings(array $config, ContainerBuilder $container)
    {
        $container->setParameter('dtc_queue.record_timings', isset($config['record_timings']) ? $config['record_timings'] : false);
        $container->setParameter('dtc_queue.record_timings_timezone_offset', $config['record_timings_timezone_offset']);
        if ($config['record_timings_timezone_offset'] > 24 || $config['record_timings_timezone_offset'] < -24) {
            throw new \InvalidArgumentException('Invalid record_timings_timezone_offset: '.$config['record_timings_timezone_offset']);
        }
    }

    protected function configRabbitMQ(array $config, ContainerBuilder $container)
    {
        if (isset($config['rabbit_mq'])) {
            foreach (['host', 'port', 'user', 'password'] as $value) {
                if (!isset($config['rabbit_mq'][$value])) {
                    throw new InvalidConfigurationException('dtc_queue: rabbit_mq must have '.$value.' in config.yml');
                }
            }
            $config['rabbit_mq']['queue_args']['max_priority'] = $config['priority_max'];
            $container->setParameter('dtc_queue.rabbit_mq', $config['rabbit_mq']);
        }
    }

    protected function configBeanstalkd(array $config, ContainerBuilder $container)
    {
        if (isset($config['beanstalkd'])) {
            if (!isset($config['beanstalkd']['host'])) {
                throw new InvalidConfigurationException('dtc_queue: beanstalkd requires host in config.yml');
            }
        }

        if (isset($config['beanstalkd']['host'])) {
            $container->setParameter('dtc_queue.beanstalkd.host', $config['beanstalkd']['host']);
        }
        if (isset($config['beanstalkd']['tube'])) {
            $container->setParameter('dtc_queue.beanstalkd.tube', $config['beanstalkd']['tube']);
        }
    }

    public function getAlias()
    {
        return 'dtc_queue';
    }
}
