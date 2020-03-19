<?php

namespace Dtc\QueueBundle\Controller;

use Dtc\QueueBundle\Doctrine\DoctrineJobManager;
use Dtc\QueueBundle\Exception\UnsupportedException;
use Dtc\QueueBundle\Model\BaseJob;
use Dtc\QueueBundle\Model\Worker;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QueueController
{
    use ControllerTrait;

    private $container;

    public function __construct(ContainerInterface $container) {
        $this->container = $container;
    }

    /**
     * Summary stats.
     */
    public function status()
    {
        $params = [];
        $jobManager = $this->container->get('dtc_queue.manager.job');

        $params['status'] = $jobManager->getStatus();
        $this->addCssJs($params);

        return $this->render('@DtcQueue/Queue/status.html.twig', $params);
    }

    /**
     * List jobs in system by default.
     *
     * @throws UnsupportedException|\Exception
     */
    public function jobsAll()
    {
        $this->validateManagerType('dtc_queue.manager.job');
        $this->checkDtcGridBundle();

        $class1 = $this->container->getParameter('dtc_queue.class.job');
        $class2 = $this->container->getParameter('dtc_queue.class.job_archive');
        $label1 = 'Non-Archived Jobs';
        $label2 = 'Archived Jobs';

        $params = $this->getDualGridParams($class1, $class2, $label1, $label2);

        return $this->render('@DtcQueue/Queue/grid.html.twig', $params);
    }

    /**
     * @throws UnsupportedException
     */
    public function archive(Request $request)
    {
        return $this->streamResults($request, 'archiveAllJobs');
    }

    /**
     * @return StreamedResponse
     * @throws UnsupportedException
     */
    public function resetStalled(Request $request)
    {
        return $this->streamResults($request, 'resetStalledJobs');
    }

    /**
     * @return StreamedResponse
     *
     * @throws UnsupportedException
     */
    public function pruneStalled(Request $request)
    {
        return $this->streamResults($request, 'pruneStalledJobs');
    }

    /**
     * @param $functionName
     *
     * @return StreamedResponse
     *
     * @throws UnsupportedException
     */
    protected function streamResults(Request $request, $functionName)
    {
        $jobManager = $this->container->get('dtc_queue.manager.job');
        if (!$jobManager instanceof DoctrineJobManager) {
            throw new UnsupportedException('$jobManager must be instance of '.DoctrineJobManager::class);
        }

        $streamingResponse = new StreamedResponse($this->getStreamFunction($request, $functionName));
        $streamingResponse->headers->set('Content-Type', 'application/x-ndjson');
        $streamingResponse->headers->set('X-Accel-Buffering', 'no');

        return $streamingResponse;
    }

    /**
     * @param string $functionName
     *
     * @return \Closure
     */
    protected function getStreamFunction(Request $request, $functionName)
    {
        $jobManager = $this->container->get('dtc_queue.manager.job');
        $workerName = $request->get('workerName');
        $methodName = $request->get('method');
        $total = null;
        $callback = function ($count, $totalCount) use (&$total) {
            if (null !== $totalCount && null === $total) {
                $total = $totalCount;
                echo json_encode(['total' => $total]);
                echo "\n";
                flush();

                return;
            }
            echo json_encode(['count' => $count]);
            echo "\n";
            flush();
        };

        return function () use ($jobManager, $callback, $workerName, $methodName, $functionName, &$total) {
            switch ($functionName) {
                case 'archiveAllJobs':
                    $total = $jobManager->countLiveJobs($workerName, $methodName);
                    echo json_encode(['total' => $total]);
                    echo "\n";
                    flush();
                    if ($total > 0) {
                        $jobManager->archiveAllJobs($workerName, $methodName, $callback);
                    }
                    break;
                default:
                    $jobManager->$functionName($workerName, $methodName, $callback);
                    break;
            }
        };
    }

    /**
     * List jobs in system by default.
     *
     * @throws UnsupportedException|\Exception
     */
    public function jobs()
    {
        $this->validateManagerType('dtc_queue.manager.job');
        $this->checkDtcGridBundle();
        $managerType = $this->container->getParameter('dtc_queue.manager.job');
        $rendererFactory = $this->container->get('dtc_grid.renderer.factory');
        $renderer = $rendererFactory->create('datatables');
        $gridSource = $this->container->get('dtc_queue.grid_source.jobs_waiting.'.('mongodb' === $managerType ? 'odm' : $managerType));
        $renderer->bind($gridSource);
        $params = $renderer->getParams();
        $this->addCssJs($params);

        $params['worker_methods'] = $this->container->get('dtc_queue.manager.job')->getWorkersAndMethods();
        $params['prompt_message'] = 'This will archive all non-running jobs';

        return $this->render('@DtcQueue/Queue/jobs.html.twig', $params);
    }

    /**
     * List jobs in system by default.
     *
     * @throws UnsupportedException|\Exception
     */
    public function jobsRunning()
    {
        $this->validateManagerType('dtc_queue.manager.job');
        $this->checkDtcGridBundle();
        $managerType = $this->container->getParameter('dtc_queue.manager.job');
        $rendererFactory = $this->container->get('dtc_grid.renderer.factory');
        $renderer = $rendererFactory->create('datatables');
        $gridSource = $this->container->get('dtc_queue.grid_source.jobs_running.'.('mongodb' === $managerType ? 'odm' : $managerType));
        $renderer->bind($gridSource);
        $params = $renderer->getParams();
        $this->addCssJs($params);

        $params['worker_methods'] = $this->container->get('dtc_queue.manager.job')->getWorkersAndMethods(BaseJob::STATUS_RUNNING);
        $params['prompt_message'] = 'This will prune all stalled jobs';

        return $this->render('@DtcQueue/Queue/jobs_running.html.twig', $params);
    }

    /**
     * @param string $class1
     * @param string $class2
     * @param string $label1
     * @param string $label2
     *
     * @return array
     *
     * @throws \Exception
     */
    protected function getDualGridParams($class1, $class2, $label1, $label2)
    {
        $rendererFactory = $this->container->get('dtc_grid.renderer.factory');
        $renderer = $rendererFactory->create('datatables');
        $gridSource = $this->container->get('dtc_grid.manager.source')->get($class1);
        $renderer->bind($gridSource);
        $params = $renderer->getParams();

        $renderer2 = $rendererFactory->create('datatables');
        $gridSource = $this->container->get('dtc_grid.manager.source')->get($class2);
        $renderer2->bind($gridSource);
        $params2 = $renderer2->getParams();

        $params['archive_grid'] = $params2['dtc_grid'];

        $params['dtc_queue_grid_label1'] = $label1;
        $params['dtc_queue_grid_label2'] = $label2;
        $this->addCssJs($params);

        return $params;
    }

    /**
     * List jobs in system by default.
     *
     * @throws UnsupportedException|\Exception
     */
    public function runs()
    {
        $this->validateRunManager();
        $this->checkDtcGridBundle();
        $class1 = $this->container->getParameter('dtc_queue.class.run');
        $class2 = $this->container->getParameter('dtc_queue.class.run_archive');
        $label1 = 'Live Runs';
        $label2 = 'Archived Runs';

        $params = $this->getDualGridParams($class1, $class2, $label1, $label2);
        return $this->render('@DtcQueue/Queue/grid.html.twig', $params);
    }

    /**
     * List registered workers in the system.
     *
     */
    public function workers()
    {
        $workerManager = $this->container->get('dtc_queue.manager.worker');
        $workers = $workerManager->getWorkers();

        $workerList = [];
        foreach ($workers as $workerName => $worker) {
            /* @var Worker $worker */
            $workerList[$workerName] = get_class($worker);
        }
        $params = ['workers' => $workerList];
        $this->addCssJs($params);

        return $this->render('@DtcQueue/Queue/workers.html.twig', $params);
    }

    /**
     * Validates that DtcGridBundle exists.
     *
     * @throws UnsupportedException
     */
    protected function checkDtcGridBundle()
    {
        if (!class_exists('Dtc\GridBundle\DtcGridBundle')) {
            throw new UnsupportedException('DtcGridBundle (mmucklo/grid-bundle) needs to be installed.');
        }
    }
}
