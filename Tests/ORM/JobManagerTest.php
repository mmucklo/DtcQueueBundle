<?php

namespace Dtc\QueueBundle\Tests\ORM;

use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\Setup;
use Dtc\QueueBundle\Tests\Model\BaseJobManagerTest;
use Dtc\QueueBundle\Tests\FibonacciWorker;
use Dtc\QueueBundle\ORM\JobManager;
use Doctrine\ORM\EntityManager;

/**
 * @author David
 *
 * This test requires local mongodb running
 */
class JobManagerTest extends BaseJobManagerTest
{
    public static $entityManager;

    public static function setUpBeforeClass()
    {
        if (!is_dir('/tmp/dtcqueuetest/generate/proxies')) {
            mkdir('/tmp/dtcqueuetest/generate/proxies', 0777, true);
        }

        $config = Setup::createAnnotationMetadataConfiguration(array(__DIR__.'/../..'), true, null, null, false);

        $namingStrategy = new UnderscoreNamingStrategy();
        $config->setNamingStrategy($namingStrategy);
        $host = getenv('MYSQL_HOST');
        $user = getenv('MYSQL_USER');
        $port = getenv('MYSQL_PORT') ?: 3306;
        $password = getenv('MYSQL_PASSWORD');
        $db = getenv('MYSQL_DATABASE');
        $params = ['host' => $host,
                    'port' => $port,
                    'user' => $user,
                    'driver' => 'mysqli',
                    'password' => $password,
                    'dbname' => $db, ];
        self::$entityManager = EntityManager::create($params, $config);

        $entityName = 'Dtc\QueueBundle\Entity\Job';
        $archiveEntityName = 'Dtc\QueueBundle\Entity\JobArchive';
        $tool = new SchemaTool(self::$entityManager);
        $metadataEntity = [self::$entityManager->getClassMetadata($entityName)];
        $tool->dropSchema($metadataEntity);
        $tool->createSchema($metadataEntity);

        $metadataEntityArchive = [self::$entityManager->getClassMetadata($archiveEntityName)];
        $tool->dropSchema($metadataEntityArchive);
        $tool->createSchema($metadataEntityArchive);

        self::$jobManager = new JobManager(self::$entityManager, $entityName, $archiveEntityName);
        self::$worker = new FibonacciWorker();
        self::$worker->setJobClass($entityName);
        parent::setUpBeforeClass();
    }
}
