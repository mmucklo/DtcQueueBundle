<?php
namespace Dtc\QueueBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Reference;

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

        $jobManagerDev = $container->getDefinition('dtc_queue.job_manager');
        $jobManagerDev->addArgument(new Reference($odmManager));
        $jobManagerDev->addArgument($config['class']);

        $container->setParameter('dtc_queue.job_class', $config['class']);
    }

    public function getAlias()
    {
        return 'dtc_queue';
    }
}
