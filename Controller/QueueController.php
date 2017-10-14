<?php

namespace Dtc\QueueBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class QueueController extends Controller
{
    /**
     * Summary stats.
     *
     * @Route("/")
     * @Route("/status/")
     * @Template()
     */
    public function statusAction()
    {
        $params = array();
        $jobManager = $this->get('dtc_queue.job_manager');

        $params['status'] = $jobManager->getStatus();

        return $params;
    }

    /**
     * List jobs in system by default.
     *
     * @Route("/jobs", name="dtc_queue_jobs")
     */
    public function jobsAction()
    {
        $managerType = $this->container->getParameter('dtc_queue.default_manager');
        if ('mongodb' !== $managerType && 'orm' != $managerType) {
            throw new \Exception("Unsupported manager type: $managerType");
        }

        $rendererFactory = $this->get('dtc_grid.renderer.factory');
        $renderer = $rendererFactory->create('datatables');
        $className = $this->container->getParameter('dtc_queue.class_job');
        $gridSource = $this->get('dtc_grid.manager.source')->get($className);
        $renderer->bind($gridSource);
        $params = $renderer->getParams();

        $renderer2 = $rendererFactory->create('datatables');
        $className = $this->container->getParameter('dtc_queue.class_job_archive');
        $gridSource = $this->get('dtc_grid.manager.source')->get($className);
        $renderer2->bind($gridSource);
        $params2 = $renderer2->getParams();

        $params['archive_grid'] = $params2['dtc_grid'];
        $params['dtc_queue_grid_label1'] = 'Live Jobs';
        $params['dtc_queue_grid_label2'] = 'Archived Jobs';

        return $this->render('@DtcQueue/Queue/grid.html.twig', $params);
    }

    /**
     * List jobs in system by default.
     *
     * @Route("/runs", name="dtc_queue_runs")
     */
    public function runsAction()
    {
        $managerType = $this->container->getParameter('dtc_queue.default_manager');
        if ('mongodb' !== $managerType && 'orm' != $managerType) {
            throw new \Exception("Unsupported manager type: $managerType");
        }

        $rendererFactory = $this->get('dtc_grid.renderer.factory');
        $renderer = $rendererFactory->create('datatables');
        $className = $this->container->getParameter('dtc_queue.class_run');
        $manager = $this->container->get('dtc_queue.job_manager');
        $gridSource = $this->get('dtc_grid.manager.source')->get($className);
        $renderer->bind($gridSource);
        $params = $renderer->getParams();

        $renderer2 = $rendererFactory->create('datatables');
        $className = $this->container->getParameter('dtc_queue.class_run_archive');
        $gridSource = $this->get('dtc_grid.manager.source')->get($className);
        $renderer2->bind($gridSource);
        $params2 = $renderer2->getParams();

        $params['archive_grid'] = $params2['dtc_grid'];

        $params['dtc_queue_grid_label1'] = 'Live Runs';
        $params['dtc_queue_grid_label2'] = 'Archived Runs';

        return $this->render('@DtcQueue/Queue/grid.html.twig', $params);
    }

    /**
     * List registered workers in the system.
     *
     * @Route("/workers/")
     * @Template()
     */
    public function workersAction()
    {
        $params = array();

        return $params;
    }

    /**
     * Show a graph of job trends.
     *
     * @Route("/trends/")
     * @Template()
     */
    public function trendsAction()
    {
    }
}
