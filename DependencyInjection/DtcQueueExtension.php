<?php
namespace Dtc\QueueBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Loader;

class DtcQueueExtension
    extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $processor = new Processor();
        $configuration = new Configuration();

        $config = $processor->processConfiguration($configuration, $configs);
        $container->setParameter('dtc_queue.document_manager', $config['document_manager']);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('queue.xml');

        $odmManager = "doctrine_mongodb.odm.{$config['document_manager']}_document_manager";

        $jobManagerDef = $container->getDefinition('dtc_queue.job_manager');
        $jobManagerDef->addArgument(new Reference($odmManager));
        $jobManagerDef->addArgument($config['class']);

        $container->setParameter('dtc_queue.job_class', $config['class']);

        // Load Grid if Dtc\GridBundle Bundle is registered
        $yamlLoader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $yamlLoader->load('grid.yml');

        $jobGridSourceDef = $container->getDefinition('dtc_queue.grid.source.job');
        $jobGridSourceDef->addArgument(new Reference($odmManager));
        $jobGridSourceDef->addArgument($config['class']);
    }

    public function getAlias()
    {
        return 'dtc_queue';
    }
}
