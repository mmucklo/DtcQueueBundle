<?php

namespace Dtc\QueueBundle\Tests\Model;

use Dtc\QueueBundle\Tests\GetterSetterTrait;
use PHPUnit\Framework\TestCase;

class RunTest extends TestCase
{
    use GetterSetterTrait;

    public function testGettersSetters()
    {
        $this->runGetterSetterTests('\Dtc\QueueBundle\Model\Run');
    }
}
