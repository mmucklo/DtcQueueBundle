<?php

namespace Dtc\QueueBundle\Beanstalkd;

use Dtc\QueueBundle\Model\MessageableJob;

class Job extends MessageableJob
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
