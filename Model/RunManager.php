<?php

namespace Dtc\QueueBundle\Model;

class RunManager
{
    /** @var string|null */
    protected $runClass;

    public function __construct($runClass)
    {
        $this->runClass = $runClass;
    }

    /**
     * @return null|string
     */
    public function getRunClass()
    {
        return $this->runClass;
    }

    /**
     * @param null|string $runClass
     */
    public function setRunClass($runClass)
    {
        $this->runClass = $runClass;
    }

    /**
     * @param \DateTime $olderThan
     *
     * @return int Number of archived runs pruned
     */
    public function pruneArchivedRuns(\DateTime $olderThan)
    {
        throw new \Exception('not supported');
    }
}
