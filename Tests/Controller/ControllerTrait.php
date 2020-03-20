<?php

namespace Dtc\QueueBundle\Tests\Controller;

use Doctrine\Common\Annotations\AnnotationReader;
use Dtc\GridBundle\Grid\Renderer\RendererFactory;
use Dtc\GridBundle\Grid\Source\ColumnSource;
use Dtc\GridBundle\Grid\Source\DocumentGridSource;
use Dtc\GridBundle\Grid\Source\EntityGridSource;
use Dtc\GridBundle\Manager\GridSourceManager;
use Dtc\GridBundle\Util\ColumnUtil;
use Dtc\QueueBundle\EventDispatcher\EventDispatcher;
use Dtc\QueueBundle\Manager\WorkerManager;
use Dtc\QueueBundle\ORM\LiveJobsGridSource;
use Dtc\QueueBundle\Tests\ORM\JobManagerTest;
use Symfony\Bridge\Twig\Extension\RoutingExtension;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Bundle\TwigBundle\TwigEngine;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Loader\GlobFileLoader;
use Symfony\Component\Routing\Loader\YamlFileLoader;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouteCollectionBuilder;
use Symfony\Component\Routing\Router;
use Symfony\Component\Templating\TemplateNameParser;
use Symfony\Component\Translation\Translator;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

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
        static::assertContains('css', $response->getContent());
        static::assertContains('js', $response->getContent());
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
        $container->setParameter('dtc_queue.manager.job', 'orm');
        $container->setParameter('dtc_queue.timings.record', true);
        $container->setParameter('dtc_queue.timings.timezone_offset', 0);
        $container->setParameter('dtc_grid.theme.css', ['https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css', 'https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css']);
        $container->setParameter('dtc_grid.theme.js', ['https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js']);
        $container->set('dtc_queue.manager.job', $jobManager);
        $container->set('dtc_queue.manager.job_timing', $jobTimingManager);
        $container->set('dtc_queue.manager.worker', new WorkerManager($jobManager, new EventDispatcher()));
        $rendererFactory = new RendererFactory(
            new Router(new YamlFileLoader(new FileLocator(__DIR__)), 'test.yml'),
            new Translator('en_US'),
            [
                'theme.css' => [],
                'theme.js' => [],
                'page_div_style' => 'somestyle',
                'jquery' => [],
                'purl' => [],
                'table.options' => [],
                'datatables.css' => [],
                'datatables.class' => '',
                'datatables.js' => [],
                'datatables.options' => [],
                'jq_grid.css' => [],
                'jq_grid.js' => [],
                'jq_grid.options' => [], ],
            []
        );
        $templates = ['@DtcQueue/Queue/status.html.twig' => file_get_contents(__DIR__.'/../../Resources/views/Queue/status.html.twig'),
                      '@DtcQueue/Queue/jobs.html.twig' => file_get_contents(__DIR__.'/../../Resources/views/Queue/jobs.html.twig'),
                      '@DtcQueue/Queue/nav.html.twig' => file_get_contents(__DIR__.'/../../Resources/views/Queue/nav.html.twig'),
                      '@DtcQueue/Queue/macros.html.twig' => file_get_contents(__DIR__.'/../../Resources/views/Queue/macros.html.twig'),
                      '@DtcQueue/Queue/workers.html.twig' => file_get_contents(__DIR__.'/../../Resources/views/Queue/workers.html.twig'),
                      '@DtcQueue/Queue/trends.html.twig' => file_get_contents(__DIR__.'/../../Resources/views/Queue/trends.html.twig'),
                      '@DtcQueue/layout.html.twig' => file_get_contents(__DIR__.'/../../Resources/views/layout.html.twig'),
                      '@DtcQueue/Queue/jobs_running.html.twig' => file_get_contents(__DIR__.'/../../Resources/views/Queue/jobs_running.html.twig'),
                      '@DtcQueue/Queue/grid.html.twig' => file_get_contents(__DIR__.'/../../Resources/views/Queue/grid.html.twig'),
                      '@DtcGrid/Page/datatables.html.twig' => file_get_contents(__DIR__.'/../../vendor/mmucklo/grid-bundle/Resources/views/Page/datatables.html.twig'),
                      '@DtcGrid/Grid/datatables.html.twig' => file_get_contents(__DIR__.'/../../vendor/mmucklo/grid-bundle/Resources/views/Grid/datatables.html.twig'),
                      '@DtcGrid/layout.html.twig' => file_get_contents(__DIR__.'/../../vendor/mmucklo/grid-bundle/Resources/views/layout.html.twig'),
                      '@DtcGrid/layout_base_jquery.html.twig' => file_get_contents(__DIR__.'/../../vendor/mmucklo/grid-bundle/Resources/views/layout_base_jquery.html.twig'),
            ];
        if (class_exists('Symfony\Bundle\TwigBundle\TwigEngine') && method_exists($rendererFactory, 'setTwigEngine')) {
            $twigEngine = new TwigEngine(
                new Environment(new \Twig_Loader_Array($templates)),
                new TemplateNameParser(),
                new FileLocator(__DIR__)
            );
            $rendererFactory->setTwigEngine($twigEngine);
            $container->set('twig', $twigEngine);
        } elseif (class_exists('Twig\Environment') && method_exists($rendererFactory, 'setTwigEnvironment')) {
            $environment = new Environment(new ArrayLoader($templates));
            $translatorExtension = new TranslationExtension(new Translator('en_US'));
//            foreach ($translatorExtension->getFilters() as $filter) {
//                $environment->addFilter($filter);
//            }
            $environment->addExtension($translatorExtension);
            $routeCollectionBuilder = new RouteCollectionBuilder(new YamlFileLoader(new FileLocator(__DIR__.'/../../Resources/config')));
            $routeCollectionBuilder->import('routing.yml');
            $urlGenerator = new UrlGenerator($routeCollectionBuilder->build(), new RequestContext());
            $routingExtension = new RoutingExtension($urlGenerator);
            $environment->addExtension($routingExtension);
            $rendererFactory->setTwigEnvironment($environment);
            $container->set('twig', $environment);
        }

        $container->set('dtc_grid.renderer.factory', $rendererFactory);
        $liveJobsGridSource = new $liveJobsGridSourceClass($jobManager);
        $container->set('dtc_queue.grid_source.jobs_waiting.orm', $liveJobsGridSource);
        $liveJobsGridSource = new $liveJobsGridSourceClass($jobManager);
        $liveJobsGridSource->setRunning(true);
        $container->set('dtc_queue.grid_source.jobs_running.orm', $liveJobsGridSource);
        $container->set('dtc_queue.manager.job', $jobManager);
        $gridSourceManager = new GridSourceManager(new ColumnSource(__DIR__, true));
        $gridSourceManager->setReader(new AnnotationReader());

        $columnSource = new \Dtc\GridBundle\Grid\Source\ColumnSource(__DIR__, true);
        $gridSourceManager = new GridSourceManager($columnSource);
        $gridSourceManager->setReader(new AnnotationReader());
        $container->set('dtc_grid.manager.source', $gridSourceManager);

        $gridSourceJob = new $gridSourceClass($jobManager->getObjectManager(), $jobManager->getJobClass());
        ColumnUtil::cacheClassesFromFile(__DIR__, __DIR__.\DIRECTORY_SEPARATOR.'..'.\DIRECTORY_SEPARATOR.'..'.\DIRECTORY_SEPARATOR.'Resources'.\DIRECTORY_SEPARATOR.'config'.\DIRECTORY_SEPARATOR.'dtc_grid.yaml');
        $columnSourceInfo = $columnSource->getColumnSourceInfo($jobManager->getObjectManager(), $jobManager->getJobClass(), false);
        $gridSourceJob->setIdColumn($columnSourceInfo->idColumn);
        $gridSourceJob->setColumns($columnSourceInfo->columns);
        $gridSourceJob->setId($jobManager->getJobClass());
        $gridSourceJob->setDefaultSort($columnSourceInfo->sort);
        $gridSourceManager->add($jobManager->getJobClass(), $gridSourceJob);

        $gridSourceJobArchive = new $gridSourceClass($jobManager->getObjectManager(), $jobManager->getJobArchiveClass());
        $columnSourceInfo = $columnSource->getColumnSourceInfo($jobManager->getObjectManager(), $jobManager->getJobArchiveClass(), false);
        $gridSourceJobArchive->setIdColumn($columnSourceInfo->idColumn);
        $gridSourceJobArchive->setColumns($columnSourceInfo->columns);
        $gridSourceJobArchive->setId($jobManager->getJobArchiveClass());
        $gridSourceJobArchive->setDefaultSort($columnSourceInfo->sort);
        $gridSourceManager->add($jobManager->getJobArchiveClass(), $gridSourceJobArchive);

        $gridSourceRun = new $gridSourceClass($runManager->getObjectManager(), $runManager->getRunClass());
        $columnSourceInfo = $columnSource->getColumnSourceInfo($runManager->getObjectManager(), $runManager->getRunClass(), false);
        $gridSourceRun->setIdColumn($columnSourceInfo->idColumn);
        $gridSourceRun->setColumns($columnSourceInfo->columns);
        $gridSourceRun->setId($runManager->getRunClass());
        $gridSourceRun->setDefaultSort($columnSourceInfo->sort);
        $gridSourceManager->add($runManager->getRunClass(), $gridSourceRun);

        $gridSourceRunArchive = new $gridSourceClass($runManager->getObjectManager(), $runManager->getRunArchiveClass());
        $columnSourceInfo = $columnSource->getColumnSourceInfo($runManager->getObjectManager(), $runManager->getRunArchiveClass(), false);
        $gridSourceRunArchive->setIdColumn($columnSourceInfo->idColumn);
        $gridSourceRunArchive->setColumns($columnSourceInfo->columns);
        $gridSourceRunArchive->setId($runManager->getRunArchiveClass());
        $gridSourceRunArchive->setDefaultSort($columnSourceInfo->sort);
        $gridSourceManager->add($runManager->getRunArchiveClass(), $gridSourceRunArchive);

        return $container;
    }
}
