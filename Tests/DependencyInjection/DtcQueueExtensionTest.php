<?php

namespace Dtc\QueueBundle\Tests\DependencyInjection;

use Dtc\QueueBundle\DependencyInjection\DtcQueueExtension;
use Dtc\QueueBundle\Manager\PriorityJobManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class DtcQueueExtensionTest extends TestCase
{
    public function testDtcQueueExtension()
    {
        $dtcQueueExtension = new DtcQueueExtension();
        $configs = [];
        $containerBuilder = new ContainerBuilder();
        $dtcQueueExtension->load($configs, $containerBuilder);

        self::assertEquals('dtc_queue', $dtcQueueExtension->getAlias());
    }

    protected function tryBadConfigs(array $configs)
    {
        return $this->tryConfigs($configs, false);
    }

    public function testBeanstalkExtension()
    {
        $configs = ['config' => ['beanstalkd' => ['host' => null]]];
        $this->tryBadConfigs($configs);
        $configs = ['config' => ['beanstalkd' => ['tube' => null]]];
        $this->tryBadConfigs($configs);

        $configs = ['config' => ['beanstalkd' => ['host' => 'somehost']]];
        $containerBuilder = $this->tryConfigs($configs);
        self::assertEquals('somehost', $containerBuilder->getParameter('dtc_queue.beanstalkd.host'));

        $configs = ['config' => ['beanstalkd' => ['host' => 'somehost', 'tube' => 'something']]];
        $containerBuilder = $this->tryConfigs($configs);

        self::assertEquals('something', $containerBuilder->getParameter('dtc_queue.beanstalkd.tube'));
        self::assertEquals('somehost', $containerBuilder->getParameter('dtc_queue.beanstalkd.host'));
    }

    public function testPriority()
    {
        $configs = [];
        $containerBuilder = $this->tryConfigs($configs);
        self::assertEquals(255, $containerBuilder->getParameter('dtc_queue.priority.max'));
        self::assertEquals(PriorityJobManager::PRIORITY_DESC, $containerBuilder->getParameter('dtc_queue.priority.direction'));
        $configs = ['config' => ['priority' => ['max' => 200]]];
        $containerBuilder = $this->tryConfigs($configs);
        self::assertEquals(200, $containerBuilder->getParameter('dtc_queue.priority.max'));
        $configs = ['config' => ['priority' => ['max' => null]]];
        $this->tryBadConfigs($configs);
        $configs = ['config' => ['priority' => ['max' => 0]]];
        $this->tryBadConfigs($configs);
        $configs = ['config' => ['priority' => ['direction' => 'asdf']]];
        $this->tryBadConfigs($configs);

        $configs = ['config' => ['priority' => ['direction' => PriorityJobManager::PRIORITY_ASC]]];
        $containerBuilder = $this->tryConfigs($configs);
        self::assertEquals(PriorityJobManager::PRIORITY_ASC, $containerBuilder->getParameter('dtc_queue.priority.direction'));
    }

    protected function tryConfigs(array $configs, $good = true)
    {
        $dtcQueueExtension = new DtcQueueExtension();
        $containerBuilder = new ContainerBuilder();

        $failed = false;
        try {
            $dtcQueueExtension->load($configs, $containerBuilder);
            if (!$good) {
                $failed = true;
            }
        } catch (\Exception $exception) {
            if ($good) {
                self::fail($exception->getMessage());
            }
        }
        self::assertFalse($failed);

        return $containerBuilder;
    }

    public function testLocaleFix()
    {
        $configs = ['config' => ['locale_fix' => true]];
        $containerBuilder = $this->tryConfigs($configs);
        self::assertTrue($containerBuilder->getParameter('dtc_queue.locale_fix'));

        $configs = ['config' => []];
        $containerBuilder = $this->tryConfigs($configs);
        self::assertFalse($containerBuilder->getParameter('dtc_queue.locale_fix'));
    }

    public function testSncRedis()
    {
        $configs = ['config' => ['redis' => ['snc_redis' => ['type' => 'asdf']]]];
        $this->tryBadConfigs($configs);

        $configs = ['config' => ['redis' => ['snc_redis' => ['type' => 'predis']]]];
        $this->tryBadConfigs($configs);

        $configs = ['config' => ['redis' => ['snc_redis' => ['alias' => 'asdf']]]];
        $this->tryBadConfigs($configs);

        $configs = ['config' => ['redis' => ['snc_redis' => ['alias' => 'default', 'type' => 'predis']]]];
        $containerBuilder = $this->tryConfigs($configs);
        self::assertEquals('default', $containerBuilder->getParameter('dtc_queue.redis.snc_redis.alias'));
        self::assertEquals('predis', $containerBuilder->getParameter('dtc_queue.redis.snc_redis.type'));
    }

    public function testPredis()
    {
        $configs = ['config' => ['redis' => ['predis' => ['dsn' => 'redis://localhost']]]];
        $containerBuilder = $this->tryConfigs($configs);
        self::assertEquals('redis://localhost', $containerBuilder->getParameter('dtc_queue.redis.predis.dsn'));
        self::assertFalse($containerBuilder->hasParameter('dtc_queue.redis.snc_redis.alias'));

        $configs = [
            'config' => [
                'redis' => [
                    'predis' => [
                        'connection_parameters' => [
                            'host' => 'localhost',
                            'port' => 6379,
                        ],
                    ],
                ],
            ],
        ];
        $containerBuilder = $this->tryConfigs($configs);
        $this->arrayTest($containerBuilder, 'dtc_queue.redis.predis.connection_parameters', 'host', 'localhost');
        $this->arrayTest($containerBuilder, 'dtc_queue.redis.predis.connection_parameters', 'port', 6379);
        $this->arrayTest($containerBuilder, 'dtc_queue.redis.predis.connection_parameters', 'timeout', 5.0);
        $this->arrayTest($containerBuilder, 'dtc_queue.redis.predis.connection_parameters', 'scheme', 'tcp');
    }

    public function testPhpRedis()
    {
        $configs = ['config' => ['redis' => ['phpredis' => ['host' => 'localhost', 'port' => 6379]]]];
        $containerBuilder = $this->tryConfigs($configs);
        $this->assertEquals('localhost', $containerBuilder->getParameter('dtc_queue.redis.phpredis.host'));
        $this->assertEquals(6379, $containerBuilder->getParameter('dtc_queue.redis.phpredis.port'));
        $this->assertEquals(0, $containerBuilder->getParameter('dtc_queue.redis.phpredis.timeout'));
        $this->assertEquals(0, $containerBuilder->getParameter('dtc_queue.redis.phpredis.read_timeout'));
        $this->assertEquals(null, $containerBuilder->getParameter('dtc_queue.redis.phpredis.retry_interval'));
        $this->assertFalse($containerBuilder->hasParameter('dtc_queue.redis.phpredis.auth'));

        $configs = ['config' => ['redis' => ['phpredis' => ['host' => 'localhost', 'port' => 6379, 'read_timeout' => 12.32, 'timeout' => 1.3, 'retry_interval' => 1, 'auth' => 'asdf']]]];
        $containerBuilder = $this->tryConfigs($configs);
        $this->assertEquals('localhost', $containerBuilder->getParameter('dtc_queue.redis.phpredis.host'));
        $this->assertEquals(6379, $containerBuilder->getParameter('dtc_queue.redis.phpredis.port'));
        $this->assertEquals(1.3, $containerBuilder->getParameter('dtc_queue.redis.phpredis.timeout'));
        $this->assertEquals(12.32, $containerBuilder->getParameter('dtc_queue.redis.phpredis.read_timeout'));
        $this->assertEquals(1, $containerBuilder->getParameter('dtc_queue.redis.phpredis.retry_interval'));
        $this->assertEquals('asdf', $containerBuilder->getParameter('dtc_queue.redis.phpredis.auth'));
    }

    public function testRabbitMq()
    {
        $configs = ['config' => ['rabbit_mq' => []]];
        $this->tryBadConfigs($configs);

        $configs = ['config' => ['rabbit_mq' => ['host' => 'somehost', 'port' => 1234, 'user' => 'auser', 'password' => 'pass']]];
        $containerBuilder = $this->tryConfigs($configs);
        $this->arrayTest($containerBuilder, 'dtc_queue.rabbit_mq', 'host', 'somehost');
        $this->arrayTest($containerBuilder, 'dtc_queue.rabbit_mq', 'port', 1234);
        $this->arrayTest($containerBuilder, 'dtc_queue.rabbit_mq', 'user', 'auser');
        $this->arrayTest($containerBuilder, 'dtc_queue.rabbit_mq', 'password', 'pass');

        $configs = ['config' => ['rabbit_mq' => ['host' => 'somehost', 'port' => 1234, 'user' => 'auser', 'password' => 'pass', 'options' => ['insist' => true]]]];
        $containerBuilder = $this->tryConfigs($configs);
        $this->arrayTest($containerBuilder, 'dtc_queue.rabbit_mq', 'options', ['insist' => true]);

        $configs = ['config' => ['rabbit_mq' => ['host' => 'somehost', 'port' => 1234, 'user' => 'auser', 'ssl_options' => ['a' => true], 'password' => 'pass']]];
        $this->tryBadConfigs($configs);

        $configs = ['config' => ['rabbit_mq' => ['host' => 'somehost', 'port' => 1234, 'user' => 'auser', 'ssl' => true, 'ssl_options' => ['a' => true], 'password' => 'pass']]];
        $containerBuilder = $this->tryConfigs($configs);
        $this->arrayTest($containerBuilder, 'dtc_queue.rabbit_mq', 'ssl_options', ['a' => true]);
        $rabbitMq = $containerBuilder->getParameter('dtc_queue.rabbit_mq');
        self::assertFalse(isset($rabbitMq['options']));

        $configs = ['config' => ['rabbit_mq' => ['host' => 'somehost', 'port' => 1234, 'user' => 'auser', 'ssl' => true, 'ssl_options' => ['a' => ['something']], 'password' => 'pass']]];
        $this->tryBadConfigs($configs);

        $configs = ['config' => ['rabbit_mq' => ['host' => 'somehost', 'port' => 1234, 'user' => 'auser', 'ssl' => true, 'ssl_options' => ['peer_fingerprint' => ['something' => 'else']], 'password' => 'pass']]];
        $containerBuilder = $this->tryConfigs($configs);
        $this->arrayTest($containerBuilder, 'dtc_queue.rabbit_mq', 'ssl_options', ['peer_fingerprint' => ['something' => 'else']]);
    }

    public function testDeprecated()
    {
        $configs = ['config' => ['default_manager' => 'odm']];
        $containerBuilder = $this->tryConfigs($configs);
        self::assertEquals('odm', $containerBuilder->getParameter('dtc_queue.manager.job'));

        $configs = ['config' => ['document_manager' => 'something']];
        $containerBuilder = $this->tryConfigs($configs);
        self::assertEquals('something', $containerBuilder->getParameter('dtc_queue.odm.document_manager'));

        $configs = ['config' => ['entity_manager' => 'something']];
        $containerBuilder = $this->tryConfigs($configs);
        self::assertEquals('something', $containerBuilder->getParameter('dtc_queue.orm.entity_manager'));

        $configs = ['config' => ['run_manager' => 'orm']];
        $containerBuilder = $this->tryConfigs($configs);
        self::assertEquals('orm', $containerBuilder->getParameter('dtc_queue.manager.run'));

        $configs = ['config' => ['job_timing_manager' => 'orm']];
        $containerBuilder = $this->tryConfigs($configs);
        self::assertEquals('orm', $containerBuilder->getParameter('dtc_queue.manager.job_timing'));

        $configs = ['config' => ['record_timings' => true]];
        $containerBuilder = $this->tryConfigs($configs);
        self::assertTrue($containerBuilder->getParameter('dtc_queue.timings.record'));

        $configs = ['config' => ['record_timings_timezone_offset' => 4.5]];
        $containerBuilder = $this->tryConfigs($configs);
        self::assertEquals(4.5, $containerBuilder->getParameter('dtc_queue.timings.timezone_offset'));

        $configs = ['config' => ['class_job' => '\Dtc\QueueBundle\Model\RetryableJob']];
        $containerBuilder = $this->tryConfigs($configs);
        self::assertEquals('\Dtc\QueueBundle\Model\RetryableJob', $containerBuilder->getParameter('dtc_queue.class.job'));

        $configs = ['config' => ['class_job_archive' => '\Dtc\QueueBundle\Model\RetryableJob']];
        $containerBuilder = $this->tryConfigs($configs);
        self::assertEquals('\Dtc\QueueBundle\Model\RetryableJob', $containerBuilder->getParameter('dtc_queue.class.job_archive'));

        $configs = ['config' => ['class_run' => '\Dtc\QueueBundle\Document\RunArchive']];
        $containerBuilder = $this->tryConfigs($configs);
        self::assertEquals('\Dtc\QueueBundle\Document\RunArchive', $containerBuilder->getParameter('dtc_queue.class.run'));

        $configs = ['config' => ['class_run_archive' => '\Dtc\QueueBundle\Document\RunArchive']];
        $containerBuilder = $this->tryConfigs($configs);
        self::assertEquals('\Dtc\QueueBundle\Document\RunArchive', $containerBuilder->getParameter('dtc_queue.class.run_archive'));

        $configs = ['config' => ['class_job_timing' => '\Dtc\QueueBundle\Document\JobTiming']];
        $containerBuilder = $this->tryConfigs($configs);
        self::assertEquals('\Dtc\QueueBundle\Document\JobTiming', $containerBuilder->getParameter('dtc_queue.class.job_timing'));

        $configs = ['config' => ['priority_max' => 10002]];
        $containerBuilder = $this->tryConfigs($configs);
        self::assertEquals(10002, $containerBuilder->getParameter('dtc_queue.priority.max'));

        $configs = ['config' => ['priority_direction' => PriorityJobManager::PRIORITY_DESC]];
        $containerBuilder = $this->tryConfigs($configs);
        self::assertEquals(PriorityJobManager::PRIORITY_DESC, $containerBuilder->getParameter('dtc_queue.priority.direction'));

        $configs = ['config' => ['priority_direction' => PriorityJobManager::PRIORITY_ASC]];
        $containerBuilder = $this->tryConfigs($configs);
        self::assertEquals(PriorityJobManager::PRIORITY_ASC, $containerBuilder->getParameter('dtc_queue.priority.direction'));
    }

    /**
     * @param ContainerBuilder $containerBuilder
     * @param string           $parameter
     * @param string           $key
     * @param mixed            $result
     */
    protected function arrayTest(ContainerBuilder $containerBuilder, $parameter, $key, $result)
    {
        $arr = $containerBuilder->getParameter($parameter);
        self::assertArrayHasKey($key, $arr);
        self::assertEquals($result, $arr[$key]);
    }
}
