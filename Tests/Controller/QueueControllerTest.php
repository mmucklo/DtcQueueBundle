<?php

namespace Dtc\QueueBundle\Tests\Controller;

use Dtc\QueueBundle\Controller\QueueController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QueueControllerTest extends TestCase
{
    use ControllerTrait;

    public function testStatusAction()
    {
        $container = $this->getContainerOrm();
        $queueController = new QueueController();
        $queueController->setContainer($container);
        $response = $queueController->statusAction();
        self::assertArrayHasKey('status', $response);
        $this->runJsCssTest($response);

        $container = $this->getContainerOdm();
        $queueController = new QueueController();
        $queueController->setContainer($container);
        $response = $queueController->statusAction();
        self::assertArrayHasKey('status', $response);
        $this->runJsCssTest($response);
    }

    public function runJsCssTest($response)
    {
        self::assertArrayHasKey('css', $response);
        self::assertArrayHasKey('js', $response);
    }

    public function testJobsAction()
    {
        $container = $this->getContainerOrm();
        $queueController = new QueueController();
        $queueController->setContainer($container);
        $response = $queueController->jobsAction();
        $this->runJsCssTest($response);

        $container = $this->getContainerOdm();
        $queueController = new QueueController();
        $queueController->setContainer($container);
        $response = $queueController->jobsAction();
        $this->runJsCssTest($response);
    }

    public function testJobsRunningAction()
    {
        $container = $this->getContainerOrm();
        $queueController = new QueueController();
        $queueController->setContainer($container);
        $response = $queueController->runningJobsAction();
        $this->runJsCssTest($response);
    }

    public function testWorkersAction()
    {
        $container = $this->getContainerOrm();
        $queueController = new QueueController();
        $queueController->setContainer($container);
        $response = $queueController->workersAction();
        self::assertArrayHasKey('workers', $response);
        $this->runJsCssTest($response);
    }

    public function testArchiveJobsAction()
    {
        $container = $this->getContainerOrm();
        $queueController = new QueueController();
        $queueController->setContainer($container);
        $response = $queueController->archiveAction(new Request());
        self::assertNotNull($response);
        self::assertTrue($response instanceof StreamedResponse);
    }
}
