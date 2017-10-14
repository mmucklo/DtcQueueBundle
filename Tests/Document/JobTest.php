<?php

namespace Dtc\QueueBundle\Tests\Document;

use Dtc\QueueBundle\Tests\GetterSetterTrait;
use PHPUnit\Framework\TestCase;

class JobTest extends TestCase
{
    use GetterSetterTrait;

    public function testSetArgs()
    {
        $args = [1, 2, ['something']];

        $job = new \Dtc\QueueBundle\Document\Job();

        $job->setArgs($args);
        self::assertEquals($args, $job->getArgs());
    }

    public function testGettersSetters()
    {
        $this->runGetterSetterTests('Dtc\QueueBundle\Document\Job');
    }
}
