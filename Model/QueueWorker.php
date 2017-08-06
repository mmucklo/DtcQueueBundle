<?php

namespace Dtc\QueueBundle\Model;

/**
 * Class QueueWorker.
 *
 * Placeholder for future Queue Worker that sits and drains the queue
 */
class QueueWorker
{
    protected $id;
    protected $active;
    protected $startAt;
    protected $endAt;
    protected $lastHeartbeatAt;
    protected $maxCount;
    protected $processedCount;
}
