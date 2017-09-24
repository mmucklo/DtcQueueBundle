<?php

namespace Dtc\QueueBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="run", indexes={@ORM\Index(name="run_last_heart_beat", columns={"last_heart_beat_at"})})
 */
class Run extends BaseRun
{
}
