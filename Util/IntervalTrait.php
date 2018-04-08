<?php

namespace Dtc\QueueBundle\Util;

use Dtc\QueueBundle\Exception\UnsupportedException;

trait IntervalTrait {
    /**
     * Returns the date interval based on the modifier and the duration.
     *
     * @param string $modifier
     * @param int    $duration
     *
     * @return \DateInterval
     *
     * @throws \Exception
     */
    protected function getInterval($modifier, $duration)
    {
        switch ($modifier) {
            case 'd':
                $interval = new \DateInterval("P${duration}D");
                break;
            case 'm':
                $interval = new \DateInterval("P${duration}M");
                break;
            case 'y':
                $interval = new \DateInterval("P${duration}Y");
                break;
            default:
                $interval = $this->getIntervalTime($modifier, $duration);
        }

        return $interval;
    }

    /**
     * @param string $modifier
     * @param int    $duration
     *
     * @return \DateInterval
     *
     * @throws \Exception
     */
    protected function getIntervalTime($modifier, $duration)
    {
        switch ($modifier) {
            case 'h':
                $interval = new \DateInterval("PT${duration}H");
                break;
            case 'i':
                $seconds = $duration * 60;
                $interval = new \DateInterval("PT${seconds}S");
                break;
            case 's':
                $interval = new \DateInterval("PT${duration}S");
                break;
            default:
                throw new UnsupportedException("Unknown duration modifier: $modifier");
        }

        return $interval;
    }
}