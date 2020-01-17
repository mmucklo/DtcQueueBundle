<?php

namespace Dtc\QueueBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Dtc\QueueBundle\Model\JobTiming as BaseJobTiming;

/**
 * @ORM\Entity
 * @ORM\Table(name="dtc_queue_job_timing", indexes={@ORM\Index(name="job_timing_finished_at", columns={"status", "finished_at"})})
 */
class JobTiming extends BaseJobTiming
{
    /**
     * @ORM\Column(name="id", type="bigint")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @ORM\Column(name="finished_at", type="datetime")
     */
    protected $finishedAt;

    /**
     * @ORM\Column(name="status", type="integer")
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
