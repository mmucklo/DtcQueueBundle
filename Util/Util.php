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
        self::copyMethods($obj1, $obj2, $reflection1, $reflection2);
        self::copyProperties($obj1, $obj2, $reflection1, $reflection2);
    }

    private static function copyProperties($obj1, $obj2, \ReflectionObject $reflection1, \ReflectionObject $reflection2)
    {
        $publicVars = $reflection1->getProperties(\ReflectionProperty::IS_PUBLIC);
        foreach ($publicVars as $property) {
            $propertyName = $property->getName();
            if ($reflection2->hasProperty($propertyName) && $reflection2->getProperty($propertyName)->isPublic()) {
                $obj2->$propertyName = $obj1->$propertyName;
            }
        }
    }

    private static function copyMethods($obj1, $obj2, \ReflectionObject $reflection1, \ReflectionObject $reflection2)
    {
        $methods = $reflection1->getMethods(\ReflectionMethod::IS_PUBLIC);
        foreach ($methods as $method) {
            $methodName = $method->name;
            if (0 === strpos($methodName, 'get')) {
                $getMethod = $methodName;
                $setMethod = $methodName;
                $setMethod[0] = 's';
                self::copyMethod($obj1, $obj2, $getMethod, $setMethod, $reflection2);
            }
        }
    }

    private static function copyMethod($obj1, $obj2, $getMethod, $setMethod, \ReflectionObject $reflection2)
    {
        if ($reflection2->hasMethod($setMethod)) {
            $value = $obj1->$getMethod();
            if (null !== $value) {
                $obj2->$setMethod($value);
            }
        }
    }

    /**
     * @param string $varName
     * @param int    $pow
     */
    public static function validateIntNull($varName, $var, $pow)
    {
        if (null === $var) {
            return null;
        }
        if (!ctype_digit(strval($var))) {
            throw new \Exception("$varName must be an integer");
        }

        if (strval(intval($var)) !== strval($var) || $var <= 0 || $var >= pow(2, $pow)) {
            throw new \Exception("$varName must be an base 10 integer within 2^$pow");
        }

        return intval($var);
    }
}
