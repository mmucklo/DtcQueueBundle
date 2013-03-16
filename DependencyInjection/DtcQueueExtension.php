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
    	$documentName = 'Dtc\QueueBundle\Documents\Job';
        $processor = new Processor();
        $configuration = new Configuration();

        $config = $processor->processConfiguration($configuration, $configs);
        $container->setParameter('dtc_queue.document_manager', $config['document_manager']);
        $container->setParameter('dtc_queue.job_class', $documentName);

        // Load Grid if Dtc\GridBundle Bundle is registered
        $yamlLoader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));

        $yamlLoader->load('queue.yml');
        $yamlLoader->load('grid.yml');

        $odmManager = "doctrine_mongodb.odm.{$config['document_manager']}_document_manager";
        $container->setAlias('dtc_queue.document_manager', $odmManager);
    }

    public function getAlias()
    {
        return 'dtc_queue';
    }
}
