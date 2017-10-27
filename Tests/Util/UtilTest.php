<?php

namespace Dtc\QueueBundle\Tests\Run;

use Dtc\QueueBundle\Util\Util;
use PHPUnit\Framework\TestCase;

class UtilTest extends TestCase
{
    /**
     * @param string $varName
     * @param int    $pow
     */
    public function testValidateIntNull()
    {
        self::assertNull(Util::validateIntNull('something', null, 2));
        self::assertEquals(1, Util::validateIntNull('asdf', 1, 2));
        try {
            Util::validateIntNull('something', 'asdf', 2);
        } catch (\Exception $exception) {
            self::assertTrue(true);
        }

        $failed = false;
        try {
            Util::validateIntNull('test', '5', 2);
            $failed = true;
        } catch (\Exception $exception) {
            self::assertTrue(true);
        }
        self::assertFalse($failed);
        self::assertEquals(5, Util::validateIntNull('test', '5', 3));
        self::assertEquals(PHP_INT_MAX, Util::validateIntNull('test', PHP_INT_MAX, 64));

        try {
            Util::validateIntNull('test', PHP_INT_MAX, 31);
            $failed = true;
        } catch (\Exception $exception) {
            self::assertTrue(true);
        }
        self::assertFalse($failed);
    }
}
