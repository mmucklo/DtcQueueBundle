<?php

namespace Dtc\QueueBundle\Controller;

use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ORM\EntityManager;
use Dtc\QueueBundle\Doctrine\DoctrineJobTimingManager;
use Dtc\QueueBundle\Model\JobTiming;
use Dtc\QueueBundle\ODM\JobManager;
use Dtc\QueueBundle\ODM\JobTimingManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class TrendsController
{
    use ControllerTrait;

    private $container;

    public function __construct(ContainerInterface $container) {
        $this->container = $container;
    }

    /**
     * Show a graph of job trends.
     */
    public function trends()
    {
        $recordTimings = $this->container->getParameter('dtc_queue.timings.record');
        $foundYearFunction = class_exists('Oro\ORM\Query\AST\Platform\Functions\Mysql\Year') || class_exists('DoctrineExtensions\Query\Mysql\Year');
        $params = ['record_timings' => $recordTimings, 'states' => JobTiming::getStates(), 'found_year_function' => $foundYearFunction];
        $this->addCssJs($params);

        return $this->render('@DtcQueue/Queue/trends.html.twig', $params);
    }

    public function timings(Request $request)
    {
        $begin = $request->query->get('begin');
        $end = $request->query->get('end');
        $type = $request->query->get('type', 'HOUR');
        $beginDate = \DateTime::createFromFormat('Y-m-d\TH:i:s.uO', $begin) ?: null;
        if ($beginDate instanceof \DateTime) {
            $beginDate->setTimezone(new \DateTimeZone(date_default_timezone_get()));
        }
        $endDate = \DateTime::createFromFormat('Y-m-d\TH:i:s.uO', $end) ?: \Dtc\QueueBundle\Util\Util::getMicrotimeDateTime();
        if ($endDate instanceof \DateTime) {
            $endDate->setTimezone(new \DateTimeZone(date_default_timezone_get()));
        }

        $recordTimings = $this->container->getParameter('dtc_queue.timings.record');
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

        /** @var DoctrineJobTimingManager $jobTimingManager */
        $jobTimingManager = $this->get('dtc_queue.manager.job_timing');
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
        usort($timingsDates, function ($date1str, $date2str) use ($format) {
            $date1 = \DateTime::createFromFormat($format, $date1str);
            $date2 = \DateTime::createFromFormat($format, $date2str);
            if (!$date2) {
                return false;
            }
            if (!$date1) {
                return false;
            }
            $date1->setTimezone(new \DateTimeZone(date_default_timezone_get()));
            $date2->setTimezone(new \DateTimeZone(date_default_timezone_get()));

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
     * @param string $format
     *
     * @return array
     */
    protected function getTimingsDatesAdjusted(array $timingsDates, $format)
    {
        $timezoneOffset = $this->container->getParameter('dtc_queue.timings.timezone_offset');
        $timingsDatesAdjusted = [];
        foreach ($timingsDates as $dateStr) {
            $date = \DateTime::createFromFormat($format, $dateStr);
            if (false === $date) {
                throw new \InvalidArgumentException("'$dateStr' is not in the right format: ".DATE_RFC3339);
            }
            $date->setTimezone(new \DateTimeZone(date_default_timezone_get()));
            if (0 !== $timezoneOffset) {
                // This may too simplistic in areas that observe DST - does the database or PHP code observe DST?
                $date->setTimestamp($date->getTimestamp() + ($timezoneOffset * 3600));
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

    /**
     * @param string                                 $type
     * @param \Doctrine\ODM\MongoDB\Aggregation\Expr $expr
     *
     * @return mixed
     */
    protected function addJobTimingsDateInfo($type, $expr)
    {
        switch ($type) {
            case 'YEAR':
                return $expr->field('year')
                    ->year('$finishedAt');
            case 'MONTH':
                return $expr->field('year')
                    ->year('$finishedAt')
                    ->field('month')
                    ->month('$finishedAt');
            case 'DAY':
                return $expr->field('year')
                    ->year('$finishedAt')
                    ->field('month')
                    ->month('$finishedAt')
                    ->field('day')
                    ->dayOfMonth('$finishedAt');
            case 'HOUR':
                return $expr->field('year')
                    ->year('$finishedAt')
                    ->field('month')
                    ->month('$finishedAt')
                    ->field('day')
                    ->dayOfMonth('$finishedAt')
                    ->field('hour')
                    ->hour('$finishedAt');
            case 'MINUTE':
                return $expr->field('year')
                    ->year('$finishedAt')
                    ->field('month')
                    ->month('$finishedAt')
                    ->field('day')
                    ->dayOfMonth('$finishedAt')
                    ->field('hour')
                    ->hour('$finishedAt')
                    ->field('minute')
                    ->minute('$finishedAt');
            default:
                throw new \InvalidArgumentException("Unknown type $type");
        }
    }

    protected function getJobTImingsOdmMapReduce($builder, $type, \DateTime $end, \DateTime $begin = null)
    {
        $regexInfo = $this->getRegexDate($type);
        if (!$begin) {
            $begin = clone $end;
            $begin->sub($regexInfo['interval']);
        }

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

        // Run a map reduce function get worker and status break down
        $reduceFunc = JobManager::REDUCE_FUNCTION;
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

    protected function getJobTimingsOdm($type, \DateTime $end, \DateTime $begin = null)
    {
        /** @var JobTimingManager $runManager */
        $jobTimingManager = $this->get('dtc_queue.manager.job_timing');
        $jobTimingClass = $jobTimingManager->getJobTimingClass();

        /** @var DocumentManager $documentManager */
        $documentManager = $jobTimingManager->getObjectManager();

        $builder = $documentManager->createQueryBuilder($jobTimingClass);
        if (method_exists($builder, 'map')) {
            return $this->getJobTimingsOdmMapReduce($builder, $type, $end, $begin);
        }

        $regexInfo = $this->getRegexDate($type);
        if (!$begin) {
            $begin = clone $end;
            $begin->sub($regexInfo['interval']);
        }

        $aggregationBuilder = $documentManager->createAggregationBuilder($jobTimingClass);
        $expr = $this->addJobTimingsDateInfo($type, $aggregationBuilder->expr());
        $expr = $expr->field('status')
                ->expression('$status');

        $aggregationBuilder
            ->match()
                ->field('finishedAt')
                ->gte($begin)
                ->lte($end)
            ->group()
                    ->field('id')
                    ->expression($expr)
                    ->field('value')
                    ->sum(1);

        $results = $aggregationBuilder->execute();
        $resultHash = [];
        foreach ($results as $result) {
            $key = $result['_id']['status'];
            $dateStr = $this->getAggregationResultDateStr($type, $result['_id']);
            $resultHash[$key][$dateStr] = $result['value'];
        }

        return $resultHash;
    }

    /**
     * Formats the aggregation result into the desired date string format.
     *
     * @param string $type
     *
     * @return string
     */
    protected function getAggregationResultDateStr($type, array $result)
    {
        switch ($type) {
            case 'YEAR':
                return $result['year'];
            case 'MONTH':
                return "{$result['year']}-".str_pad($result['month'], 2, '0', STR_PAD_LEFT);
            case 'DAY':
                $str = "{$result['year']}-".str_pad($result['month'], 2, '0', STR_PAD_LEFT);
                $str .= '-'.str_pad($result['day'], 2, '0', STR_PAD_LEFT);

                return $str;
            case 'HOUR':
                $str = "{$result['year']}-".str_pad($result['month'], 2, '0', STR_PAD_LEFT);
                $str .= '-'.str_pad($result['day'], 2, '0', STR_PAD_LEFT);
                $str .= ' '.str_pad($result['hour'], 2, '0', STR_PAD_LEFT);

                return $str;
            case 'MINUTE':
                $str = "{$result['year']}-".str_pad($result['month'], 2, '0', STR_PAD_LEFT);
                $str .= '-'.str_pad($result['day'], 2, '0', STR_PAD_LEFT);
                $str .= ' '.str_pad($result['hour'], 2, '0', STR_PAD_LEFT);
                $str .= ':'.str_pad($result['minute'], 2, '0', STR_PAD_LEFT);

                return $str;
            default:
                throw new \InvalidArgumentException("Invalid date format type '$type''");
        }
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
        $jobTimingManager = $this->get('dtc_queue.manager.job_timing');
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
}
