<?php

namespace Dtc\QueueBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;

trait RabbitMQConfiguration
{
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

                return $this->validateNode($node);
            })
            ->thenInvalid('Must be key-value pairs')
            ->end();

        return $rootNode;
    }

    private function validateNode(array $node)
    {
        foreach ($node as $key => $value) {
            if (is_array($value)) {
                if ($this->validateArray($key, $value)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function validateArray($key, $value)
    {
        if ('peer_fingerprint' !== $key) {
            return true;
        }
        foreach ($value as $key1 => $value1) {
            if (!is_string($key1) || !is_string($value1)) {
                return true;
            }
        }

        return false;
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
