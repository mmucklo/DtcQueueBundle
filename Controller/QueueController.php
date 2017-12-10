<?php

namespace Dtc\QueueBundle\Controller;

use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ORM\EntityManager;
use Dtc\QueueBundle\Doctrine\BaseJobTimingManager;
use Dtc\QueueBundle\Exception\UnsupportedException;
use Dtc\QueueBundle\Model\JobTiming;
use Dtc\QueueBundle\Model\Worker;
use Dtc\QueueBundle\ODM\JobManager;
use Dtc\QueueBundle\ODM\JobTimingManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Zend\Stdlib\Request;

class QueueController extends Controller
{
    /**
     * Summary stats.
     *
     * @Route("/")
     * @Route("/status/")
     * @Template("@DtcQueue/Queue/jobs.html.twig")
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
        $jQuery = $this->container->getParameter('dtc_grid.jquery');
        array_unshift($params['js'], $jQuery['url']);
        $params['chartjs'] = $this->container->getParameter('dtc_queue.admin.chartjs');
    }

    protected function validateJobTimingManager()
    {
        if ($this->container->hasParameter('dtc_queue.job_timing_manager')) {
            $this->validateManagerType('dtc_queue.job_timing_manager');
        } elseif ($this->container->hasParameter('dtc_queue.job_timing_manager')) {
            $this->validateManagerType('dtc_queue.run_manager');
        } else {
            $this->validateManagerType('dtc_queue.default_manager');
        }
    }

    protected function validateRunManager()
    {
        if ($this->container->hasParameter('dtc_queue.job_timing_manager')) {
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

    /**
     * Show a graph of job trends.
     *
     * @Route("/trends", name="dtc_queue_trends")
     * @Template("@DtcQueue/Queue/trends.html.twig")
     */
    public function trendsAction()
    {
        $recordTimings = $this->container->getParameter('dtc_queue.record_timings');
        $params = ['record_timings' => $recordTimings, 'states' => JobTiming::getStates()];
        $this->addCssJs($params);

        return $params;
    }

    /**
     * @Route("/timings", name="dtc_queue_timings")
     *
     * @param Request $request
     */
    public function getTimingsAction()
    {
        $request = $this->get('request_stack')->getMasterRequest();
        $begin = $request->query->get('begin');
        $end = $request->query->get('end');
        $type = $request->query->get('type', 'HOUR');
        $beginDate = \DateTime::createFromFormat(DATE_ISO8601, $begin) ?: null;
        $endDate = \DateTime::createFromFormat(DATE_ISO8601, $end) ?: new \DateTime();

        $recordTimings = $this->container->getParameter('dtc_queue.record_timings');
        $params = [];
        if ($recordTimings) {
            $params = $this->calculateTimings($type, $beginDate, $endDate);
        }

        return new JsonResponse($params);
    }

