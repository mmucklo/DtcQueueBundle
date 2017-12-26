<?php

namespace Dtc\QueueBundle\Model;

class JobTiming
{
    const STATUS_INSERT = 0;
    const STATUS_INSERT_DELAYED = 1;
    const STATUS_FINISHED_SUCCESS = 100;
    const STATUS_FINISHED_EXCEPTION = 101;
    const STATUS_FINISHED_EXPIRED = 102;
    const STATUS_FINISHED_STALLED = 103;
    const STATUS_FINISHED_FAILURE = 104;

    protected $finishedAt;
    protected $status;

    /**
     * A list of all the states and descriptions for them.
     *
     * @return array
     */
    public static function getStates()
    {
        return [self::STATUS_FINISHED_SUCCESS => ['label' => 'Finished: SUCCESS', 'color' => 'green'],
            self::STATUS_FINISHED_FAILURE => ['label' => 'Finished: EXCEPTION', 'color' => 'orange'],
            self::STATUS_FINISHED_EXCEPTION => ['label' => 'Finished: FAILURE', 'color' => 'red'],
            self::STATUS_FINISHED_EXPIRED => ['label' => 'Finished: EXPIRED', 'color' => 'maroon'],
            self::STATUS_FINISHED_STALLED => ['label' => 'Finished: STALLED', 'color' => 'gold'],
            self::STATUS_INSERT => ['label' => 'INSERT', 'color' => 'navy'],
            self::STATUS_INSERT_DELAYED => ['label' => 'INSERT (Delayed)', 'color' => 'purple'], ];
    }

    /**
     * @return mixed
     */
    public function getFinishedAt()
    {
        return $this->finishedAt;
    }

    /**
     * @param mixed $finishedAt
     */
    public function setFinishedAt($finishedAt)
    {
        $this->finishedAt = $finishedAt;
    }

    /**
     * @return int
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @param int $status
     */
    public function setStatus($status)
    {
        $this->status = $status;
    }
}
