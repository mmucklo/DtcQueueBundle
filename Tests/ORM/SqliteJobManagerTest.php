<?php

namespace Dtc\QueueBundle\Tests\ORM;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Dtc\QueueBundle\ORM\JobManager;
use Dtc\QueueBundle\ORM\JobTimingManager;
use Dtc\QueueBundle\ORM\RunManager;
use Dtc\QueueBundle\Tests\Doctrine\DoctrineJobManagerTest;

/**
 * Tests ORM with SQLite (in-memory). This exercises the optimistic fallback path
 * since SQLite does not support SELECT ... FOR UPDATE SKIP LOCKED.
 */
class SqliteJobManagerTest extends DoctrineJobManagerTest
{
    public static function createObjectManager()
    {
        if (!is_dir('/tmp/dtcqueuetest/generate/proxies')) {
            mkdir('/tmp/dtcqueuetest/generate/proxies', 0777, true);
        }

        $config = ORMSetup::createAttributeMetadataConfiguration([__DIR__.'/../..'], true);
        if (PHP_VERSION_ID >= 80400 && method_exists($config, 'enableNativeLazyObjects')) {
            $config->enableNativeLazyObjects(true);
        }

        $params = [
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ];

        $connection = DriverManager::getConnection($params, $config);
        self::$objectManager = new EntityManager($connection, $config);

        // SQLite in-memory databases are per-connection, so recreate schema each time
        self::createSchema();
    }

    private static function createSchema()
    {
        /** @var EntityManager $objectManager */
        $objectManager = self::$objectManager;
        $tool = new SchemaTool($objectManager);

        $classes = [
            $objectManager->getClassMetadata('Dtc\QueueBundle\Entity\Job'),
            $objectManager->getClassMetadata('Dtc\QueueBundle\Entity\JobArchive'),
            $objectManager->getClassMetadata('Dtc\QueueBundle\Entity\Run'),
            $objectManager->getClassMetadata('Dtc\QueueBundle\Entity\RunArchive'),
            $objectManager->getClassMetadata('Dtc\QueueBundle\Entity\JobTiming'),
        ];
        $tool->createSchema($classes);
    }

    public static function setUpBeforeClass(): void
    {
        self::createObjectManager();

        self::$objectName = 'Dtc\QueueBundle\Entity\Job';
        self::$archiveObjectName = 'Dtc\QueueBundle\Entity\JobArchive';
        self::$runClass = 'Dtc\QueueBundle\Entity\Run';
        self::$runArchiveClass = 'Dtc\QueueBundle\Entity\RunArchive';
        self::$jobTimingClass = 'Dtc\QueueBundle\Entity\JobTiming';
        self::$jobManagerClass = JobManager::class;
        self::$runManagerClass = RunManager::class;
        self::$jobTimingManagerClass = JobTimingManager::class;
        parent::setUpBeforeClass();
    }

    protected function runCountQuery($class)
    {
        /** @var JobManager $jobManager */
        $jobManager = self::$jobManager;

        /** @var EntityManager $entityManager */
        $entityManager = $jobManager->getObjectManager();

        return $entityManager->createQueryBuilder()->select('count(j.id)')->from($class, 'j')->getQuery()->getSingleScalarResult();
    }
}
