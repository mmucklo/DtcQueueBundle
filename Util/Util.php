<?php

namespace Dtc\QueueBundle\Util;

class Util
{
    /**
     * Copies the members of obj1 that have public getters to obj2 if there exists a public setter in obj2 of the same suffix, it will also copy public variables.
     *
     * @param $obj1
     * @param $obj2
     */
    public static function copy($obj1, $obj2)
    {
        if (!is_object($obj1)) {
            throw new \Exception('$obj1 must be an object, not '.gettype($obj1));
        }
        if (!is_object($obj2)) {
            throw new \Exception('$obj2 must be an object, not '.gettype($obj2));
        }
        $reflection1 = new \ReflectionObject($obj1);
        $reflection2 = new \ReflectionObject($obj2);

        $methods = $reflection1->getMethods(\ReflectionMethod::IS_PUBLIC);
        $publicVars = $reflection1->getProperties(\ReflectionProperty::IS_PUBLIC);
        foreach ($methods as $method) {
            $methodName = $method->getName();
            if (0 === strpos($methodName, 'get')) {
                $getMethod = $methodName;
                $setMethod = $methodName;
                $setMethod[0] = 's';
                if ($reflection2->hasMethod($setMethod)) {
                    $value = $obj1->$getMethod();
                    if (null !== $value) {
                        $obj2->$setMethod($value);
                    }
                }
            }
        }
        foreach ($publicVars as $property) {
            $propertyName = $property->getName();
            if ($reflection2->hasPropery($propertyName) && $reflection2->getProperty($propertyName)->isPublic()) {
                $obj2->$propertyName = $obj1->$propertyName;
            }
        }
    }
}
