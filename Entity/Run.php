<?php

namespace Dtc\QueueBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Dtc\GridBundle\Annotation as Grid;

/**
 * @ORM\Entity
 * @ORM\Table(name="dtc_queue_run", indexes={@ORM\Index(name="run_last_heart_beat", columns={"last_heartbeat_at"})})
 * @Grid\Grid(actions={@Grid\ShowAction(), @Grid\DeleteAction()},sort=@Grid\Sort(column="startedAt",direction="DESC"))
 */
class Run extends BaseRun
{
}
