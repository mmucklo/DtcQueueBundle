<?php

namespace Dtc\QueueBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="job_archive",indexes={
 *                  @ORM\Index(name="job_archive_updated_at_idx", columns={"updated_at"})})
 */
class JobArchive extends BaseJob
{
    /**
     * @ORM\Column(type="bigint")
     * @ORM\Id
     */
    protected $id;
}
