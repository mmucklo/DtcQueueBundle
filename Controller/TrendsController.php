<?php

namespace Dtc\QueueBundle\Controller;

use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ORM\EntityManager;
use Dtc\QueueBundle\Doctrine\BaseJobTimingManager;
use Dtc\QueueBundle\Model\JobTiming;
use Dtc\QueueBundle\ODM\JobManager;
use Dtc\QueueBundle\ODM\JobTimingManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class TrendsController extends Controller
{
    use ControllerTrait;

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
     */
    public function getTimingsAction(Request $request)
    {
        $begin = $request->query->get('begin');
        $end = $request->query->get('end');
        $type = $request->query->get('type', 'HOUR');
        $beginDate = \DateTime::createFromFormat('Y-m-d\TH:i:s.uO', $begin) ?: null;
        $endDate = \DateTime::createFromFormat('Y-m-d\TH:i:s.uO', $end) ?: new \DateTime();

        $recordTimings = $this->container->getParameter('dtc_queue.record_timings');
        $params = [];
        if ($recordTimings) {
            $params = $this->calculateTimings($type, $beginDate, $endDate);
        }

        return new JsonResponse($params);
    }

    /**
     * @param \DateTime|null $beginDate
     * @param \DateTime      $endDate
     */
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

        $format = $this->getDateFormat($type);
        usort($timingsDates, function($date1str, $date2str) use ($format) {
            $date1 = \DateTime::createFromFormat($format, $date1str);
            $date2 = \DateTime::createFromFormat($format, $date2str);
            if (!$date2) {
                return false;
            }
            if (!$date1) {
                return false;
            }

            return $date1 > $date2;
        });

        $timingsDatesAdjusted = $this->getTimingsDatesAdjusted($timingsDates, $format);
        $this->setTimingsData($timingStates, $timings, $timingsDates, $params);
        $params['timings_dates'] = $timingsDates;
        $params['timings_dates_rfc3339'] = $timingsDatesAdjusted;

        return $params;
    }

    /**
     * Timings offset by timezone if necessary.
     *
     * @param array  $timingsDates
     * @param string $format
     *
     * @return array
     */
    protected function getTimingsDatesAdjusted(array $timingsDates, $format)
    {
        $timezoneOffset = $this->container->getParameter('dtc_queue.record_timings_timezone_offset');
        $timingsDatesAdjusted = [];
        foreach ($timingsDates as $dateStr) {
            $date = \DateTime::createFromFormat($format, $dateStr);
            if (0 !== $timezoneOffset) {
                $date->setTimestamp($date->getTimestamp() + ($timezoneOffset * 3600));
            }
            if (false === $date) {
                throw new \InvalidArgumentException("'$date' is not in the right format: ".DATE_RFC3339);
            }
            $timingsDatesAdjusted[] = $date->format(DATE_RFC3339);
        }

        return $timingsDatesAdjusted;
    }

    protected function setTimingsData(array $timingStates, array $timings, array $timingsDates, array &$params)
    {
        foreach (array_keys($timingStates) as $state) {
            if (!isset($timings[$state])) {
                continue;
            }

            $timingsData = $timings[$state];
            foreach ($timingsDates as $date) {
                $params['timings_data_'.$state][] = isset($timingsData[$date]) ? $timingsData[$date] : 0;
            }
        }
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
        $mapFunc = 'function() {
            var dateStr = this.finishedAt.toISOString();
            dateStr = dateStr.replace(/'.$regexInfo['replace']['regex']."/,'".$regexInfo['replace']['replacement']."');
            var dateBegin = new Date('".$begin->format('c')."');
            var dateEnd = new Date('".$end->format('c')."');
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

    protected function getDateFormat($type)
    {
        switch ($type) {
            case 'YEAR':
                return 'Y';
            case 'MONTH':
                return 'Y-m';
            case 'DAY':
                return 'Y-m-d';
            case 'HOUR':
                return 'Y-m-d H';
            case 'MINUTE':
                return 'Y-m-d H:i';
            default:
                throw new \InvalidArgumentException("Invalid date format type '$type''");
        }
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
            $date = $this->formatOrmDateTime($type, $row['thedate']);
            $resultHash[$row['status']][$date] = intval($row['thecount']);
        }

        return $resultHash;
    }

    protected function strPadLeft($str)
    {
        return str_pad($str, 2, '0', STR_PAD_LEFT);
    }

    protected function formatOrmDateTime($type, $str)
    {
        switch ($type) {
            case 'MONTH':
                $parts = explode('-', $str);

                return $parts[0].'-'.$this->strPadLeft($parts[1]);
            case 'DAY':
                $parts = explode('-', $str);

                return $parts[0].'-'.$this->strPadLeft($parts[1]).'-'.$this->strPadLeft($parts[2]);
            case 'HOUR':
                $parts = explode(' ', $str);
                $dateParts = explode('-', $parts[0]);

                return $dateParts[0].'-'.$this->strPadLeft($dateParts[1]).'-'.$this->strPadLeft($dateParts[2]).' '.$this->strPadLeft($parts[1]);
            case 'MINUTE':
                $parts = explode(' ', $str);
                $dateParts = explode('-', $parts[0]);
                $timeParts = explode(':', $parts[1]);

                return $dateParts[0].'-'.$this->strPadLeft($dateParts[1]).'-'.$this->strPadLeft($dateParts[2]).' '.$this->strPadLeft($timeParts[0]).':'.$this->strPadLeft($timeParts[1]);
        }

        return $str;
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
}
