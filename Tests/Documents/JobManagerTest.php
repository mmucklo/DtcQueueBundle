<?php

namespace Dtc\QueueBundle\Tests\Documents;

use Dtc\QueueBundle\Tests\Model\BaseJobManagerTest;
use Dtc\QueueBundle\Tests\FibonacciWorker;
use Dtc\QueueBundle\Documents\JobManager;
use Doctrine\MongoDB\Connection;
use Doctrine\ODM\MongoDB\Configuration;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Mapping\Driver\AnnotationDriver;

/**
 * @author David
 *
 * This test requires local mongodb running
 */
class JobManagerTest extends BaseJobManagerTest
{
    public static $dm;

    public static function setUpBeforeClass()
    {
        if (!is_dir('/tmp/dtcqueuetest/generate/proxies')) {
            mkdir('/tmp/dtcqueuetest/generate/proxies', 0777, true);
        }

        if (!is_dir('/tmp/dtcqueuetest/generate/hydrators')) {
            mkdir('/tmp/dtcqueuetest/generate/hydrators', 0777, true);
        }
        AnnotationDriver::registerAnnotationClasses();

        // Set up database delete here??
        $config = new Configuration();
        $config->setProxyDir('/tmp/dtcqueuetest/generate/proxies');
        $config->setProxyNamespace('Proxies');

        $config->setHydratorDir('/tmp/dtcqueuetest/generate/hydrators');
        $config->setHydratorNamespace('Hydrators');

        $classPath = __DIR__.'../../Documents';
        $config->setMetadataDriverImpl(AnnotationDriver::create($classPath));

        self::$dm = DocumentManager::create(new Connection(getenv('MONGODB_HOST')), $config);

        $documentName = 'Dtc\QueueBundle\Documents\Job';
        $sm = self::$dm->getSchemaManager();
        $timeout = 1000;

        $sm->dropDocumentCollection($documentName);
        $sm->createDocumentCollection($documentName);
        $sm->updateDocumentIndexes($documentName);

        self::$jobManager = new JobManager(self::$dm, $documentName);
        self::$worker = new FibonacciWorker();
        self::$worker->setJobClass($documentName);

        parent::setUpBeforeClass();
    }
}
