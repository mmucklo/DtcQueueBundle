<?php

namespace Dtc\QueueBundle\Beanstalkd;

use Dtc\QueueBundle\Model\Job as BaseJob;

class Job extends BaseJob
{
    protected $ttr = 3600;

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
