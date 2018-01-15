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
        $this->configRedis($config, $container);

        $container->setParameter('dtc_queue.manager.job', $config['manager']['job']);
        $container->setParameter('dtc_queue.odm.document_manager', $config['odm']['document_manager']);
        $container->setParameter('dtc_queue.orm.entity_manager', $config['orm']['entity_manager']);
        $container->setParameter('dtc_queue.manager.run', isset($config['manager']['run']) ? $config['manager']['run'] : $config['manager']['job']);
        $container->setParameter('dtc_queue.manager.job_timing', isset($config['manager']['job_timing']) ? $config['manager']['job_timing'] : $container->getParameter('dtc_queue.manager.run'));
        $container->setParameter('dtc_queue.priority.direction', $config['priority']['direction']);
        $container->setParameter('dtc_queue.priority.max', $config['priority']['max']);
        $container->setParameter('dtc_queue.retry.max.retries', $config['retry']['max']['retries']);
        $container->setParameter('dtc_queue.retry.max.failures', $config['retry']['max']['failures']);
        $container->setParameter('dtc_queue.retry.max.exceptions', $config['retry']['max']['exceptions']);
        $container->setParameter('dtc_queue.retry.max.stalls', $config['retry']['max']['stalls']);
        $container->setParameter('dtc_queue.retry.auto.failure', $config['retry']['auto']['failure']);
        $container->setParameter('dtc_queue.retry.auto.exception', $config['retry']['auto']['exception']);
        $this->configClasses($config, $container);
        $this->configRecordTimings($config, $container);
        $this->configAdmin($config, $container);
        $this->configDeprecated($config, $container);

        // Load Grid if Dtc\GridBundle Bundle is registered
        $yamlLoader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));

        $yamlLoader->load('queue.yml');
    }

    protected function configDeprecated(array $config, ContainerBuilder $container)
    {
        if (isset($config['default_manager'])) {
            $container->setParameter('dtc_queue.manager.job', $config['default_manager']);
        }
        if (isset($config['run_manager'])) {
            $container->setParameter('dtc_queue.manager.run', $config['run_manager']);
        }
        if (isset($config['job_timing_manager'])) {
            $container->setParameter('dtc_queue.manager.job_timing', $config['job_timing_manager']);
        }
        if (isset($config['document_manager'])) {
            $container->setParameter('dtc_queue.odm.document_manager', $config['document_manager']);
        }
        if (isset($config['entity_manager'])) {
            $container->setParameter('dtc_queue.orm.entity_manager', $config['entity_manager']);
        }
        $this->configClassDeprecated($config, $container);
        $this->configOtherDeprecated($config, $container);
    }

    protected function configClassDeprecated(array $config, ContainerBuilder $container)
    {
        if (isset($config['class_job'])) {
            $container->setParameter('dtc_queue.class.job', $config['class_job']);
        }
        if (isset($config['class_job_archive'])) {
            $container->setParameter('dtc_queue.class.job_archive', $config['class_job_archive']);
        }
        if (isset($config['class_run'])) {
            $container->setParameter('dtc_queue.class.run', $config['class_run']);
        }
        if (isset($config['class_run_archive'])) {
            $container->setParameter('dtc_queue.class.run_archive', $config['class_run_archive']);
        }
        if (isset($config['class_job_timing'])) {
            $container->setParameter('dtc_queue.class.job_timing', $config['class_job_timing']);
        }
    }

    protected function configOtherDeprecated(array $config, ContainerBuilder $container)
    {
        if (isset($config['record_timings'])) {
            $container->setParameter('dtc_queue.timings.record', $config['record_timings']);
        }
        if (isset($config['record_timings_timezone_offset'])) {
            $container->setParameter('dtc_queue.timings.timezone_offset', $config['record_timings_timezone_offset']);
        }
        if (isset($config['record_timings_timezone_offset'])) {
            $container->setParameter('dtc_queue.timings.timezone_offset', $config['record_timings_timezone_offset']);
        }
        if (isset($config['priority_max'])) {
            $container->setParameter('dtc_queue.priority.max', $config['priority_max']);
        }
        if (isset($config['priority_direction'])) {
            $container->setParameter('dtc_queue.priority.direction', $config['priority_direction']);
        }
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
        }
        $this->configPhpRedis($config, $container);
    }

    protected function configPhpRedis(array $config, ContainerBuilder $container)
    {
        $container->setParameter('dtc_queue.redis.phpredis.host', isset($config['redis']['phpredis']['host']) ? $config['redis']['phpredis']['host'] : null);
        $container->setParameter('dtc_queue.redis.phpredis.port', isset($config['redis']['phpredis']['port']) ? $config['redis']['phpredis']['port'] : null);
        $container->setParameter('dtc_queue.redis.phpredis.timeout', isset($config['redis']['phpredis']['timeout']) ? $config['redis']['phpredis']['timeout'] : null);
        $container->setParameter('dtc_queue.redis.phpredis.retry_interval', isset($config['redis']['phpredis']['retry_interval']) ? $config['redis']['phpredis']['retry_interval'] : null);
        $container->setParameter('dtc_queue.redis.phpredis.read_timeout', isset($config['redis']['phpredis']['read_timeout']) ? $config['redis']['phpredis']['read_timeout'] : null);
        if (isset($config['redis']['phpredis']['auth'])) {
            $container->setParameter('dtc_queue.redis.phpredis.auth', $config['redis']['phpredis']['auth']);
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
        $container->setParameter('dtc_queue.timings.record', isset($config['timings']['record']) ? $config['timings']['record'] : false);
        $container->setParameter('dtc_queue.timings.timezone_offset', $config['timings']['timezone_offset']);
    }

    protected function configRabbitMQ(array $config, ContainerBuilder $container)
    {
        if (isset($config['rabbit_mq'])) {
            foreach (['host', 'port', 'user', 'password'] as $value) {
                if (!isset($config['rabbit_mq'][$value])) {
                    throw new InvalidConfigurationException('dtc_queue: rabbit_mq must have '.$value.' in config.yml');
                }
            }
            $config['rabbit_mq']['queue_args']['max_priority'] = $config['priority']['max'];
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
