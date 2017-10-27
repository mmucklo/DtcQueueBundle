<?php

namespace Dtc\QueueBundle\Tests;

trait RecordingTrait
{
    public $calls;
    public $returns;

    private function recordArgs($function, $args)
    {
        $this->calls[$function][] = $args;
        if (!empty($this->returns[$function])) {
            return array_pop($this->returns[$function]);
        }

        return null;
    }
}
