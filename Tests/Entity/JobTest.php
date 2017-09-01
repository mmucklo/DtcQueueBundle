<?php

namespace Dtc\QueueBundle\Tests\Entity;

use Dtc\QueueBundle\Model\Job;
use PHPUnit\Framework\TestCase;

class JobTest extends TestCase
{
    public function testSetArgs()
    {
        $args = [1, 2, ['something']];

        $job = new \Dtc\QueueBundle\Entity\Job();

        $job->setArgs($args);
        self::assertEquals($args, $job->getArgs());
    }

    public function testGettersSetters()
    {
        $reflection = new \ReflectionClass('\Dtc\QueueBundle\Entity\Job');
        $properties = $reflection->getProperties();
        foreach ($properties as $property) {
            $name = $property->getName();
            $getMethod = 'get'.ucfirst($name);
            $setMethod = 'set'.ucfirst($name);
            self::assertTrue($reflection->hasMethod($getMethod), $getMethod);
            self::assertTrue($reflection->hasMethod($setMethod), $setMethod);

            $job = new Job();

            $parameters = $reflection->getMethod($setMethod)->getParameters();
            if ($parameters && count($parameters) == 1) {
                $parameter = $parameters[0];
                if (!$parameter->getClass()) {
                    $someValue = 'somevalue';
                    $job->$setMethod($someValue);
                    self::assertSame($someValue, $job->$getMethod(), "$setMethod, $getMethod");
                }
            }
        }
    }
}
