<?php

namespace Dtc\QueueBundle\Tests\Beanstalkd;

use Dtc\QueueBundle\Tests\GetterSetterTrait;
use PHPUnit\Framework\TestCase;

class JobTest extends TestCase
{
    use GetterSetterTrait;

    public function testGettersSetters()
    {
        $this->runGetterSetterTests('\Dtc\QueueBundle\Beanstalkd\Job');
    }
}
