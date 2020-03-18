<?php

namespace Dtc\QueueBundle\DependencyInjection;

use Dtc\QueueBundle\Manager\PriorityJobManager;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\HttpKernel\Kernel;

class Configuration implements ConfigurationInterface
{
    use RabbitMQConfiguration;
    use RedisConfiguration;

    /**
     * Generates the configuration tree.
     *
     * @return TreeBuilder
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('dtc_queue');

        if (method_exists($treeBuilder, 'getRootNode')) {
            $rootNode = $treeBuilder->getRootNode();
        } else {
            // BC layer for symfony/config 4.1 and older
            $rootNode = $treeBuilder->root('dtc_queue');
        }

        $node = $rootNode
            ->children()
                ->booleanNode('locale_fix')
                    ->defaultFalse()
                    ->info('Set this to true to fix issues with ORM saving (see issue #98) in non-period decimal format locales')
                ->end()
                ->append($this->addSimpleScalar('orm', 'entity_manager', 'This only needs to be set if orm is used for any of the managers, and you do not want to use the default entity manager'))
                ->append($this->addSimpleScalar('odm', 'document_manager', 'This only needs to be set if odm is used for any of the managers, and you do not want to use the default document manager'))
                ->append($this->addManager())
                ->append($this->addTimings())
                ->append($this->addBeanstalkd())
                ->append($this->addRabbitMq())
                ->append($this->addRedis())
                ->append($this->addSimpleScalar('admin', 'chartjs', 'This can be changed to say a locally hosted path or url.', 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.7.1/Chart.bundle.min.js'))
                ->append($this->addClasses())
                ->append($this->addPriority())
                ->append($this->addRetry());
        $node = $this->setDeprecatedNode($node, 'scalarNode', 'document_manager', 'The "%node% option is deprecated, Use "odm: { document_manager: ... }" instead.');
        $node = $this->setDeprecatedNode($node, 'scalarNode', 'entity_manager', 'The "%node% option is deprecated, Use "orm: { entity_manager: ... }" instead.');
        $node = $this->setDeprecatedNode($node, 'scalarNode', 'default_manager', 'The "%node% option is deprecated, Use "manager: { job: ... }" instead.');
        $node = $this->setDeprecatedNode($node, 'scalarNode', 'run_manager', 'The "%node% option is deprecated, Use "manager: { run: ... }" instead.');
        $node = $this->setDeprecatedNode($node, 'scalarNode', 'job_timing_manager', 'The "%node% option is deprecated, Use "manager: { job_timing: ... }" instead.');
        $node = $this->setDeprecatedNode($node, 'booleanNode', 'record_timings', 'The "%node% option is deprecated, Use "timings: { record: ... }" instead.');
        $node = $this->setDeprecatedNode($node, 'floatNode', 'record_timings_timezone_offset', 'The "%node% option is deprecated, Use "record: { timezone_offset: ... }" instead.');
        $node = $this->setDeprecatedNode($node, 'scalarNode', 'class_job', 'The "%node% option is deprecated, Use "class: { job: ... }" instead.');
        $node = $this->setDeprecatedNode($node, 'scalarNode', 'class_job_archive', 'The "%node% option is deprecated, Use "class: { job_archive: ... }" instead.');
        $node = $this->setDeprecatedNode($node, 'scalarNode', 'class_run', 'The "%node% option is deprecated, Use "class: { run: ... }" instead.');
        $node = $this->setDeprecatedNode($node, 'scalarNode', 'class_run_archive', 'The "%node% option is deprecated, Use "class: { run_archive: ... }" instead.');
        $node = $this->setDeprecatedNode($node, 'scalarNode', 'class_job_timing', 'The "%node% option is deprecated, Use "class: { job_timing: ... }" instead.');
        $node = $this->setDeprecatedNode($node, 'scalarNode', 'priority_max', 'The "%node% option is deprecated, Use "priority: { max: ... }" instead.');
        $node = $this->setDeprecatedNode($node, 'scalarNode', 'priority_direction', 'The "%node% option is deprecated, Use "priority: { direction: ... }" instead.');
        $node->end();

        return $treeBuilder;
    }

    public function setDeprecatedNode($node, $type, $name, $deprecatedMessage)
    {
        $node = $node->$type($name);

        if (Kernel::VERSION_ID >= 30400) {
            $node = $node->setDeprecated($deprecatedMessage);
        }

        return $node->end();
    }

    protected function addTimings()
    {
        $treeBuilder = new TreeBuilder('timings');

        if (method_exists($treeBuilder, 'getRootNode')) {
            $rootNode = $treeBuilder->getRootNode();
        } else {
            // BC layer for symfony/config 4.1 and older
            $rootNode = $treeBuilder->root('timings');
        }

        $rootNode
            ->addDefaultsIfNotSet()
            ->children()
                ->booleanNode('record')
                    ->info('Set this to true to record timings (used on the Trends page)')
                    ->defaultFalse()
                ->end()
                ->floatNode('timezone_offset')
                    ->defaultValue(0)
                    ->info('Set this some integer offset from GMT in case your database is not storing things in the proper timezone and the dates look off on the Trends page')
                    ->max(24)
                    ->min(-24)
                ->end()
            ->end();

        return $rootNode;
    }

    protected function addSimpleScalar($rootName, $nodeName, $info, $defaultValue = 'default')
    {
        $treeBuilder = new TreeBuilder($rootName);

        if (method_exists($treeBuilder, 'getRootNode')) {
            $rootNode = $treeBuilder->getRootNode();
        } else {
            // BC layer for symfony/config 4.1 and older
            $rootNode = $treeBuilder->root($rootName);
        }

        $rootNode
            ->addDefaultsIfNotSet()
            ->children()
            ->scalarNode($nodeName)
            ->info($info)
            ->defaultValue($defaultValue)
            ->cannotBeEmpty()
            ->end()
            ->end();

        return $rootNode;
    }

    protected function addManager()
    {
        $treeBuilder = new TreeBuilder('manager');

        if (method_exists($treeBuilder, 'getRootNode')) {
            $rootNode = $treeBuilder->getRootNode();
        } else {
            // BC layer for symfony/config 4.1 and older
            $rootNode = $treeBuilder->root('manager');
        }
        $rootNode
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('job')
                    ->defaultValue('odm')
                    ->info('This can be [odm|orm|beanstalkd|rabbit_mq|redis|(your-own-custom-one)]')
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('run')->end()
                ->scalarNode('job_timing')->end()
            ->end();

        return $rootNode;
    }

    protected function addBeanstalkd()
    {
        $treeBuilder = new TreeBuilder('beanstalkd');

        if (method_exists($treeBuilder, 'getRootNode')) {
            $rootNode = $treeBuilder->getRootNode();
        } else {
            // BC layer for symfony/config 4.1 and older
            $rootNode = $treeBuilder->root('beanstalkd');
        }

        $rootNode
            ->children()
                ->scalarNode('host')->end()
                ->integerNode('port')
                    ->defaultValue(11300)
                ->end()
                ->scalarNode('tube')->end()
            ->end();

        return $rootNode;
    }

    protected function addRetry()
    {
        $treeBuilder = new TreeBuilder('retry');

        if (method_exists($treeBuilder, 'getRootNode')) {
            $rootNode = $treeBuilder->getRootNode();
        } else {
            // BC layer for symfony/config 4.1 and older
            $rootNode = $treeBuilder->root('retry');
        }

        $rootNode
            ->addDefaultsIfNotSet()
            ->children()
                ->arrayNode('max')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('retries')
                            ->info('This the maximum total number of retries of any type.')
                            ->defaultValue(3)
                        ->end()
                        ->integerNode('failures')
                            ->info('This the maximum total number of failures before a job is marked as hitting the maximum failures.')
                            ->defaultValue(1)
                        ->end()
                        ->integerNode('exceptions')
                            ->info('This the maximum total number of exceptions before a job is marked as hitting the maximum exceptions.')
                            ->defaultValue(2)
                        ->end()
                        ->integerNode('stalls')
                            ->info('This the maximum total number of exceptions before a job is marked as hitting the maximum exceptions.')
                            ->defaultValue(2)
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('auto')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('failure')
                            ->info('Instantly re-queue the job on failure.')
                            ->defaultTrue()
                        ->end()
                        ->booleanNode('exception')
                            ->info('Instantly re-queue the job on exception.')
                            ->defaultFalse()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $rootNode;
    }

    protected function addPriority()
    {
        $treeBuilder = new TreeBuilder('priority');

        if (method_exists($treeBuilder, 'getRootNode')) {
            $rootNode = $treeBuilder->getRootNode();
        } else {
            // BC layer for symfony/config 4.1 and older
            $rootNode = $treeBuilder->root('priority');
        }
        $rootNode
            ->addDefaultsIfNotSet()
            ->children()
                ->integerNode('max')
                    ->defaultValue(255)
                    ->info('Maximum priority value.')
                    ->min(1)
                ->end()
                ->enumNode('direction')
                    ->values([PriorityJobManager::PRIORITY_ASC, PriorityJobManager::PRIORITY_DESC])
                    ->info('Whether 1 is high priority or low prioirty.  '.PriorityJobManager::PRIORITY_ASC.' means 1 is low, '.PriorityJobManager::PRIORITY_DESC.' means 1 is high.')
                    ->defaultValue(PriorityJobManager::PRIORITY_DESC)
                ->end()
            ->end();

        return $rootNode;
    }

    protected function addClasses()
    {
        $treeBuilder = new TreeBuilder('class');

        if (method_exists($treeBuilder, 'getRootNode')) {
            $rootNode = $treeBuilder->getRootNode();
        } else {
            // BC layer for symfony/config 4.1 and older
            $rootNode = $treeBuilder->root('class');
        }

        $rootNode
            ->children()
                ->scalarNode('job')
                    ->info('If you want to override the Job class, put the class name here.')->end()
                ->scalarNode('job_archive')
                    ->info('If you want to override the JobArchive class, put the class name here.')->end()
                ->scalarNode('job_timing')
                    ->info('If you want to override the JobTiming class, put the class name here.')->end()
                ->scalarNode('run')
                    ->info('If you want to override the Run class, put the class name here.')->end()
                ->scalarNode('run_archive')
                    ->info('If you want to override the RunArchive class, put the class name here.')->end()
            ->end();

        return $rootNode;
    }
}
