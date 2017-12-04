<?php

namespace Dtc\QueueBundle\Controller;

use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ORM\EntityManager;
use Dtc\QueueBundle\Doctrine\BaseRunManager;
use Dtc\QueueBundle\Exception\UnsupportedException;
use Dtc\QueueBundle\Model\Worker;
use Dtc\QueueBundle\ODM\RunManager;
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
     * List jobs in system by default.
     *
     * @Template()
     * @Route("/jobs", name="dtc_queue_jobs")
     */
    public function jobsAction()
    {
        $this->validateManagerType('dtc_queue.default_manager');
        $managerType = $this->container->getParameter('dtc_queue.default_manager');
        $rendererFactory = $this->get('dtc_grid.renderer.factory');
        $renderer = $rendererFactory->create('datatables');
        $gridSource = $this->get('dtc_queue.grid_source.live_jobs.'.($managerType = 'mongodb' ? 'odm' : $managerType));
        $renderer->bind($gridSource);
        $params = $renderer->getParams();
        $this->addCssJs($params);

        return $params;
    }

    protected function validateManagerType($type)
    {
        $managerType = $this->container->getParameter($type);
        if ('mongodb' !== $managerType && 'orm' != $managerType && 'odm' != $managerType) {
            throw new UnsupportedException("Unsupported manager type: $managerType");
        }
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

    protected function addCssJs(array &$params)
    {
        $params['css'] = $this->container->getParameter('dtc_grid.theme.css');
        $params['js'] = $this->container->getParameter('dtc_grid.theme.js');
        $params['chartjs'] = $this->container->getParameter('dtc_queue.admin.chartjs');
    }

    protected function validateRunManager()
    {
        if ($this->container->hasParameter('dtc_queue.run_manager')) {
            $this->validateManagerType('dtc_queue.run_manager');
        } else {
            $this->validateManagerType('dtc_queue.default_manager');
        }
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
     * @Route("/workers")
     * @Template()
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

    /**
     * Show a graph of job trends.
     *
     * @Route("/trends")
     * @Template()
     */
    public function trendsAction()
    {
        $recordTimings = $this->container->getParameter('dtc_queue.record_timings');
        $params = ['record_timings' => $recordTimings];
        if ($recordTimings) {
            $this->validateRunManager();

            /** @var BaseRunManager $runManager */
            $runManager = $this->get('dtc_queue.run_manager');
            if ($runManager instanceof RunManager) {
                $timings = $this->getJobTimingsOdm();
            } else {
                $timings = $this->getJobTimingsOrm();
            }
            uksort($timings, function ($date1str, $date2str) {
                $date1 = \DateTime::createFromFormat('Y-m-d H', $date1str);
                $date2 = \DateTime::createFromFormat('Y-m-d H', $date2str);
                if (!$date2) {
                    return false;
                }
                if (!$date1) {
                    return false;
                }

                return $date1 > $date2;
            });
            $params['timings_dates'] = json_encode(array_keys($timings));
            $params['timings_data'] = json_encode(array_values($timings));
        }
        $this->addCssJs($params);

        return $params;
    }

    protected function getJobTimingsOdm()
    {
        /** @var RunManager $runManager */
        $runManager = $this->get('dtc_queue.run_manager');
        $jobTimingClass = $runManager->getJobTimingClass();
        /** @var DocumentManager $documentManager */
        $documentManager = $runManager->getObjectManager();

        // Run a map reduce function get worker and status break down
        $mapFunc = "function() {
            var dateStr = this.finishedAt.toISOString();
            dateStr = dateStr.replace(/:.+\$/,'');
            dateStr = dateStr.replace(/T0*/,' ');
            emit(dateStr, 1);
        }";
        $reduceFunc = 'function(k, vals) {
            return Array.sum(vals);
        }';

        $builder = $documentManager->createQueryBuilder($jobTimingClass);
        $builder->map($mapFunc)
            ->reduce($reduceFunc);
        $query = $builder->getQuery();
        $results = $query->execute();
        $resultHash = [];
        foreach ($results as $info) {
            $resultHash[$info['_id']] = $info['value'];
        }

        return $resultHash;
    }

    protected function getJobTimingsOrm()
    {
        /** @var RunManager $runManager */
        $runManager = $this->get('dtc_queue.run_manager');
        $jobTimingClass = $runManager->getJobTimingClass();
        /** @var EntityManager $entityManager */
        $entityManager = $runManager->getObjectManager();
        $queryBuilder = $entityManager->createQueryBuilder()->select("count(j.finishedAt) as thecount, CONCAT(YEAR(j.finishedAt),'-',MONTH(j.finishedAt),'-',DAY(j.finishedAt),' ',HOUR(j.finishedAt)) as thedate")
            ->from($jobTimingClass, 'j')
            ->groupBy('thedate');

        $result = $queryBuilder
            ->getQuery()->getArrayResult();

        $resultHash = [];
        foreach ($result as $row) {
            $resultHash[$row['thedate']] = intval($row['thecount']);
        }

        return $resultHash;
    }
}
