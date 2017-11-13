<?php

namespace Dtc\QueueBundle\Controller;

use Dtc\QueueBundle\Exception\UnsupportedException;
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
        $class1 = $this->container->getParameter('dtc_queue.class_job');
        $class2 = $this->container->getParameter('dtc_queue.class_job_archive');
        $label1 = 'Live Jobs';
        $label2 = 'Archived Jobs';

        return $this->renderDualGrid($class1, $class2, $label1, $label2);
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
    protected function renderDualGrid($class1, $class2, $label1, $label2)
    {
        $managerType = $this->container->getParameter('dtc_queue.default_manager');
        if ('mongodb' !== $managerType && 'orm' != $managerType) {
            throw new UnsupportedException("Unsupported manager type: $managerType");
        }

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

        return $this->render('@DtcQueue/Queue/grid.html.twig', $params);
    }

    /**
     * List jobs in system by default.
     *
     * @Route("/runs", name="dtc_queue_runs")
     */
    public function runsAction()
    {
        $class1 = $this->container->getParameter('dtc_queue.class_run');
        $class2 = $this->container->getParameter('dtc_queue.class_run_archive');
        $label1 = 'Live Runs';
        $label2 = 'Archived Runs';

        return $this->renderDualGrid($class1, $class2, $label1, $label2);
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
