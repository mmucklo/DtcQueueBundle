<?php

namespace Dtc\QueueBundle\Model;

use Dtc\QueueBundle\Manager\JobManagerInterface;
use Dtc\QueueBundle\Util\Util;

abstract class Worker
{
    const RESULT_SUCCESS = 0;
    const RESULT_FAILURE = 1;

    /** @var JobManagerInterface */
    private $jobManager;
    private $currentJob;

    public function setCurrentJob(BaseJob $job)
    {
        $this->currentJob = $job;
    }

    public function getCurrentJob()
    {
        return $this->currentJob;
    }

    public function setJobManager(JobManagerInterface $jobManager)
    {
        $this->jobManager = $jobManager;
    }

    /**
     * @return
     */
    public function getJobManager()
    {
        return $this->jobManager;
    }

    /**
     * @param int|null $time
     * @param bool     $batch
     * @param int|null $priority
     * @throws \Exception
     * @throws \InvalidArgumentException
     */
    public function at($time = null, $batch = false, $priority = null)
    {
        $timeU = $time;
        if (null === $time) {
            $timeU = Util::getMicrotimeStr();
            $dateTime = \DateTime::createFromFormat(
                'U.u',
                $timeU
            );
            if (!$dateTime) {
                throw new \Exception("Could not create DateTime object from $timeU");
            }
            $dateTime->setTimezone(new \DateTimeZone(date_default_timezone_get()));
        } else {
            $localeInfo = localeconv();
            $decimalPoint = isset($localeInfo['decimal_point']) ? $localeInfo['decimal_point'] : '.';
            $hasDecimalPoint = false !== strpos(strval($time), $decimalPoint);
            $hasEnDecimalPoint = '.' === $decimalPoint ? $hasDecimalPoint : strpos(strval($time), '.');
            if (!$hasEnDecimalPoint) {
                if ($hasDecimalPoint) {
                    $dateTime = \DateTime::createFromFormat(
                        'U'.$decimalPoint.'u',
                        strval($timeU)
                    );
                    if (!$dateTime) {
                        throw new \InvalidArgumentException("Could not create DateTime object from $timeU");
                    }
                    $dateTime->setTimezone(new \DateTimeZone(date_default_timezone_get()));
                } else {
                    $dateTime = \DateTime::createFromFormat(
                        'U',
                        strval($timeU)
                    );
                    if (!$dateTime) {
                        throw new \InvalidArgumentException("Could not create DateTime object from $timeU");
                    }
                    $dateTime->setTimezone(new \DateTimeZone(date_default_timezone_get()));
                }
            } else {
                $dateTime = \DateTime::createFromFormat(
                    'U.u',
                    strval($timeU)
                );
                if (!$dateTime) {
                    throw new \InvalidArgumentException("Could not create DateTime object from $timeU");
                }
                $dateTime->setTimezone(new \DateTimeZone(date_default_timezone_get()));
            }
        }

        if (!($dateTime instanceof \DateTime)) {
            throw new \InvalidArgumentException("Invalid time: $time".($timeU != $time ? " - (micro: $timeU)" : ''));
        }
        $jobClass = $this->jobManager->getJobClass();

        return new $jobClass($this, $batch, $priority, $dateTime);
    }

    /**
     * @param int      $delay    Amount of time to delay
     * @param int|null $priority
     */
    public function later($delay = 0, $priority = null)
    {
        return $this->batchOrLaterDelay($delay, false, $priority);
    }

    public function batchOrLaterDelay($delay = 0, $batch = false, $priority = null)
    {
        $timing = Util::getMicrotimeStr();
        $parts = explode('.', $timing);
        $parts[0] = strval(intval($parts[0]) + intval($delay));
        $localeInfo = localeconv();
        $decimalPoint = isset($localeInfo['decimal_point']) ? $localeInfo['decimal_point'] : '.';

        $job = $this->at(implode($decimalPoint, $parts), $batch, $priority);
        $job->setDelay($delay);

        return $job;
    }

    /**
     * @param int      $delay    Amount of time to delay
     * @param int|null $priority
     */
    public function batchLater($delay = 0, $priority = null)
    {
        return $this->batchOrLaterDelay($delay, true, $priority);
    }

    /**
     * @param int|null $time
     * @param int|null $priority
     */
    public function batchAt($time = null, $priority = null)
    {
        return $this->at($time, true, $priority);
    }

    abstract public function getName();
}
