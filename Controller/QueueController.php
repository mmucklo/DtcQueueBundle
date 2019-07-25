<?php

namespace Dtc\QueueBundle\Controller;

use Dtc\QueueBundle\Doctrine\DoctrineJobManager;
use Dtc\QueueBundle\Exception\UnsupportedException;
use Dtc\QueueBundle\Model\BaseJob;
use Dtc\QueueBundle\Model\Worker;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QueueController extends Controller
{
    use ControllerTrait;

    /**
     * Summary stats.
     *
     * @Route("/")
     * @Route("/status/")
     * @Template("@DtcQueue/Queue/status.html.twig")
     */
    public function statusAction()
    {
        $params = array();
        $jobManager = $this->get('dtc_queue.manager.job');

        $params['status'] = $jobManager->getStatus();
        $this->addCssJs($params);

        return $params;
    }

    /**
     * List jobs in system by default.
     *
     * @Route("/jobs_all", name="dtc_queue_jobs_all")
     *
     * @throws UnsupportedException|\Exception
     */
    public function jobsAllAction()
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
     * @Route("/archive", name="dtc_queue_archive")
     * @Method({"POST"})
     *
     * @throws UnsupportedException
     */
    public function archiveAction(Request $request)
    {
        return $this->streamResults($request, 'archiveAllJobs');
    }

    /**
     * @Route("/reset-stalled", name="dtc_queue_reset_stalled")
     *
     * @param Request $request
     *
     * @return StreamedResponse
     *
     * @throws UnsupportedException
     */
    public function resetStalledAction(Request $request)
    {
        return $this->streamResults($request, 'resetStalledJobs');
    }

    /**
     * @Route("/prune-stalled", name="dtc_queue_prune_stalled")
     *
     * @param Request $request
     *
     * @return StreamedResponse
     *
     * @throws UnsupportedException
     */
    public function pruneStalledAction(Request $request)
    {
        return $this->streamResults($request, 'pruneStalledJobs');
    }

    /**
     * @param Request $request
     * @param $functionName
     *
     * @return StreamedResponse
     *
     * @throws UnsupportedException
     */
    protected function streamResults(Request $request, $functionName)
    {
        $jobManager = $this->get('dtc_queue.manager.job');
        if (!$jobManager instanceof DoctrineJobManager) {
            throw new UnsupportedException('$jobManager must be instance of '.DoctrineJobManager::class);
        }

        $streamingResponse = new StreamedResponse($this->getStreamFunction($request, $functionName));
        $streamingResponse->headers->set('Content-Type', 'application/x-ndjson');
        $streamingResponse->headers->set('X-Accel-Buffering', 'no');

        return $streamingResponse;
    }

    /**
     * @param Request $request
     * @param string  $functionName
     *
     * @return \Closure
     */
    protected function getStreamFunction(Request $request, $functionName)
    {
        $jobManager = $this->get('dtc_queue.manager.job');
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
     * @Template("@DtcQueue/Queue/jobs.html.twig")
     * @Route("/jobs", name="dtc_queue_jobs")
     *
     * @throws UnsupportedException|\Exception
     */
    public function jobsAction()
    {
        $this->validateManagerType('dtc_queue.manager.job');
        $this->checkDtcGridBundle();
        $managerType = $this->container->getParameter('dtc_queue.manager.job');
        $rendererFactory = $this->get('dtc_grid.renderer.factory');
        $renderer = $rendererFactory->create('datatables');
        $gridSource = $this->get('dtc_queue.grid_source.jobs_waiting.'.('mongodb' === $managerType ? 'odm' : $managerType));
        $renderer->bind($gridSource);
        $params = $renderer->getParams();
        $this->addCssJs($params);

        $params['worker_methods'] = $this->get('dtc_queue.manager.job')->getWorkersAndMethods();
        $params['prompt_message'] = 'This will archive all non-running jobs';

        return $params;
    }

    /**
     * List jobs in system by default.
     *
     * @Template("@DtcQueue/Queue/jobs_running.html.twig")
     * @Route("/jobs_running", name="dtc_queue_jobs_running")
     * @throws UnsupportedException|\Exception
     */
    public function runningJobsAction()
    {
        $this->validateManagerType('dtc_queue.manager.job');
        $this->checkDtcGridBundle();
        $managerType = $this->container->getParameter('dtc_queue.manager.job');
        $rendererFactory = $this->get('dtc_grid.renderer.factory');
        $renderer = $rendererFactory->create('datatables');
        $gridSource = $this->get('dtc_queue.grid_source.jobs_running.'.('mongodb' === $managerType ? 'odm' : $managerType));
        $renderer->bind($gridSource);
        $params = $renderer->getParams();
        $this->addCssJs($params);

        $params['worker_methods'] = $this->get('dtc_queue.manager.job')->getWorkersAndMethods(BaseJob::STATUS_RUNNING);
        $params['prompt_message'] = 'This will prune all stalled jobs';

        return $params;
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
        $rendererFactory = $this->get('dtc_grid.renderer.factory');
        $renderer = $rendererFactory->create('datatables');
        $gridSource = $this->get('dtc_grid.manager.source')->get($class1);
        $renderer->bind($gridSource);
        $params = $renderer->getParams();

        $renderer2 = $rendererFactory->create('datatables');
        $gridSource = $this->get('dtc_grid.manager.source')->get($class2);
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
     * @Route("/runs", name="dtc_queue_runs")
     * @throws UnsupportedException|\Exception
     */
    public function runsAction()
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
     * @Route("/workers", name="dtc_queue_workers")
     * @Template("@DtcQueue/Queue/workers.html.twig")
     */
    public function workersAction()
    {
        $workerManager = $this->get('dtc_queue.manager.worker');
        $workers = $workerManager->getWorkers();

        $workerList = [];
        foreach ($workers as $workerName => $worker) {
            /* @var Worker $worker */
            $workerList[$workerName] = get_class($worker);
        }
        $params = ['workers' => $workerList];
        $this->addCssJs($params);

        return $params;
    }

    /**
     * Validates that DtcGridBundle exists
     * @throws UnsupportedException
     */
    protected function checkDtcGridBundle() {
        if (!class_exists('Dtc\GridBundle\DtcGridBundle')) {
            throw new UnsupportedException("DtcGridBundle (mmucklo/grid-bundle) needs to be installed.");
        }
    }
}
