<?php

namespace Dtc\QueueBundle\Redis;

use Dtc\QueueBundle\Model\BaseJob;

trait StatusTrait
{
    protected function collateStatusResults(array &$results, $cacheKey)
    {
        $cursor = null;
        while ($jobs = $this->redis->zScan($cacheKey, $cursor, '', 100)) {
            $jobs = $this->redis->mget(array_map(function ($item) {
                return $this->getJobCacheKey($item);
            }, array_keys($jobs)));
            $this->extractStatusResults($jobs, $results);
            if (0 === $cursor) {
                break;
            }
        }

        return $results;
    }

    protected function extractStatusResults(array $jobs, array &$results)
    {
        foreach ($jobs as $jobMessage) {
            if (is_string($jobMessage)) {
                $job = new Job();
                $job->fromMessage($jobMessage);
                $resultHashKey = $job->getWorkerName().'->'.$job->getMethod().'()';
                if (!isset($results[$resultHashKey][BaseJob::STATUS_NEW])) {
                    $results[$resultHashKey] = static::getAllStatuses();
                }
                if (!isset($results[$resultHashKey][BaseJob::STATUS_NEW])) {
                    $results[$resultHashKey][BaseJob::STATUS_NEW] = 0;
                }
                ++$results[$resultHashKey][BaseJob::STATUS_NEW];
            }
        }
    }

    protected function extractStatusHashResults(array $hResults, array &$results)
    {
        foreach ($hResults as $key => $value) {
            list($workerName, $method, $status) = explode(',', $key);
            $resultHashKey = $workerName.'->'.$method.'()';
            if (!isset($results[$resultHashKey])) {
                $results[$resultHashKey] = static::getAllStatuses();
            }
            if (!isset($results[$resultHashKey][$status])) {
                $results[$resultHashKey][$status] = 0;
            }
            $results[$resultHashKey][$status] += $value;
        }
    }
}
