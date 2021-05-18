<?php

namespace Dtc\QueueBundle\Tests\Controller;

use Dtc\QueueBundle\Controller\QueueController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QueueControllerTest extends TestCase
{
    use ControllerTrait;

    public function testStatus()
    {
        $container = $this->getContainerOrm();
        $queueController = new QueueController($container);
        $response = $queueController->status();
        self::assertStringContainsString('status', $response->getContent());
        $this->runJsCssTest($response);

        $container = $this->getContainerOdm();
        $queueController = new QueueController($container);
        $response = $queueController->status();
        self::assertStringContainsString('status', $response->getContent());
        $this->runJsCssTest($response);
    }

    public function testJobs()
    {
        $container = $this->getContainerOrm();
        $queueController = new QueueController($container);
        $response = $queueController->jobs();
        $this->runJsCssTest($response);

        $container = $this->getContainerOdm();
        $queueController = new QueueController($container);
        $response = $queueController->jobs();
        $this->runJsCssTest($response);
    }

    public function testRuns()
    {
        $container = $this->getContainerOrm();
        $queueController = new QueueController($container);
        $response = $queueController->runs();
        self::assertTrue($response instanceof Response);

        $container = $this->getContainerOdm();
        $queueController = new QueueController($container);
        $response = $queueController->runs();
        self::assertTrue($response instanceof Response);
    }

    public function testAllJobs()
    {
        $container = $this->getContainerOrm();
        $queueController = new QueueController($container);
        $response = $queueController->jobsAll();
        self::assertTrue($response instanceof Response);

        $container = $this->getContainerOdm();
        $queueController = new QueueController($container);
        $response = $queueController->jobsAll();
        self::assertTrue($response instanceof Response);
    }

    public function testJobsRunning()
    {
        $container = $this->getContainerOrm();
        $queueController = new QueueController($container);
        $response = $queueController->jobsRunning();
        $this->runJsCssTest($response);
    }

    public function testWorkers()
    {
        $container = $this->getContainerOrm();
        $queueController = new QueueController($container);
        $response = $queueController->workers();
        self::assertStringContainsString('workers', $response->getContent());
        $this->runJsCssTest($response);
    }

    public function testArchiveJobs()
    {
        $container = $this->getContainerOrm();
        $queueController = new QueueController($container);
        $response = $queueController->archive(new Request());
        self::assertNotNull($response);
        self::assertTrue($response instanceof StreamedResponse);

        $request = new Request();
        $request->query->set('workerName', 'fibonacci');
        $request->query->set('method', 'fibonacci');
        $response = $queueController->archive($request);
        self::assertNotNull($response);
        self::assertTrue($response instanceof StreamedResponse);

        $container = $this->getContainerOdm();
        $queueController = new QueueController($container);
        $response = $queueController->archive(new Request());
        self::assertNotNull($response);
        self::assertTrue($response instanceof StreamedResponse);

        $request = new Request();
        $request->query->set('workerName', 'fibonacci');
        $request->query->set('method', 'fibonacci');
        $response = $queueController->archive($request);
        self::assertNotNull($response);
        self::assertTrue($response instanceof StreamedResponse);
    }
}
