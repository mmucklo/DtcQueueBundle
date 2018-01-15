<?php

namespace Dtc\QueueBundle\Tests\Manager;

use Dtc\QueueBundle\Exception\UnsupportedException;
use Dtc\QueueBundle\Manager\RunManager;
use Dtc\QueueBundle\Model\Run;
use PHPUnit\Framework\TestCase;

class RunManagerTest extends TestCase
{
    public function testGetRunClass()
    {
        $runManager = new RunManager(Run::class);
        self::assertEquals(Run::class, $runManager->getRunClass());
    }

    public function testSetRunClass()
    {
        $runManager = new RunManager(Run::class);
        self::assertEquals(Run::class, $runManager->getRunClass());
        $runManager->setRunClass(\Dtc\QueueBundle\Entity\Run::class);
        self::assertEquals(\Dtc\QueueBundle\Entity\Run::class, $runManager->getRunClass());
    }

    public function testPruneArchiveRuns()
    {
        $runManager = new RunManager(Run::class);

        $failure = false;
        try {
            $runManager->pruneArchivedRuns(new \DateTime());
            $failure = true;
        } catch (UnsupportedException $exception) {
            self::assertNotNull($exception);
        }
        self::assertFalse($failure);
    }

    public function testPruneStalledRuns()
    {
        $runManager = new RunManager(Run::class);

        $failure = false;
        try {
            $runManager->pruneStalledRuns();
            $failure = true;
        } catch (UnsupportedException $exception) {
            self::assertNotNull($exception);
        }
        self::assertFalse($failure);
    }
}
