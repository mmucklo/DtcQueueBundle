<?php

namespace Dtc\QueueBundle\Tests\ODM;

use Doctrine\Common\Annotations\AnnotationRegistry;
use Dtc\QueueBundle\Tests\Doctrine\BaseJobManagerTest;
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
    public static function setUpBeforeClass()
    {
        if (!is_dir('/tmp/dtcqueuetest/generate/proxies')) {
            mkdir('/tmp/dtcqueuetest/generate/proxies', 0777, true);
        }

        if (!is_dir('/tmp/dtcqueuetest/generate/hydrators')) {
            mkdir('/tmp/dtcqueuetest/generate/hydrators', 0777, true);
        }

        AnnotationRegistry::registerFile(__DIR__.'/../../vendor/doctrine/mongodb-odm/lib/Doctrine/ODM/MongoDB/Mapping/Annotations/Document.php');
        AnnotationRegistry::registerFile(__DIR__.'/../../vendor/doctrine/mongodb-odm/lib/Doctrine/ODM/MongoDB/Mapping/Annotations/Id.php');
        AnnotationRegistry::registerFile(__DIR__.'/../../vendor/doctrine/mongodb-odm/lib/Doctrine/ODM/MongoDB/Mapping/Annotations/Field.php');
        AnnotationRegistry::registerFile(__DIR__.'/../../vendor/doctrine/mongodb-odm/lib/Doctrine/ODM/MongoDB/Mapping/Annotations/Index.php');
        AnnotationRegistry::registerFile(__DIR__.'/../../vendor/doctrine/mongodb-odm/lib/Doctrine/ODM/MongoDB/Mapping/Annotations/AlsoLoad.php');
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

        self::$objectManager = DocumentManager::create(new Connection(getenv('MONGODB_HOST')), $config);

        $documentName = 'Dtc\QueueBundle\Document\Job';
        $archiveDocumentName = 'Dtc\QueueBundle\Document\JobArchive';
        $runClass = 'Dtc\QueueBundle\Document\Run';
        $runArchiveClass = 'Dtc\QueueBundle\Document\RunArchive';
        $sm = self::$objectManager->getSchemaManager();

        $sm->dropDocumentCollection($documentName);
        $sm->dropDocumentCollection($runClass);
        $sm->dropDocumentCollection($archiveDocumentName);
        $sm->dropDocumentCollection($runArchiveClass);
        $sm->createDocumentCollection($documentName);
        $sm->createDocumentCollection($archiveDocumentName);
        $sm->updateDocumentIndexes($documentName);
        $sm->updateDocumentIndexes($archiveDocumentName);

        self::$objectName = $documentName;
        self::$archiveObjectName = $archiveDocumentName;
        self::$runClass = $runClass;
        self::$runArchiveClass = $runArchiveClass;
        self::$jobManagerClass = JobManager::class;
        parent::setUpBeforeClass();
    }
}
