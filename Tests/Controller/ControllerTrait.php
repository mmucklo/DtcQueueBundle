<?php

namespace Dtc\QueueBundle\Tests\Controller;

use Doctrine\Common\Annotations\AnnotationReader;
use Dtc\GridBundle\Grid\Renderer\RendererFactory;
use Dtc\GridBundle\Grid\Source\DocumentGridSource;
use Dtc\GridBundle\Grid\Source\EntityGridSource;
use Dtc\GridBundle\Manager\GridSourceManager;
use Dtc\QueueBundle\Entity\Job;
use Dtc\QueueBundle\Entity\JobArchive;
use Dtc\QueueBundle\Entity\Run;
use Dtc\QueueBundle\Entity\RunArchive;
use Dtc\QueueBundle\EventDispatcher\EventDispatcher;
use Dtc\QueueBundle\Model\WorkerManager;
use Dtc\QueueBundle\ORM\LiveJobsGridSource;
use Dtc\QueueBundle\Tests\ORM\JobManagerTest;
use Symfony\Bundle\TwigBundle\TwigEngine;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Routing\Loader\YamlFileLoader;
use Symfony\Component\Routing\Router;
use Symfony\Component\Templating\TemplateNameParser;
use Twig\Environment;

trait ControllerTrait
{
    protected function getContainerOrm()
    {
        JobManagerTest::setUpBeforeClass();
        $jobManager = JobManagerTest::$jobManager;
        $jobTimingManager = JobManagerTest::$jobTimingManager;

        return $this->getContainer($jobManager, $jobTimingManager, LiveJobsGridSource::class, EntityGridSource::class);
    }

    protected function getContainerOdm()
    {
        \Dtc\QueueBundle\Tests\ODM\JobManagerTest::setUpBeforeClass();
        $jobManager = JobManagerTest::$jobManager;
        $jobTimingManager = JobManagerTest::$jobTimingManager;

        return $this->getContainer($jobManager, $jobTimingManager, \Dtc\QueueBundle\ODM\LiveJobsGridSource::class, DocumentGridSource::class);
    }

    protected function getContainer($jobManager, $jobTimingManager, $liveJobsGridSourceClass, $gridSourceClass)
    {
        $container = new Container();
        $container->setParameter('dtc_grid.theme.css', []);
        $container->setParameter('dtc_grid.theme.js', []);
        $container->setParameter('dtc_grid.jquery', ['url' => 'https://something']);
        $container->setParameter('dtc_queue.class_job', Job::class);
        $container->setParameter('dtc_queue.class_job_archive', JobArchive::class);
        $container->setParameter('dtc_queue.class_run', Run::class);
        $container->setParameter('dtc_queue.class_run_archive', RunArchive::class);
        $container->setParameter('dtc_queue.admin.chartjs', '');
        $container->setParameter('dtc_queue.default_manager', 'orm');
        $container->setParameter('dtc_queue.record_timings', true);
        $container->setParameter('dtc_queue.record_timings_timezone_offset', 0);
        $container->set('dtc_queue.job_manager', $jobManager);
        $container->set('dtc_queue.job_timing_manager', $jobTimingManager);
        $container->set('dtc_queue.worker_manager', new WorkerManager($jobManager, new EventDispatcher()));
        $rendererFactory = new RendererFactory(
            new Router(new YamlFileLoader(new FileLocator(__DIR__)), 'test.yml'),
            [
                'theme.css' => [],
                'theme.js' => [],
                'page_div_style' => 'somestyle',
                'jquery' => [],
                'purl' => [],
                'datatables.css' => [],
                'datatables.js' => [],
                'jq_grid.css' => [],
                'jq_grid.js' => [], ]
        );
        $twigEngine = new TwigEngine(new Environment(new \Twig_Loader_Array()), new TemplateNameParser(), new FileLocator(__DIR__));
        $rendererFactory->setTwigEngine($twigEngine);
        $container->set('dtc_grid.renderer.factory', $rendererFactory);
        $liveJobsGridSource = new $liveJobsGridSourceClass($jobManager);
        $container->set('dtc_queue.grid_source.jobs_waiting.orm', $liveJobsGridSource);
        $liveJobsGridSource = new $liveJobsGridSourceClass($jobManager);
        $liveJobsGridSource->setRunning(true);
        $container->set('dtc_queue.grid_source.jobs_running.orm', $liveJobsGridSource);
        $container->set('dtc_queue.job_manager', $jobManager);
        $gridSourceManager = new GridSourceManager(new AnnotationReader(), __DIR__);
        $container->set('dtc_grid.manager.source', $gridSourceManager);
        $gridSourceManager->add(Job::class, new $gridSourceClass($jobManager->getObjectManager(), $jobManager->getJobClass()));

        return $container;
    }
}
