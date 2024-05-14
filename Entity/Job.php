<?php

namespace Dtc\QueueBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Note: the number of indexes was purposefully kept smaller than it could be (such as adding an expires index)
 *   This was done to keep the number of indexes reasonably minimal for insert performance considerations.
 */
#[ORM\Entity]
#[ORM\Table(name: 'dtc_queue_job')]
#[ORM\Index(columns: ['crc_hash', 'status'], name: 'job_crc_hash_idx')]
#[ORM\Index(columns: ['priority', 'when_us'], name: 'job_priority_idx')]
#[ORM\Index(columns: ['when_us'], name: 'job_when_idx')]
#[ORM\Index(columns: ['status', 'when_us'], name: 'job_status_idx')]
class Job extends BaseJob
{
    public const STATUS_ARCHIVE = 'archive';
    #[ORM\Column(name: 'id', type: 'bigint')]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    protected $id;
}
