<?php

namespace Dtc\QueueBundle\Tests\ODM;

use Doctrine\ODM\MongoDB\Configuration;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Mapping\Driver\AttributeDriver;
use Dtc\QueueBundle\ODM\JobManager;
use Dtc\QueueBundle\ODM\JobTimingManager;
use Dtc\QueueBundle\ODM\RunManager;
use Dtc\QueueBundle\Tests\Doctrine\DoctrineJobManagerTest;

/**
 * @author David
 * This test requires local mongodb running
 */
class JobManagerTest extends DoctrineJobManagerTest
{
    public static function setUpBeforeClass(): void
    {
        if (!is_dir('/tmp/dtcqueuetest/generate/proxies')) {
            mkdir('/tmp/dtcqueuetest/generate/proxies', 0777, true);
        }

        if (!is_dir('/tmp/dtcqueuetest/generate/hydrators')) {
            mkdir('/tmp/dtcqueuetest/generate/hydrators', 0777, true);
        }

        $config = new Configuration();
        $config->setProxyDir('/tmp/dtcqueuetest/generate/proxies');
        $config->setProxyNamespace('Proxies');

        $config->setHydratorDir('/tmp/dtcqueuetest/generate/hydrators');
        $config->setHydratorNamespace('Hydrators');

        $classPath = __DIR__.'../../Document';
        $config->setMetadataDriverImpl(AttributeDriver::create($classPath));

        self::$objectManager = DocumentManager::create(new \MongoDB\Client('mongodb://'.getenv('MONGODB_HOST'), [], ['typeMap' => DocumentManager::CLIENT_TYPEMAP]), $config);

        $documentName = 'Dtc\QueueBundle\Document\Job';
        $archiveDocumentName = 'Dtc\QueueBundle\Document\JobArchive';
        $jobTimingClass = 'Dtc\QueueBundle\Document\JobTiming';
        $runClass = 'Dtc\QueueBundle\Document\Run';
        $runArchiveClass = 'Dtc\QueueBundle\Document\RunArchive';
        $sm = self::$objectManager->getSchemaManager();

        $sm->dropDocumentCollection($documentName);
        $sm->dropDocumentCollection($runClass);
        $sm->dropDocumentCollection($archiveDocumentName);
        $sm->dropDocumentCollection($runArchiveClass);
        $sm->dropDocumentCollection($jobTimingClass);
        $sm->createDocumentCollection($documentName);
        $sm->createDocumentCollection($archiveDocumentName);
        $sm->createDocumentCollection($runClass);
        $sm->createDocumentCollection($runArchiveClass);
        $sm->createDocumentCollection($jobTimingClass);
        $sm->updateDocumentIndexes($documentName);
        $sm->updateDocumentIndexes($archiveDocumentName);
        $sm->updateDocumentIndexes($runClass);
        $sm->updateDocumentIndexes($runArchiveClass);
        $sm->updateDocumentIndexes($jobTimingClass);

        self::$objectName = $documentName;
        self::$archiveObjectName = $archiveDocumentName;
        self::$runClass = $runClass;
        self::$runArchiveClass = $runArchiveClass;
        self::$jobTimingClass = $jobTimingClass;
        self::$jobManagerClass = JobManager::class;
        self::$runManagerClass = RunManager::class;
        self::$jobTimingManagerClass = JobTimingManager::class;
        parent::setUpBeforeClass();
    }

    /**
     * @param $class
     *
     * @return array|\Doctrine\ODM\MongoDB\Iterator\Iterator|int|\MongoDB\DeleteResult|\MongoDB\InsertOneResult|\MongoDB\UpdateResult|object|null
     *
     * @throws \Doctrine\ODM\MongoDB\MongoDBException
     */
    protected function runCountQuery($class)
    {
        /** @var JobManager $jobManager */
        $jobManager = self::$jobManager;

        /** @var DocumentManager $documentManager */
        $documentManager = $jobManager->getObjectManager();

        $queryBuilder = $documentManager->createQueryBuilder($class);
        if (method_exists($queryBuilder, 'count')) {
            return $queryBuilder->count()->getQuery()->execute();
        }

        return $queryBuilder->getQuery()->count();
    }
}
