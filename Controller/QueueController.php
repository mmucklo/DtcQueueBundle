<?php

namespace Dtc\QueueBundle\Controller;

use Dtc\QueueBundle\Model\Worker;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

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
        $jobManager = $this->get('dtc_queue.job_manager');

        $params['status'] = $jobManager->getStatus();
        $this->addCssJs($params);

        return $params;
    }

    /**
     * List jobs in system by default.
     *
     * @Route("/jobs_all", name="dtc_queue_jobs_all")
     */
    public function jobsAllAction()
    {
        $this->validateManagerType('dtc_queue.default_manager');
        $class1 = $this->container->getParameter('dtc_queue.class_job');
        $class2 = $this->container->getParameter('dtc_queue.class_job_archive');
        $label1 = 'Non-Archived Jobs';
        $label2 = 'Archived Jobs';

        $params = $this->getDualGridParams($class1, $class2, $label1, $label2);

        return $this->render('@DtcQueue/Queue/grid.html.twig', $params);
    }

    /**
     * @Route("/archive", name="dtc_queue_archive")
     * @Method({"POST"})
     */
    public function archiveAction(Request $request) {
        $workerName = $request->get('workerName');
        $method = $request->get('method');

        $jobManager = $this->get('dtc_queue.job_manager');
        $jobManager->archiveAllJobs($workerName, $method);
        return new JsonResponse(['success' => true]);
    }

    /**
     * List jobs in system by default.
     *
     * @Template("@DtcQueue/Queue/jobs.html.twig")
     * @Route("/jobs", name="dtc_queue_jobs")
     */
    public function jobsAction()
    {
        $this->validateManagerType('dtc_queue.default_manager');
        $managerType = $this->container->getParameter('dtc_queue.default_manager');
        $rendererFactory = $this->get('dtc_grid.renderer.factory');
        $renderer = $rendererFactory->create('datatables');
        $gridSource = $this->get('dtc_queue.grid_source.live_jobs.'.('mongodb' === $managerType ? 'odm' : $managerType));
        $renderer->bind($gridSource);
        $params = $renderer->getParams();
        $this->addCssJs($params);

        $params['worker_methods'] = $this->get('dtc_queue.job_manager')->getWorkersAndMethods();

        return $params;
    }

    /**
     * @param string $class1
     * @param string $class2
     * @param string $label1
     * @param string $label2
     *
     * @return \Symfony\Component\HttpFoundation\Response
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
     */
    public function runsAction()
    {
        $this->validateRunManager();
        $class1 = $this->container->getParameter('dtc_queue.class_run');
        $class2 = $this->container->getParameter('dtc_queue.class_run_archive');
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
        $workerManager = $this->get('dtc_queue.worker_manager');
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
}
