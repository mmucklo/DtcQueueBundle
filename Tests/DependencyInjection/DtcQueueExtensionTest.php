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
