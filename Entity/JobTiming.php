<?php

namespace Dtc\QueueBundle\Entity;

use Dtc\QueueBundle\Model\JobTiming as BaseJobTiming;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="dtc_queue_job_timing", indexes={@ORM\Index(name="job_timing_finished_at", columns={"finished_at", "status"})})
 */
class JobTiming extends BaseJobTiming
{
    /**
     * @ORM\Column(type="bigint")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @ORM\Column(type="datetime")
     */
    protected $finishedAt;

    /**
     * @ORM\Column(type="integer")
     */
    protected $status;

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param mixed $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }
}
