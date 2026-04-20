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
 * This test requires local PostgreSQL running.
 */
class PostgresJobManagerTest extends DoctrineJobManagerTest
{
    public static function createObjectManager()
    {
        if (!extension_loaded('pdo_pgsql')) {
            return;
        }
        if (!is_dir('/tmp/dtcqueuetest/generate/proxies')) {
            mkdir('/tmp/dtcqueuetest/generate/proxies', 0777, true);
        }

        $config = ORMSetup::createAttributeMetadataConfiguration([__DIR__.'/../..'], true);
        if (PHP_VERSION_ID >= 80400 && method_exists($config, 'enableNativeLazyObjects')) {
            $config->enableNativeLazyObjects(true);
        }

        $host = getenv('POSTGRES_HOST');
        $user = getenv('POSTGRES_USER') ?: 'root';
        $port = (int) (getenv('POSTGRES_PORT') ?: 5432);
        $password = getenv('POSTGRES_PASSWORD') ?: '';
        $db = getenv('POSTGRES_DATABASE') ?: 'queue_test';
        $params = [
            'host' => $host,
            'port' => $port,
            'user' => $user,
            'driver' => 'pdo_pgsql',
            'password' => $password,
            'dbname' => $db,
        ];

        $connection = DriverManager::getConnection($params, $config);
        self::$objectManager = new EntityManager($connection, $config);
    }

    public static function setUpBeforeClass(): void
    {
        if (!extension_loaded('pdo_pgsql') || !getenv('POSTGRES_HOST')) {
            return;
        }
        self::createObjectManager();
        $entityName = 'Dtc\QueueBundle\Entity\Job';
        $archiveEntityName = 'Dtc\QueueBundle\Entity\JobArchive';
        $runClass = 'Dtc\QueueBundle\Entity\Run';
        $runArchiveClass = 'Dtc\QueueBundle\Entity\RunArchive';
        $jobTimingClass = 'Dtc\QueueBundle\Entity\JobTiming';

        /** @var EntityManager $objectManager */
        $objectManager = self::$objectManager;
        $tool = new SchemaTool($objectManager);
        $metadataEntity = [$objectManager->getClassMetadata($entityName)];
        $tool->dropSchema($metadataEntity);
        $tool->createSchema($metadataEntity);

        $metadataEntityArchive = [$objectManager->getClassMetadata($archiveEntityName)];
        $tool->dropSchema($metadataEntityArchive);
        $tool->createSchema($metadataEntityArchive);

        $metadataEntityRun = [$objectManager->getClassMetadata($runClass)];
        $tool->dropSchema($metadataEntityRun);
        $tool->createSchema($metadataEntityRun);

        $metadataEntityRunArchive = [$objectManager->getClassMetadata($runArchiveClass)];
        $tool->dropSchema($metadataEntityRunArchive);
        $tool->createSchema($metadataEntityRunArchive);

        $metadataJobTiming = [$objectManager->getClassMetadata($jobTimingClass)];
        $tool->dropSchema($metadataJobTiming);
        $tool->createSchema($metadataJobTiming);

        self::$objectName = $entityName;
        self::$archiveObjectName = $archiveEntityName;
        self::$runClass = $runClass;
        self::$runArchiveClass = $runArchiveClass;
        self::$jobTimingClass = $jobTimingClass;
        self::$jobManagerClass = JobManager::class;
        self::$runManagerClass = RunManager::class;
        self::$jobTimingManagerClass = JobTimingManager::class;
        parent::setUpBeforeClass();
    }

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_pgsql') || !getenv('POSTGRES_HOST')) {
            $this->markTestSkipped('pdo_pgsql extension or POSTGRES_HOST not available');
        }
        parent::setUp();
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
