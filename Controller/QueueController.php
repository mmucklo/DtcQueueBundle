<?php
namespace Dtc\QueueBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

class QueueController
    extends Controller
{
    /**
     * Summary stats
     *
     * @Route("/")
     * @Route("/status/")
     * @Template()
     */
    public function statusAction() {
        $params = array();
        $jobManager = $this->get('dtc_queue.job_manager');
        $workerName = null;
        $methodName = null;

        $params['status'] = $jobManager->getStatus();

        return $params;
    }

    /**
     * List jobs in system by default
     *
     * @Route("/jobs/")
     * @Template()
     */
    public function jobsAction() {
        $renderer = $this->get('grid.renderer.jq_table_grid');

        $gridSource = $this->get('dtc_queue.grid.source.job');
        $renderer->bind($gridSource);

        return array('grid' => $renderer);
    }

    /**
     * List registered workers in the system
     *
     * @Route("/jobs/")
     * @Template()
     */
    public function workersAction() {
        $params = array();
        ve('testing one two...');

        return $params;
    }

    /**
     * Show a graph of job trends
     * @Route("/trends/")
     * @Template()
     */
    public function trendsAction() {

    }
}
