<?php

namespace Dtc\QueueBundle\Tests\Controller;

use Doctrine\Common\Annotations\AnnotationReader;
use Dtc\GridBundle\Grid\Renderer\RendererFactory;
use Dtc\GridBundle\Grid\Source\DocumentGridSource;
use Dtc\GridBundle\Grid\Source\EntityGridSource;
use Dtc\GridBundle\Manager\GridSourceManager;
use Dtc\QueueBundle\EventDispatcher\EventDispatcher;
use Dtc\QueueBundle\Manager\WorkerManager;
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
        $runManager = JobManagerTest::$runManager;

        return $this->getContainer($jobManager, $runManager, $jobTimingManager, LiveJobsGridSource::class, EntityGridSource::class);
    }

    public function runJsCssTest($response)
    {
        static::assertArrayHasKey('css', $response);
        static::assertArrayHasKey('js', $response);
    }

    protected function getContainerOdm()
    {
        \Dtc\QueueBundle\Tests\ODM\JobManagerTest::setUpBeforeClass();
        $jobManager = \Dtc\QueueBundle\Tests\ODM\JobManagerTest::$jobManager;
        $jobTimingManager = \Dtc\QueueBundle\Tests\ODM\JobManagerTest::$jobTimingManager;
        $runManager = \Dtc\QueueBundle\Tests\ODM\JobManagerTest::$runManager;

        return $this->getContainer($jobManager, $runManager, $jobTimingManager, \Dtc\QueueBundle\ODM\LiveJobsGridSource::class, DocumentGridSource::class);
    }

    protected function getContainer($jobManager, $runManager, $jobTimingManager, $liveJobsGridSourceClass, $gridSourceClass)
    {
        $container = new Container();
        $container->setParameter('dtc_grid.theme.css', []);
        $container->setParameter('dtc_grid.theme.js', []);
        $container->setParameter('dtc_grid.jquery', ['url' => 'https://something']);
        $container->setParameter('dtc_queue.class.job', $jobManager->getJobClass());
        $container->setParameter('dtc_queue.class.job_archive', $jobManager->getJobArchiveClass());
        $container->setParameter('dtc_queue.class.run', $runManager->getRunClass());
        $container->setParameter('dtc_queue.class.run_archive', $runManager->getRunArchiveClass());
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
        $templates = ['@DtcQueue/Queue/grid.html.twig' => file_get_contents(__DIR__.'/../../Resources/views/Queue/grid.html.twig'),
                        'DtcGridBundle:Page:datatables.html.twig' => file_get_contents(__DIR__.'/../../vendor/mmucklo/grid-bundle/Resources/views/Grid/datatables.html.twig'), ];
        $twigEngine = new TwigEngine(new Environment(new \Twig_Loader_Array($templates)), new TemplateNameParser(), new FileLocator(__DIR__));
        $rendererFactory->setTwigEngine($twigEngine);

        $container->set('twig', $twigEngine);

        $container->set('dtc_grid.renderer.factory', $rendererFactory);
        $liveJobsGridSource = new $liveJobsGridSourceClass($jobManager);
        $container->set('dtc_queue.grid_source.jobs_waiting.orm', $liveJobsGridSource);
        $liveJobsGridSource = new $liveJobsGridSourceClass($jobManager);
        $liveJobsGridSource->setRunning(true);
        $container->set('dtc_queue.grid_source.jobs_running.orm', $liveJobsGridSource);
        $container->set('dtc_queue.job_manager', $jobManager);
        $gridSourceManager = new GridSourceManager(new AnnotationReader(), __DIR__);
        $container->set('dtc_grid.manager.source', $gridSourceManager);
        $gridSourceJob = new $gridSourceClass($jobManager->getObjectManager(), $jobManager->getJobClass());
        $gridSourceJob->autodiscoverColumns();
        $gridSourceManager->add($jobManager->getJobClass(), $gridSourceJob);
        $gridSourceJobArchive = new $gridSourceClass($jobManager->getObjectManager(), $jobManager->getJobArchiveClass());
        $gridSourceJobArchive->autodiscoverColumns();
        $gridSourceManager->add($jobManager->getJobArchiveClass(), $gridSourceJobArchive);
        $gridSourceRun = new $gridSourceClass($runManager->getObjectManager(), $runManager->getRunClass());
        $gridSourceRun->autodiscoverColumns();
        $gridSourceManager->add($runManager->getRunClass(), $gridSourceRun);
        $gridSourceRunArchive = new $gridSourceClass($runManager->getObjectManager(), $runManager->getRunArchiveClass());
        $gridSourceRunArchive->autodiscoverColumns();
        $gridSourceManager->add($runManager->getRunArchiveClass(), $gridSourceRunArchive);

        return $container;
    }
}
