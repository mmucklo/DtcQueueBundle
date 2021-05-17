<?php

namespace Dtc\QueueBundle\Tests\ORM;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityManager;
use Dtc\QueueBundle\Entity\RunArchive;
use Dtc\QueueBundle\ORM\RunManager;
use PHPUnit\Framework\TestCase;
use ProxyManager\Factory\LazyLoadingValueHolderFactory;

class RunManagerTest extends TestCase
{
    protected static $runManager;

    public static function setUpBeforeClass(): void
    {
        JobManagerTest::setUpBeforeClass();
        $jobManager = JobManagerTest::$jobManager;
        $runClass = \Dtc\QueueBundle\Entity\Run::class;
        $runArchiveClass = \Dtc\QueueBundle\Entity\RunArchive::class;
        $runManager = new \Dtc\QueueBundle\ORM\RunManager($jobManager->getObjectManager(), $runClass, RunArchive::class);
        self::$runManager = $runManager;
    }

    public function testPruneStaleRuns()
    {
        /** @var RunManager $runManager */
        $runManager = self::$runManager;
        $runClass = $runManager->getRunClass();
        $objectManager = $runManager->getObjectManager();
        $runRepository = $objectManager->getRepository($runClass);
        self::assertEmpty($runRepository->findAll());
        $runArchiveRepository = $objectManager->getRepository($runManager->getRunArchiveClass());
        self::assertEmpty($runArchiveRepository->findAll());

        $run = new $runClass();
        $time = time() - 96400;
        $date = new \DateTime("@$time");

        $run->setStartedAt($date);
        $run->setLastHeartbeatAt($date);
        $objectManager->persist($run);
        $objectManager->flush($run);
        self::assertCount(1, $runRepository->findAll());

        $count = $runManager->pruneStalledRuns();
        self::assertEquals(1, $count);
        self::assertEmpty($runRepository->findAll());
        $count = $runManager->pruneStalledRuns();
        self::assertEquals(0, $count);
    }

    public function testCloseEm()
    {
        /** @var RunManager $runManager */
        $runManager = self::$runManager;

        $factory = new LazyLoadingValueHolderFactory();
        /** @var ContainerInterface $container */
        $container = new ContainerExtended();
        $objectManager = $runManager->getObjectManager();
        $container->set(
            'doctrine.orm.default_entity_manager',
            $factory->createProxy(
                EntityManager::class,
                function (&$wrappedObject, $proxy, $method, $parameters, &$initializer) use ($objectManager) {
                    $wrappedObject = $objectManager;
                    $initializer = null;
                }
            )
        );
        $registry = new Registry($container, [], ['default' => 'doctrine.orm.default_entity_manager'], 'default', 'default');
        $runManager->setRegistry($registry);
        $runManager->setEntityManagerName('default');

        $run = $runManager->runStart($start = microtime(true));
        $runManager->getObjectManager()->close();
        $runManager->runStop($run, $start);
    }

    public function testCloseEm2()
    {
        /** @var RunManager $runManager */
        $runManager = self::$runManager;

        $factory = new LazyLoadingValueHolderFactory();
        /** @var ContainerInterface $container */
        $container = new ContainerExtended();
        $objectManager = $runManager->getObjectManager();
        $container->set(
            'doctrine.orm.default_entity_manager',
            $factory->createProxy(
                EntityManager::class,
                function (&$wrappedObject, $proxy, $method, $parameters, &$initializer) use ($objectManager) {
                    $wrappedObject = $objectManager;
                    $initializer = null;
                }
            )
        );
        $registry = new Registry($container, [], ['default' => 'doctrine.orm.default_entity_manager'], 'default', 'default');
        $runManager->setRegistry($registry);
        $runManager->setEntityManagerName('default');

        $run = $runManager->runStart($start = microtime(true));
        $runManager->getObjectManager()->close();
        $registry->resetManager('default');
        $runManager->runStop($run, $start);
    }
}
