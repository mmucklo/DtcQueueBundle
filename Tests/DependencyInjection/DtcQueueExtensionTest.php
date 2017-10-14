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
    }
}
