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
                ->scalarNode('default')
                    ->defaultValue('mongodb')
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('class')
                ->end()
                ->arrayNode('beanstalkd')
                    ->children()
                       ->scalarNode('host')->end()
                       ->scalarNode('tube')->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
