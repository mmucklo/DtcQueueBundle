<?php

namespace Dtc\QueueBundle\DependencyInjection;

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
                    ->defaultValue('mongodb')
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('class_job')
                ->end()
                ->scalarNode('class_job_archive')
                ->end()
                ->scalarNode('class_run')
                ->end()
                ->scalarNode('class_run_archive')
                ->end()
                ->arrayNode('beanstalkd')
                    ->children()
                        ->scalarNode('host')->end()
                        ->scalarNode('tube')->end()
                    ->end()
                ->end()
                ->arrayNode('rabbit_mq')
                    ->children()
                        ->scalarNode('host')->end()
                        ->scalarNode('port')->end()
                        ->scalarNode('user')->end()
                        ->scalarNode('password')->end()
                        ->scalarNode('vhost')->defaultValue('/')->end()
                        ->booleanNode('ssl')->defaultFalse()->end()
                        ->scalarNode('options')->end()
                        ->scalarNode('ssl_options')->end()
                        ->arrayNode('queue_args')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('queue')->defaultValue('dtc_queue')->end()
                                ->booleanNode('passive')->defaultFalse()->end()
                                ->booleanNode('durable')->defaultTrue()->end()
                                ->booleanNode('exclusive')->defaultFalse()->end()
                                ->booleanNode('auto_delete')->defaultFalse()->end()
                                ->integerNode('max_priority')->defaultValue(255)->end()
                            ->end()
                        ->end()
                        ->arrayNode('exchange_args')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('exchange')->defaultValue('dtc_queue_exchange')->end()
                                ->booleanNode('type')->defaultValue('direct')->end()
                                ->booleanNode('passive')->defaultFalse()->end()
                                ->booleanNode('durable')->defaultTrue()->end()
                                ->booleanNode('auto_delete')->defaultFalse()->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
