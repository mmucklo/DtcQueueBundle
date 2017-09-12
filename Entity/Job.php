<?php

namespace Dtc\QueueBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="job", indexes={@ORM\Index(name="job_crc_hash_idx", columns={"crc_hash","status"}),
 *                  @ORM\Index(name="job_priority_idx", columns={"priority","when_at"}),
 *                  @ORM\Index(name="job_when_idx", columns={"when_at","locked"}),
 *                  @ORM\Index(name="job_status_idx", columns={"status","locked","when_at"})})
 */
class Job extends BaseJob
{
    /**
     * @ORM\Column(type="bigint")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;
}
