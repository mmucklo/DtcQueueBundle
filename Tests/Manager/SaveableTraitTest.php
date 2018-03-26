<?php

namespace Dtc\QueueBundle\Tests\Manager;

use Dtc\QueueBundle\Exception\ClassNotSubclassException;
use Dtc\QueueBundle\Exception\PriorityException;
use Dtc\QueueBundle\Manager\SaveableTrait;
use Dtc\QueueBundle\Model\Job;
use PHPUnit\Framework\TestCase;

class SaveableTraitTest extends TestCase
{
    use SaveableTrait;
    protected $maxPriority;

    public function testValidateSaveable()
    {
        $job = new Job();
        $job->setPriority(1);
        $failure = false;
        try {
            $this->validateSaveable($job);
            $failure = true;
        } catch (PriorityException $exception) {
            self::assertTrue(true);
        }

        self::assertFalse($failure);
        $job = new Job();
        try {
            $this->validateSaveable($job);
            $failure = true;
        } catch (ClassNotSubclassException $exception) {
            self::assertTrue(true);
        }
        self::assertFalse($failure);
    }
}
