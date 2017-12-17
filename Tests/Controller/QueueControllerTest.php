<?php

namespace Dtc\QueueBundle\Tests\Controller;

use Doctrine\Common\Annotations\AnnotationReader;
use Dtc\GridBundle\Grid\Renderer\RendererFactory;
use Dtc\GridBundle\Grid\Source\EntityGridSource;
use Dtc\GridBundle\Manager\GridSourceManager;
use Dtc\QueueBundle\Controller\QueueController;
use Dtc\QueueBundle\Entity\Job;
use Dtc\QueueBundle\Entity\JobArchive;
use Dtc\QueueBundle\Entity\Run;
use Dtc\QueueBundle\Entity\RunArchive;
use Dtc\QueueBundle\EventDispatcher\EventDispatcher;
use Dtc\QueueBundle\Model\WorkerManager;
use Dtc\QueueBundle\ORM\LiveJobsGridSource;
use Dtc\QueueBundle\Tests\ORM\JobManagerTest;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\TwigBundle\TwigEngine;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Loader\YamlFileLoader;
use Symfony\Component\Routing\Router;

class QueueControllerTest extends TestCase
{
    public static function setUpBeforeClass()
    {
        JobManagerTest::setUpBeforeClass();
    }

    public function testStatusAction()
    {
        $container = $this->getContainer();
        $queueController = new QueueController();
        $queueController->setContainer($container);
        $response = $queueController->statusAction();
        self::assertArrayHasKey('status', $response);
        self::assertArrayHasKey('css', $response);
        self::assertArrayHasKey('js', $response);
    }

    protected function getContainer()
    {
        $container = new Container();
        $container->setParameter('dtc_grid.theme.css', []);
        $container->setParameter('dtc_grid.theme.js', []);
        $container->setParameter('dtc_grid.jquery', []);
        $container->setParameter('dtc_queue.class_job', Job::class);
        $container->setParameter('dtc_queue.class_job_archive', JobArchive::class);
        $container->setParameter('dtc_queue.class_run', Run::class);
        $container->setParameter('dtc_queue.class_run_archive', RunArchive::class);
        $container->setParameter('dtc_queue.admin.chartjs', '');
        $container->setParameter('dtc_queue.default_manager', 'orm');
        $container->set('dtc_queue.job_manager', JobManagerTest::$jobManager);
        $container->set('dtc_queue.worker_manager', new WorkerManager(JobManagerTest::$jobManager, new EventDispatcher()));
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
        $mockBuilder = self::getMockBuilder(TwigEngine::class);
        $twigEngineMock = $mockBuilder->getMock();
        $rendererFactory->setTwigEngine($twigEngineMock);
        $container->set('dtc_grid.renderer.factory', $rendererFactory);
        $liveJobsGridSource = new LiveJobsGridSource(JobManagerTest::$jobManager);
        $container->set('dtc_queue.grid_source.jobs_waiting.orm', $liveJobsGridSource);
        $liveJobsGridSource = new LiveJobsGridSource(JobManagerTest::$jobManager);
        $liveJobsGridSource->setRunning(true);
        $container->set('dtc_queue.grid_source.jobs_running.orm', $liveJobsGridSource);
        $container->set('dtc_queue.job_manager', JobManagerTest::$jobManager);
        $gridSourceManager = new GridSourceManager(new AnnotationReader(), __DIR__);
        $container->set('dtc_grid.manager.source', $gridSourceManager);
        $gridSourceManager->add(Job::class, new EntityGridSource(JobManagerTest::$jobManager->getObjectManager(), Job::class));

        return $container;
    }

    public function testJobsAction()
    {
        $container = $this->getContainer();
        $queueController = new QueueController();
        $queueController->setContainer($container);
        $response = $queueController->jobsAction();
        self::assertArrayHasKey('css', $response);
        self::assertArrayHasKey('js', $response);
    }

    public function testJobsRunningAction()
    {
        $container = $this->getContainer();
        $queueController = new QueueController();
        $queueController->setContainer($container);
        $response = $queueController->runningJobsAction();
        self::assertArrayHasKey('css', $response);
        self::assertArrayHasKey('js', $response);
    }

    public function testWorkersAction()
    {
        $container = $this->getContainer();
        $queueController = new QueueController();
        $queueController->setContainer($container);
        $response = $queueController->workersAction();
        self::assertArrayHasKey('workers', $response);
        self::assertArrayHasKey('css', $response);
        self::assertArrayHasKey('js', $response);
    }

    public function testArchiveJobsAction()
    {
        $container = $this->getContainer();
        $queueController = new QueueController();
        $queueController->setContainer($container);
        $response = $queueController->archiveAction(new Request());
        self::assertNotNull($response);
        self::assertTrue($response instanceof StreamedResponse);
    }
}
