<?php

namespace Dtc\QueueBundle\Tests\Controller;

use Dtc\QueueBundle\Controller\TrendsController;
use Dtc\QueueBundle\ORM\JobTimingManager;
use Dtc\QueueBundle\Model\BaseJob;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class TrendsControllerTest extends TestCase
{
    use ControllerTrait;

    public function testTimingsAction()
    {
        $container = $this->getContainerOrm();
        $this->runTimingsActionTests($container);
        $container = $this->getContainerOdm();
        $this->runTimingsActionTests($container);
    }

    public function testTrendsAction()
    {
        $container = $this->getContainerOrm();
        $trendsController = new TrendsController();
        $trendsController->setContainer($container);
        $response = $trendsController->trendsAction();
        $this->runJsCssTest($response);
    }

    /**
     * @param \Symfony\Component\DependencyInjection\Container $container
     */
    public function runTimingsActionTests($container)
    {
        $trendsController = new TrendsController();
        $trendsController->setContainer($container);

        $dateTimeStr = '2017-07-01T4:04:04Z';
        $dateTime = \DateTime::createFromFormat(DATE_ISO8601, $dateTimeStr, new \DateTimeZone(date_default_timezone_get()));
        self::assertNotFalse($dateTime);

        /** @var JobTimingManager $jobTimingManager */
        $jobTimingManager = $container->get('dtc_queue.manager.job_timing');
        $jobTimingManager->recordTiming(BaseJob::STATUS_SUCCESS, $dateTime);
        $jobTimingManager->recordTiming(BaseJob::STATUS_SUCCESS, $dateTime);
        $jobTimingManager->recordTiming(BaseJob::STATUS_EXCEPTION, $dateTime);

        $request = new Request();
        $request->query->set('type', 'HOUR');
        $request->query->set('end', '2017-07-01T5:05:00.0Z');
        $timings = $trendsController->getTimingsAction($request);
        $content = $timings->getContent();

        self::assertNotEmpty($content);
        $contentDecoded = json_decode($content, true);
        self::assertCount(3, $contentDecoded);
        self::assertEquals([3], $contentDecoded['timings_data_0']);
        self::assertEquals(['2017-07-01 04'], $contentDecoded['timings_dates']);
        self::assertEquals(['2017-07-01T04:00:00+00:00'], $contentDecoded['timings_dates_rfc3339']);

        $request = new Request();
        $request->query->set('type', 'MINUTE');
        $request->query->set('end', '2017-07-01T4:05:04.0Z');
        $timings = $trendsController->getTimingsAction($request);
        $content = $timings->getContent();
        $contentDecoded = json_decode($content, true);
        self::assertEquals(['2017-07-01 04:04'], $contentDecoded['timings_dates']);
        self::assertEquals(['2017-07-01T04:04:00+00:00'], $contentDecoded['timings_dates_rfc3339']);

        $request = new Request();
        $request->query->set('type', 'DAY');
        $request->query->set('end', '2017-07-01T4:05:04.0Z');
        $timings = $trendsController->getTimingsAction($request);
        $content = $timings->getContent();
        $contentDecoded = json_decode($content, true);
        self::assertEquals(['2017-07-01'], $contentDecoded['timings_dates']);

        $request = new Request();
        $request->query->set('type', 'MONTH');
        $request->query->set('end', '2017-07-01T4:05:04.0Z');
        $timings = $trendsController->getTimingsAction($request);
        $content = $timings->getContent();
        $contentDecoded = json_decode($content, true);
        self::assertEquals(['2017-07'], $contentDecoded['timings_dates']);

        $request = new Request();
        $request->query->set('type', 'YEAR');
        $request->query->set('end', '2017-07-01T4:05:04.0Z');
        $timings = $trendsController->getTimingsAction($request);
        $content = $timings->getContent();
        $contentDecoded = json_decode($content, true);
        self::assertEquals(['2017'], $contentDecoded['timings_dates']);
    }
}
