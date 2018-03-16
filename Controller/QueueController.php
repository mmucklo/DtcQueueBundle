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
     * @Route("/jobs/")
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
     * @Template("@DtcQueue/Queue/jobs_all.html.twig")
     * @Route("/jobs_all", name="dtc_queue_jobs_all")
     *
     * @throws UnsupportedException
     * @throws \Exception
     */
    public function jobsAllAction()
    {
        $this->validateManagerType('dtc_queue.manager.job');
        $class1 = $this->container->getParameter('dtc_queue.class.job');
        $class2 = $this->container->getParameter('dtc_queue.class.job_archive');
        $label1 = 'Non-Archived Jobs';
        $label2 = 'Archived Jobs';

        $params = $this->getDualGridParams($class1, $class2, $label1, $label2);
        $workersAndMethods = $this->getWorkersAndMethods();
        $params['workers_methods'] = $workersAndMethods['workers_methods'];

        return $params;
    }


    /**
     * List jobs in system by default.
     *
     * @Template("@DtcQueue/Queue/jobs_live.html.twig")
     * @Route("/live_jobs", name="dtc_queue_jobs")
     *
     * @throws UnsupportedException
     * @throws \Exception
     */
    public function liveJobsAction()
    {
        $this->validateManagerType('dtc_queue.manager.job');
        $managerType = $this->container->getParameter('dtc_queue.manager.job');
        $rendererFactory = $this->get('dtc_grid.renderer.factory');
        $renderer = $rendererFactory->create('datatables');
        $gridSource = $this->get('dtc_queue.grid_source.jobs_waiting.'.('mongodb' === $managerType ? 'odm' : $managerType));
        $renderer->bind($gridSource);
        $params = $renderer->getParams();
        $this->addCssJs($params);

        $params['dtc_queue_grid_id'] = $gridSource->getDivId();
        $workersAndMethods = $this->getWorkersAndMethods();
        $params['workers_methods'] = $workersAndMethods['workers_methods'];

        return $params;
    }

    /**
     * List jobs in system by default.
     *
     * @Template("@DtcQueue/Queue/jobs_running.html.twig")
     * @Route("/jobs_running", name="dtc_queue_jobs_running")
     * @throws \Exception
     */
    public function runningJobsAction()
    {
        $this->validateManagerType('dtc_queue.manager.job');
        $managerType = $this->container->getParameter('dtc_queue.manager.job');
        $rendererFactory = $this->get('dtc_grid.renderer.factory');
        $renderer = $rendererFactory->create('datatables');
        $gridSource = $this->get('dtc_queue.grid_source.jobs_running.'.('mongodb' === $managerType ? 'odm' : $managerType));
        $renderer->bind($gridSource);
        $params = $renderer->getParams();
        $this->addCssJs($params);

        $params['dtc_queue_grid_id'] = $gridSource->getDivId();
        $workersAndMethods = $this->getWorkersAndMethods();
        $params['workers_methods'] = $workersAndMethods['workers_methods'];

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
        $id1 = $gridSource->getDivId();
        $renderer->bind($gridSource);
        $params = $renderer->getParams();

        $renderer2 = $rendererFactory->create('datatables');
        $gridSource = $this->get('dtc_grid.manager.source')->get($class2);
        $id2 = $gridSource->getDivId();
        $renderer2->bind($gridSource);
        $params2 = $renderer2->getParams();

        $params['archive_grid'] = $params2['dtc_grid'];
        $params['dtc_queue_grid_id1'] = $id1;
        $params['dtc_queue_grid_id2'] = $id2;
        $params['dtc_queue_grid_label1'] = $label1;
        $params['dtc_queue_grid_label2'] = $label2;
        $this->addCssJs($params);

        return $params;
    }

    /**
     * List jobs in system by default.
     *
     * @Route("/runs", name="dtc_queue_runs")
     * @throws \Exception
     */
    public function runsAction()
    {
        $this->validateRunManager();
        $class1 = $this->container->getParameter('dtc_queue.class.run');
        $class2 = $this->container->getParameter('dtc_queue.class.run_archive');
        $label1 = 'Live Runs';
        $label2 = 'Archived Runs';

        $params = $this->getDualGridParams($class1, $class2, $label1, $label2);

        return $this->render('@DtcQueue/Queue/runs.html.twig', $params);
    }

    /**
     * List registered workers in the system.
     *
     * @Route("/workers", name="dtc_queue_workers")
     * @Template("@DtcQueue/Queue/workers.html.twig")
     */
    public function workersAction()
    {
        $workersAndMethods = $this->getWorkersAndMethods();
        $params = ['workers' => $workersAndMethods['workers']];
        $this->addCssJs($params);

        return $params;
    }
}
