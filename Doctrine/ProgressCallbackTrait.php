<?php

namespace Dtc\QueueBundle\Doctrine;

trait ProgressCallbackTrait
{
    protected function updateProgress($callback, $count, $totalCount = null)
    {
        if (is_callable($callback)) {
            call_user_func($callback, $count, $totalCount);
        }
    }
}
