<?php

namespace Dtc\QueueBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;

trait RedisConfiguration
{
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
}
