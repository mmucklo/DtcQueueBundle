<?php

namespace Dtc\QueueBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

abstract class BaseRun extends \Dtc\QueueBundle\Model\Run
{
    #[ODM\Id]
    protected $id;

    #[ODM\Field(type: 'date', nullable: true)]
    protected $startedAt;

    #[ODM\Field(type: 'date', nullable: true)]
    protected $endedAt;

    #[ODM\Field(type: 'float', nullable: true)]
    protected $elapsed;

    #[ODM\Field(type: 'int', nullable: true)]
    protected $duration; // How long to run for in seconds
    #[ODM\Field(type: 'date')]
    protected $lastHeartbeatAt;

    #[ODM\Field(type: 'int', nullable: true)]
    protected $maxCount;
    #[ODM\Field(type: 'int', nullable: true)]
    protected $processed; // Number of jobs processed
    #[ODM\Field(type: 'string', nullable: true)]
    protected $hostname;

    #[ODM\Field(type: 'int', nullable: true)]
    protected $pid;

    #[ODM\Field(type: 'int', nullable: true)]
    protected $processTimeout;

    #[ODM\Field(type: 'string', nullable: true)]
    protected $currentJobId;
}
