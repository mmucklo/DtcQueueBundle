<?php

namespace Dtc\QueueBundle\Tests\Entity;

use Dtc\QueueBundle\Tests\GetterSetterTrait;
use PHPUnit\Framework\TestCase;

class JobArchiveTest extends TestCase
{
    use GetterSetterTrait;

    public function testSetArgs()
    {
        $args = [1, 2, ['something']];

        $job = new \Dtc\QueueBundle\Entity\JobArchive();

        $job->setArgs($args);
        self::assertEquals($args, $job->getArgs());
    }

    public function testGettersSetters()
    {
        $this->runGetterSetterTests('Dtc\QueueBundle\Entity\JobArchive');
    }
}
