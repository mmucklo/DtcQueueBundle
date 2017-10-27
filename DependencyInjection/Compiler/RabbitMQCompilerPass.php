<?php

namespace Dtc\QueueBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class RabbitMQCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
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
     * @param string           $class
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
}
