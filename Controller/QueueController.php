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
     * @Route("/jobs/")
     */
    public function jobsAction()
    {
        $rendererFactory = $this->get('dtc_grid.renderer.factory');
        $renderer = $rendererFactory->create('datatables');
        $className = $this->container->getParameter('dtc_queue.job_class');
        $gridSource = $this->get('dtc_grid.manager.source')->get($className);
        $renderer->bind($gridSource);
        $params = $renderer->getParams();

        $renderer2 = $rendererFactory->create('datatables');
        $className = $this->container->getParameter('dtc_queue.job_class_archive');
        $gridSource = $this->get('dtc_grid.manager.source')->get($className);
        $renderer2->bind($gridSource);
        $params2 = $renderer2->getParams();

        $params['archive_grid'] = $params2['dtc_grid'];
        return $this->render('@DtcQueue/Queue/jobs.html.twig', $params);
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
