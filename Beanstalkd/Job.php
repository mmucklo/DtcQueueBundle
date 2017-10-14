<?php

namespace Dtc\QueueBundle\Beanstalkd;

use Dtc\QueueBundle\Model\Job as BaseJob;

class Job extends BaseJob
{
    protected $beanJob;

    protected $ttr = 3600;

    /**
     * @return mixed
     */
    public function getBeanJob()
    {
        return $this->beanJob;
    }

    /**
     * @param mixed $beanJob
     */
    public function setBeanJob($beanJob)
    {
        $this->beanJob = $beanJob;
    }

    public function getTtr()
    {
        return $this->ttr;
    }

    /**
     * @param $ttr
     */
    public function setTtr($ttr)
    {
        $this->ttr = $ttr;
    }
}
