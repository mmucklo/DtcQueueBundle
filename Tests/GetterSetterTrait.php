<?php

namespace Dtc\QueueBundle\Tests;

trait GetterSetterTrait
{
    public function runGetterSetterTests($testClass)
    {
        $reflection = new \ReflectionClass($testClass);
        $properties = $reflection->getProperties();
        foreach ($properties as $property) {
            $name = $property->getName();
            $getMethod = 'get'.ucfirst($name);
            $setMethod = 'set'.ucfirst($name);
            self::assertTrue($reflection->hasMethod($getMethod), $getMethod);
            self::assertTrue($reflection->hasMethod($setMethod), $setMethod);

            $obj = new $testClass();

            $parameters = $reflection->getMethod($setMethod)->getParameters();
            if ($parameters && 1 == count($parameters)) {
                $parameter = $parameters[0];
                if (!$parameter->hasType() || ('ReflectionNamedType' == get_class($parameter->getType()) &&
                        $parameter->getType()->isBuiltin() && 'array' != $parameter->getType()->getName() &&
                        'iterator' != $parameter->getType()->getName())) {
                    $someValue = 'somevalue';
                    $obj->$setMethod($someValue);
                    self::assertSame($someValue, $obj->$getMethod(), "$setMethod, $getMethod");
                }
            }
        }
    }
}
