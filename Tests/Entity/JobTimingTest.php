<?php

namespace Dtc\QueueBundle\Tests\Entity;

use Dtc\QueueBundle\Tests\GetterSetterTrait;
use PHPUnit\Framework\TestCase;

class JobTimingTest extends TestCase
{
    use GetterSetterTrait;

    public function testGettersSetters()
    {
        $this->runGetterSetterTests('Dtc\QueueBundle\Entity\JobTiming');
    }
}
