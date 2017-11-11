<?php

namespace Dtc\QueueBundle\Tests\Doctrine;

use Dtc\QueueBundle\Tests\GetterSetterTrait;

trait JobTestTrait
{
    use GetterSetterTrait;

    public function testSetArgs()
    {
        $args = [1, 2, ['something']];

        $className = $this->getClassName();
        $job = new $className();

        $job->setArgs($args);
        self::assertEquals($args, $job->getArgs());
    }

    private function getClassName()
    {
        $className = get_class($this);
        $parts = explode('\\', $className);
        $len = count($parts);

        return 'Dtc\QueueBundle\\'.$parts[$len - 2].'\\'.str_replace('Test', '', $parts[$len - 1]);
    }

    public function testGettersSetters()
    {
        $this->runGetterSetterTests($this->getClassName());
    }
}
