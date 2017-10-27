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
    }

    public function arrayTest(ContainerBuilder $containerBuilder, $parameter, $key, $result)
    {
        $arr = $containerBuilder->getParameter($parameter);
        self::assertArrayHasKey($key, $arr);
        self::assertEquals($result, $arr[$key]);
    }
}
