<?php

namespace Dtc\QueueBundle\Tests\ODM;

use Doctrine\Common\Annotations\AnnotationRegistry;
use Dtc\QueueBundle\Tests\Model\BaseJobManagerTest;
use Dtc\QueueBundle\Tests\FibonacciWorker;
use Dtc\QueueBundle\ODM\JobManager;
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
        AnnotationRegistry::registerFile(__DIR__.'/../../vendor/mmucklo/grid-bundle/Annotation/Grid.php');
        AnnotationRegistry::registerFile(__DIR__.'/../../vendor/mmucklo/grid-bundle/Annotation/ShowAction.php');
        AnnotationRegistry::registerFile(__DIR__.'/../../vendor/mmucklo/grid-bundle/Annotation/DeleteAction.php');
        AnnotationRegistry::registerFile(__DIR__.'/../../vendor/mmucklo/grid-bundle/Annotation/Column.php');
        AnnotationRegistry::registerFile(__DIR__.'/../../vendor/mmucklo/grid-bundle/Annotation/Action.php');

        // Set up database delete here??
        $config = new Configuration();
        $config->setProxyDir('/tmp/dtcqueuetest/generate/proxies');
        $config->setProxyNamespace('Proxies');

        $config->setHydratorDir('/tmp/dtcqueuetest/generate/hydrators');
        $config->setHydratorNamespace('Hydrators');

        $classPath = __DIR__.'../../Document';
        $config->setMetadataDriverImpl(AnnotationDriver::create($classPath));

        self::$dm = DocumentManager::create(new Connection(getenv('MONGODB_HOST')), $config);

        $documentName = 'Dtc\QueueBundle\Document\Job';
        $archiveDocumentName = 'Dtc\QueueBundle\Document\JobArchive';
        $sm = self::$dm->getSchemaManager();

        $sm->dropDocumentCollection($documentName);
        $sm->createDocumentCollection($documentName);
        $sm->updateDocumentIndexes($documentName);

        self::$jobManager = new JobManager(self::$dm, $documentName, $archiveDocumentName);
        self::$worker = new FibonacciWorker();
        self::$worker->setJobClass($documentName);

        parent::setUpBeforeClass();
    }
}
