<?php
namespace Dtc\QueueBundle\BeanStalkd;

use Dtc\QueueBundle\Model\Job as BaseJob;

class Job
    extends BaseJob
{
    protected $ttr;

    public function toMessage() {
        $arr = array(
                'worker' => $this->getWorkerName(),
                'args' => $this->getArgs(),
                'method' => $this->getMethod()
        );

        return json_encode($arr);
    }

    public function fromMessage($message) {
        $arr = json_decode($message, true);
        $this->setWorkerName($arr['worker']);
        $this->setArgs($arr['args']);
        $this->setMessage($arr['method']);
    }

    public function getTtr()
    {
        return $this->ttr;
    }

    /**
     * @param field_type $ttr
     */
    public function setTtr($ttr)
    {
        $this->ttr = $ttr;
    }
}