    protected function calculateTimings($type, $beginDate, $endDate)
    {
        $params = [];
        $this->validateJobTimingManager();

        /** @var BaseJobTimingManager $jobTimingManager */
        $jobTimingManager = $this->get('dtc_queue.job_timing_manager');
        if ($jobTimingManager instanceof JobTimingManager) {
            $timings = $this->getJobTimingsOdm($type, $endDate, $beginDate);
        } else {
            $timings = $this->getJobTimingsOrm($type, $endDate, $beginDate);
        }

        $timingStates = JobTiming::getStates();
        $timingsDates = [];
        foreach (array_keys($timingStates) as $state) {
            if (!isset($timings[$state])) {
                continue;
            }
            $timingsData = $timings[$state];
            $timingsDates = array_unique(array_merge(array_keys($timingsData), $timingsDates));
        }

        usort($timingsDates, function ($date1str, $date2str) {
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

        foreach (array_keys($timingStates) as $state) {
            if (!isset($timings[$state])) {
                continue;
            }

            $timingsData = $timings[$state];
            foreach ($timingsDates as $date) {
                $params['timings_data_'.$state][] = isset($timingsData[$date]) ? $timingsData[$date] : 0;
            }
        }
        $params['timings_dates'] = $timingsDates;

        return $params;
    }

    protected function getJobTimingsOdm($type, \DateTime $end, \DateTime $begin = null)
    {
        /** @var JobTimingManager $runManager */
        $jobTimingManager = $this->get('dtc_queue.job_timing_manager');
        $jobTimingClass = $jobTimingManager->getJobTimingClass();

        /** @var DocumentManager $documentManager */
        $documentManager = $jobTimingManager->getObjectManager();

        $regexInfo = $this->getRegexDate($type);
        if (!$begin) {
            $begin = clone $end;
            $begin->sub($regexInfo['interval']);
        }

        // Run a map reduce function get worker and status break down
        $mapFunc = "function() {
            var dateStr = this.finishedAt.toISOString();
            dateStr = dateStr.replace(/{$regexInfo['regex']}/,'{$regexInfo['replacement']}');
            var dateBegin = new Date('{$begin->format('c')}');
            var dateEnd = new Date('{$end->format('c')}');
            if (this.finishedAt >= dateBegin && this.finishedAt <= dateEnd) {
                var result = {};
                result[dateStr] = 1;
                emit(this.status, result);
            }
        }";
        $reduceFunc = JobManager::REDUCE_FUNCTION;
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

    protected function getRegexDate($type)
    {
        switch ($type) {
            case 'YEAR':
                return ['replace' => ['regex' => '(\d+)\-(\d+)\-(\d+)T(\d+):(\d+):(\d+).+$', 'replacement' => '$1'],
                    'interval' => new \DateInterval('P10Y'), ];
            case 'MONTH':
                return ['replace' => ['regex' => '(\d+)\-(\d+)\-(\d+)T(\d+):(\d+):(\d+).+$', 'replacement' => '$1-$2'],
                    'interval' => new \DateInterval('P12M'), ];
            case 'DAY':
                return ['replace' => ['regex' => '(\d+)\-(\d+)\-(\d+)T(\d+):(\d+):(\d+).+$', 'replacement' => '$1-$2-$3'],
                    'interval' => new \DateInterval('P31D'), ];
            case 'HOUR':
                return ['replace' => ['regex' => '(\d+)\-(\d+)\-(\d+)T(\d+):(\d+):(\d+).+$', 'replacement' => '$1-$2-$3 $4'],
                    'interval' => new \DateInterval('PT24H'), ];
            case 'MINUTE':
                return ['replace' => ['regex' => '(\d+)\-(\d+)\-(\d+)T(\d+):(\d+):(\d+).+$', 'replacement' => '$1-$2-$3 $4:$5'],
                    'interval' => new \DateInterval('PT3600S'), ];
        }
        throw new \InvalidArgumentException("Invalid type $type");
    }

    protected function getOrmGroupBy($type)
    {
        switch ($type) {
            case 'YEAR':
                return ['groupby' => 'YEAR(j.finishedAt)',
                        'interval' => new \DateInterval('P10Y'), ];
            case 'MONTH':
                return ['groupby' => 'CONCAT(YEAR(j.finishedAt),\'-\',MONTH(j.finishedAt))',
                        'interval' => new \DateInterval('P12M'), ];
            case 'DAY':
                return ['groupby' => 'CONCAT(YEAR(j.finishedAt),\'-\',MONTH(j.finishedAt),\'-\',DAY(j.finishedAt))',
                        'interval' => new \DateInterval('P31D'), ];
            case 'HOUR':
                return ['groupby' => 'CONCAT(YEAR(j.finishedAt),\'-\',MONTH(j.finishedAt),\'-\',DAY(j.finishedAt),\' \',HOUR(j.finishedAt))',
                        'interval' => new \DateInterval('PT24H'), ];
            case 'MINUTE':
                return ['groupby' => 'CONCAT(YEAR(j.finishedAt),\'-\',MONTH(j.finishedAt),\'-\',DAY(j.finishedAt),\' \',HOUR(j.finishedAt),\':\',MINUTE(j.finishedAt))',
                        'interval' => new \DateInterval('PT3600S'), ];
        }
        throw new \InvalidArgumentException("Invalid type $type");
    }

    protected function getJobTimingsOrm($type, \DateTime $end, \DateTime $begin = null)
    {
        /** @var JobTimingManager $jobTimingManager */
        $jobTimingManager = $this->get('dtc_queue.job_timing_manager');
        $jobTimingClass = $jobTimingManager->getJobTimingClass();
        /** @var EntityManager $entityManager */
        $entityManager = $jobTimingManager->getObjectManager();

        $groupByInfo = $this->getOrmGroupBy($type);

        if (!$begin) {
            $begin = clone $end;
            $begin->sub($groupByInfo['interval']);
        }

        $queryBuilder = $entityManager->createQueryBuilder()->select("j.status as status, count(j.finishedAt) as thecount, {$groupByInfo['groupby']} as thedate")
            ->from($jobTimingClass, 'j')
            ->where('j.finishedAt <= :end')
            ->andWhere('j.finishedAt >= :begin')
            ->setParameter(':end', $end)
            ->setParameter(':begin', $begin)
            ->groupBy('status')
            ->addGroupBy('thedate');

        $result = $queryBuilder
            ->getQuery()->getArrayResult();

        $resultHash = [];
        foreach ($result as $row) {
            $resultHash[$row['status']][$row['thedate']] = intval($row['thecount']);
        }

        return $resultHash;
    }
}
