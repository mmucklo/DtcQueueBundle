<?php

namespace Dtc\QueueBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'dtc_queue_run')]
#[ORM\Index(columns: ['last_heartbeat_at'], name: 'run_last_heart_beat')]
class Run extends BaseRun
{
}
