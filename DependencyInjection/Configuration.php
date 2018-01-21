<?php

namespace Dtc\QueueBundle\DependencyInjection;

use Dtc\QueueBundle\Manager\PriorityJobManager;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\HttpKernel\Kernel;

class Configuration implements ConfigurationInterface
{
    /**
     * Generates the configuration tree.
     *
     * @return TreeBuilder
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('dtc_queue');

        $node = $rootNode
            ->children()
                ->append($this->addOrm())
                ->append($this->addOdm())
                ->append($this->addManager())
                ->append($this->addTimings())
                ->append($this->addBeanstalkd())
                ->append($this->addRabbitMq())
                ->append($this->addRedis())
                ->append($this->addAdmin())
                ->append($this->addClasses())
                ->append($this->addPriority())
                ->append($this->addRetry());

        $node = $this->setDeprecatedNode($node, 'scalarNode', 'document_manager', 'The "%node% option is deprecated, Use "odm: { document_manager: ... }" instead.');
        $node = $this->setDeprecatedNode($node, 'scalarNode', 'entity_manager', 'The "%node% option is deprecated, Use "orm: { entity_manager: ... }" instead.');
        $node = $this->setDeprecatedNode($node, 'scalarNode', 'default_manager', 'The "%node% option is deprecated, Use "manager: { job: ... }" instead.');
        $node = $this->setDeprecatedNode($node, 'scalarNode', 'run_manager', 'The "%node% option is deprecated, Use "manager: { run: ... }" instead.');
        $node = $this->setDeprecatedNode($node, 'scalarNode', 'job_timing_manager', 'The "%node% option is deprecated, Use "manager: { job_timing: ... }" instead.');
        $node = $this->setDeprecatedNode($node, 'booleanNode', 'record_timings', 'The "%node% option is deprecated, Use "timings: { record: ... }" instead.');
        $node = $this->setDeprecatedNode($node, 'floatNode', 'record_timings_timezone_offset', 'The "%node% option is deprecated, Use "record: { timezone_offset: ... }" instead.');
        $node = $this->setDeprecatedNode($node, 'scalarNode', 'class_job', 'The "%node% option is deprecated, Use "class: { job: ... }" instead.');
        $node = $this->setDeprecatedNode($node, 'scalarNode', 'class_job_archive', 'The "%node% option is deprecated, Use "class: { job_archive: ... }" instead.');
        $node = $this->setDeprecatedNode($node, 'scalarNode', 'class_run', 'The "%node% option is deprecated, Use "class: { run: ... }" instead.');
        $node = $this->setDeprecatedNode($node, 'scalarNode', 'class_run_archive', 'The "%node% option is deprecated, Use "class: { run_archive: ... }" instead.');
        $node = $this->setDeprecatedNode($node, 'scalarNode', 'class_job_timing', 'The "%node% option is deprecated, Use "class: { job_timing: ... }" instead.');
        $node = $this->setDeprecatedNode($node, 'scalarNode', 'priority_max', 'The "%node% option is deprecated, Use "priority: { max: ... }" instead.');
        $node = $this->setDeprecatedNode($node, 'scalarNode', 'priority_direction', 'The "%node% option is deprecated, Use "priority: { direction: ... }" instead.');
        $node->end();

        return $treeBuilder;
    }

    public function setDeprecatedNode($node, $type, $name, $deprecatedMessage)
    {
        $node = $node->$type($name);

        if (Kernel::VERSION_ID >= 30400) {
            $node = $node->setDeprecated($deprecatedMessage);
        }

        return $node->end();
    }

    protected function addTimings()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('timings');
        $rootNode
            ->addDefaultsIfNotSet()
            ->children()
                ->booleanNode('record')
                    ->info('Set this to true to record timings (used on the Trends page)')
                    ->defaultFalse()
                ->end()
                ->floatNode('timezone_offset')
                    ->defaultValue(0)
                    ->info('Set this some integer offset from GMT in case your database is not storing things in the proper timezone and the dates look off on the Trends page')
                    ->max(24)
                    ->min(-24)
                ->end()
            ->end();

        return $rootNode;
    }

    protected function addOrm()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('orm');
        $rootNode
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('entity_manager')
                    ->info('This only needs to be set if orm is used for any of the managers, and you do not want to use the default entity manager')
                    ->defaultValue('default')
                    ->cannotBeEmpty()
                ->end()
            ->end();

        return $rootNode;
    }

    protected function addOdm()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('odm');
        $rootNode
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('document_manager')
                    ->info('This only needs to be set if odm is used for any of the managers, and you do not want to use the default document manager')
                    ->defaultValue('default')
                    ->cannotBeEmpty()
                ->end()
            ->end();

        return $rootNode;
    }

    protected function addManager()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('manager');
        $rootNode
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('job')
                    ->defaultValue('odm')
                    ->info('This can be [odm|orm|beanstalkd|rabbit_mq|redis|(your-own-custom-one)]')
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('run')->end()
                ->scalarNode('job_timing')->end()
            ->end();

        return $rootNode;
    }

    protected function addBeanstalkd()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('beanstalkd');
        $rootNode
            ->children()
                ->scalarNode('host')->end()
                ->scalarNode('tube')->end()
            ->end();

        return $rootNode;
    }

    protected function addRetry()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('retry');
        $rootNode
            ->addDefaultsIfNotSet()
            ->children()
                ->arrayNode('max')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('retries')
                            ->info('This the maximum total number of retries of any type.')
                            ->defaultValue(3)
                        ->end()
                        ->integerNode('failures')
                            ->info('This the maximum total number of failures before a job is marked as hitting the maximum failures.')
                            ->defaultValue(1)
                        ->end()
                        ->integerNode('exceptions')
                            ->info('This the maximum total number of exceptions before a job is marked as hitting the maximum exceptions.')
                            ->defaultValue(2)
                        ->end()
                        ->integerNode('stalls')
                            ->info('This the maximum total number of exceptions before a job is marked as hitting the maximum exceptions.')
                            ->defaultValue(2)
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('auto')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('failure')
                            ->info('Instantly re-queue the job on failure.')
                            ->defaultTrue()
                        ->end()
                        ->booleanNode('exception')
                            ->info('Instantly re-queue the job on exception.')
                            ->defaultFalse()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $rootNode;
    }

    protected function addPriority()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('priority');
        $rootNode
            ->addDefaultsIfNotSet()
            ->children()
                ->integerNode('max')
                    ->defaultValue(255)
                    ->info('Maximum priority value.')
                    ->min(1)
                ->end()
                ->enumNode('direction')
                    ->values([PriorityJobManager::PRIORITY_ASC, PriorityJobManager::PRIORITY_DESC])
                    ->info('Whether 1 is high priority or low prioirty.  '.PriorityJobManager::PRIORITY_ASC.' means 1 is low, '.PriorityJobManager::PRIORITY_DESC.' means 1 is high.')
                    ->defaultValue(PriorityJobManager::PRIORITY_DESC)
                ->end()
            ->end();

        return $rootNode;
    }

    protected function addClasses()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('class');
        $rootNode
            ->children()
                ->scalarNode('job')
                    ->info('If you want to override the Job class, put the class name here.')->end()
                ->scalarNode('job_archive')
                    ->info('If you want to override the JobArchive class, put the class name here.')->end()
                ->scalarNode('job_timing')
                    ->info('If you want to override the JobTiming class, put the class name here.')->end()
                ->scalarNode('run')
                    ->info('If you want to override the Run class, put the class name here.')->end()
                ->scalarNode('run_archive')
                    ->info('If you want to override the RunArchive class, put the class name here.')->end()
            ->end();

        return $rootNode;
    }

    protected function addAdmin()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('admin');
        $rootNode
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('chartjs')
                    ->defaultValue('https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.7.1/Chart.bundle.min.js')
                    ->info('This can be changed to say a locally hosted path or url.')->end()
                ->end()
            ->end();

        return $rootNode;
    }

    protected function addSncRedis()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('snc_redis');
        $rootNode
            ->children()
                ->enumNode('type')
                    ->values(['predis', 'phpredis'])
                    ->defaultNull()->end()
                ->scalarNode('alias')
                    ->defaultNull()->end()
            ->end()
            ->validate()->ifTrue(function ($node) {
                if (isset($node['type']) && !isset($node['alias'])) {
                    return true;
                }
                if (isset($node['alias']) && !isset($node['type'])) {
                    return true;
                }

                return false;
            })->thenInvalid('if alias or type is set, then both must be set')->end();

        return $rootNode;
    }

    protected function addPredis()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('predis');
        $rootNode
            ->children()
                ->scalarNode('dsn')->defaultNull()->end()
                ->append($this->addPredisArgs())
            ->end()
            ->validate()->ifTrue(function ($node) {
                if (isset($node['dsn']) && (isset($node['connection_parameters']['host']) || isset($node['connection_parameters']['port']))) {
                    return true;
                }

                return false;
            })->thenInvalid('if dsn is set, do not use connection_parameters for predis (and vice-versa)')->end();

        return $rootNode;
    }

    protected function checkPredis(array $node)
    {
        if ((isset($node['predis']['dsn']) || isset($node['predis']['connection_parameters']['host'])) &&
            (isset($node['snc_redis']['type']) || isset($node['phpredis']['host']))) {
            return true;
        }

        return false;
    }

    protected function checkSncPhpRedis(array $node)
    {
        if (isset($node['snc_redis']['type']) &&
            isset($node['phpredis']['host'])) {
            return true;
        }

        return false;
    }

    protected function addRedis()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('redis');
        $rootNode
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('prefix')->defaultValue('dtc_queue_')->end()
                ->append($this->addSncRedis())
                ->append($this->addPredis())
                ->append($this->addPhpRedisArgs())
            ->end()
            ->validate()->ifTrue(function ($node) {
                if ($this->checkPredis($node)) {
                    return true;
                }
                if ($this->checkSncPhpRedis($node)) {
                    return true;
                }

                return false;
            })->thenInvalid('only one of [snc_redis | predis | phpredis] should be set')->end();

        return $rootNode;
    }

    protected function addPhpRedisArgs()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('phpredis');
        $rootNode
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('host')->end()
                ->integerNode('port')->defaultValue(6379)->end()
                ->floatNode('timeout')->defaultValue(0)->end()
                ->integerNode('retry_interval')->defaultNull()->end()
                ->floatNode('read_timeout')->defaultValue(0)->end()
                ->scalarNode('auth')->end()
            ->end()
            ->validate()->ifTrue(function ($node) {
                if (!empty($node) && !isset($node['host'])) {
                    return true;
                }

                return false;
            })->thenInvalid('phpredis host should be set')->end();

        return $rootNode;
    }

    protected function addPredisArgs()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('connection_parameters');
        $rootNode
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('scheme')->defaultValue('tcp')->end()
                ->scalarNode('host')->defaultNull()->end()
                ->integerNode('port')->defaultNull()->end()
                ->scalarNode('path')->defaultNull()->end()
                ->scalarNode('database')->defaultNull()->end()
                ->scalarNode('password')->defaultNull()->end()
                ->booleanNode('async')->defaultFalse()->end()
                ->booleanNode('persistent')->defaultFalse()->end()
                ->floatNode('timeout')->defaultValue(5.0)->end()
                ->floatNode('read_write_timeout')->defaultNull()->end()
                ->scalarNode('alias')->defaultNull()->end()
                ->integerNode('weight')->defaultNull()->end()
                ->booleanNode('iterable_multibulk')->defaultFalse()->end()
                ->booleanNode('throw_errors')->defaultTrue()->end()
            ->end()
            ->validate()->ifTrue(function ($node) {
                if (isset($node['host']) && !isset($node['port'])) {
                    return true;
                }
                if (isset($node['port']) && !isset($node['host'])) {
                    return true;
                }

                return false;
            })->thenInvalid('predis connection_parameters host and port should both be set')->end();

        return $rootNode;
    }

    protected function addRabbitMqOptions()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('options');
        $rootNode
            ->children()
                ->scalarNode('insist')->end()
                ->scalarNode('login_method')->end()
                ->scalarNode('login_response')->end()
                ->scalarNode('locale')->end()
                ->floatNode('connection_timeout')->end()
                ->floatNode('read_write_timeout')->end()
                ->booleanNode('keepalive')->end()
                ->integerNode('heartbeat')->end()
            ->end();

        return $rootNode;
    }

    protected function addRabbitMqSslOptions()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('ssl_options');
        $rootNode
            ->prototype('variable')->end()
            ->validate()
                ->ifTrue(function ($node) {
                    if (!is_array($node)) {
                        return true;
                    }
                    foreach ($node as $key => $value) {
                        if (is_array($value)) {
                            if ('peer_fingerprint' !== $key) {
                                return true;
                            } else {
                                foreach ($value as $key1 => $value1) {
                                    if (!is_string($key1) || !is_string($value1)) {
                                        return true;
                                    }
                                }
                            }
                        }
                    }

                    return false;
                })
                ->thenInvalid('Must be key-value pairs')
            ->end();

        return $rootNode;
    }

    protected function addRabbitMqExchange()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('exchange_args');
        $rootNode
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('exchange')->defaultValue('dtc_queue_exchange')->end()
                ->booleanNode('type')->defaultValue('direct')->end()
                ->booleanNode('passive')->defaultFalse()->end()
                ->booleanNode('durable')->defaultTrue()->end()
                ->booleanNode('auto_delete')->defaultFalse()->end()
            ->end();

        return $rootNode;
    }

    protected function addRabbitMqArgs()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('queue_args');
        $rootNode
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('queue')->defaultValue('dtc_queue')->end()
                ->booleanNode('passive')->defaultFalse()->end()
                ->booleanNode('durable')->defaultTrue()->end()
                ->booleanNode('exclusive')->defaultFalse()->end()
                ->booleanNode('auto_delete')->defaultFalse()->end()
            ->end();

        return $rootNode;
    }

    protected function addRabbitMq()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('rabbit_mq');
        $rootNode
            ->children()
                ->scalarNode('host')->end()
                ->scalarNode('port')->end()
                ->scalarNode('user')->end()
                ->scalarNode('password')->end()
                ->scalarNode('vhost')->defaultValue('/')->end()
                ->booleanNode('ssl')->defaultFalse()->end()
                ->append($this->addRabbitMqOptions())
                ->append($this->addRabbitMqSslOptions())
                ->append($this->addRabbitMqArgs())
                ->append($this->addRabbitMqExchange())
            ->end()
            ->validate()->always(function ($node) {
                if (empty($node['ssl_options'])) {
                    unset($node['ssl_options']);
                }
                if (empty($node['options'])) {
                    unset($node['options']);
                }

                return $node;
            })->end()
            ->validate()->ifTrue(function ($node) {
                if (isset($node['ssl_options']) && !$node['ssl']) {
                    return true;
                }

                return false;
            })->thenInvalid('ssl must be true in order to set ssl_options')->end()
        ->end();

        return $rootNode;
    }
}
