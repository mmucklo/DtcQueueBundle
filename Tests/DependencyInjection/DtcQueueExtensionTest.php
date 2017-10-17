<?php

namespace Dtc\QueueBundle\Tests\DependencyInjection;

use Dtc\QueueBundle\DependencyInjection\DtcQueueExtension;
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

    public function tryBadConfigs(array $configs)
    {
        $dtcQueueExtension = new DtcQueueExtension();
        $containerBuilder = new ContainerBuilder();
        try {
            $dtcQueueExtension->load($configs, $containerBuilder);
            self::fail('should not reach here');
        } catch (\Exception $exception) {
            self::assertTrue(true);
        }
    }

    public function testBeanstalkExtension()
    {
        $configs = ['config' => ['beanstalkd' => ['host' => null]]];
        $this->tryBadConfigs($configs);
        $configs = ['config' => ['beanstalkd' => ['tube' => null]]];
        $this->tryBadConfigs($configs);

        $configs = ['config' => ['beanstalkd' => ['host' => 'somehost']]];
        $containerBuilder = $this->tryGoodConfigs($configs);
        self::assertEquals('somehost', $containerBuilder->getParameter('dtc_queue.beanstalkd.host'));

        $configs = ['config' => ['beanstalkd' => ['host' => 'somehost', 'tube' => 'something']]];
        $containerBuilder = $this->tryGoodConfigs($configs);

        self::assertEquals('something', $containerBuilder->getParameter('dtc_queue.beanstalkd.tube'));
        self::assertEquals('somehost', $containerBuilder->getParameter('dtc_queue.beanstalkd.host'));
    }

    protected function tryGoodConfigs(array $configs)
    {
        $dtcQueueExtension = new DtcQueueExtension();
        $containerBuilder = new ContainerBuilder();

        try {
            $dtcQueueExtension->load($configs, $containerBuilder);
        } catch (\Exception $exception) {
            self::fail($exception->getMessage());
        }
        self::assertTrue(true);

        return $containerBuilder;
    }

    public function testRabbitMq()
    {
        $configs = ['config' => ['rabbit_mq' => []]];
        $this->tryBadConfigs($configs);

        $configs = ['config' => ['rabbit_mq' => ['host' => 'somehost', 'port' => 1234, 'user' => 'auser', 'password' => 'pass']]];
        $containerBuilder = $this->tryGoodConfigs($configs);
        $this->arrayTest($containerBuilder, 'dtc_queue.rabbit_mq', 'host', 'somehost');
        $this->arrayTest($containerBuilder, 'dtc_queue.rabbit_mq', 'port', 1234);
        $this->arrayTest($containerBuilder, 'dtc_queue.rabbit_mq', 'user', 'auser');
        $this->arrayTest($containerBuilder, 'dtc_queue.rabbit_mq', 'password', 'pass');
    }

    public function arrayTest(ContainerBuilder $containerBuilder, $parameter, $key, $result)
    {
        $arr = $containerBuilder->getParameter($parameter);
        self::assertArrayHasKey($key, $arr);
        self::assertEquals($result, $arr[$key]);
    }
}
