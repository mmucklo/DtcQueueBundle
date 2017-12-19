<?php

namespace Dtc\QueueBundle\DependencyInjection;

use Dtc\QueueBundle\Model\PriorityJobManager;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

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

        $rootNode
            ->children()
                ->scalarNode('document_manager')
                    ->defaultValue('default')
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('entity_manager')
                    ->defaultValue('default')
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('default_manager')
                    ->defaultValue('odm')
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('run_manager')
                ->end()
                ->scalarNode('job_timing_manager')
                ->end()
                ->scalarNode('class_job')
                ->end()
                ->scalarNode('class_job_archive')
                ->end()
                ->scalarNode('class_job_timing')
                ->end()
                ->scalarNode('class_run')
                ->end()
                ->scalarNode('class_run_archive')
                ->end()
                ->booleanNode('record_timings')
                    ->defaultFalse()
                ->end()
                ->floatNode('record_timings_timezone_offset')
                    ->defaultValue(0)
                ->end()
                ->integerNode('priority_max')
                    ->defaultValue(255)
                    ->min(1)
                ->end()
                ->enumNode('priority_direction')
                    ->values([PriorityJobManager::PRIORITY_ASC, PriorityJobManager::PRIORITY_DESC])
                    ->defaultValue(PriorityJobManager::PRIORITY_DESC)
                ->end()
                ->arrayNode('beanstalkd')
                    ->children()
                        ->scalarNode('host')->end()
                        ->scalarNode('tube')->end()
                    ->end()
                ->end()
                ->append($this->addRabbitMq())
                ->append($this->addAdmin())
            ->end();

        return $treeBuilder;
    }

    protected function addAdmin()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('admin');
        $rootNode
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('chartjs')->defaultValue('https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.7.1/Chart.bundle.min.js')->end()
            ->end();

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
                ->ifTrue(function($node) {
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
            ->end()
            ->append($this->addRabbitMqOptions())
            ->append($this->addRabbitMqSslOptions())
            ->append($this->addRabbitMqArgs())
            ->append($this->addRabbitMqExchange())
            ->validate()->always(function($node) {
                if (empty($node['ssl_options'])) {
                    unset($node['ssl_options']);
                }
                if (empty($node['options'])) {
                    unset($node['options']);
                }

                return $node;
            })->end()
            ->validate()->ifTrue(function($node) {
                if (isset($node['ssl_options']) && !$node['ssl']) {
                    return true;
                }

                return false;
            })->thenInvalid('ssl must be true in order to set ssl_options')->end()
            ->end();

        return $rootNode;
    }
}
