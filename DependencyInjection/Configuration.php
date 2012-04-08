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
                    ->isRequired()
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('class')
                    ->defaultValue('Dtc\QueueBundle\Documents\Job')
                    ->cannotBeEmpty()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
