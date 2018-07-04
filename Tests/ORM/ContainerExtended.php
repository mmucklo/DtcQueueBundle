<?php

namespace Dtc\QueueBundle\Tests\ORM;

use Doctrine\ORM\EntityManager;
use Symfony\Component\DependencyInjection\Container;
use Doctrine\ORM\Tools\Setup;

class ContainerExtended extends Container
{
    protected $methodMap = ['doctrine.orm.default_entity_manager' => 'getDoctrine_Orm_DefaultEntityManagerService'];

    public function getDoctrine_Orm_DefaultEntityManagerService($something = false)
    {
        $config = Setup::createAnnotationMetadataConfiguration(array(__DIR__.'/../..'), true, null, null, false);
        $config->addCustomNumericFunction('year', Year::class);
        $config->addCustomNumericFunction('month', Month::class);
        $config->addCustomNumericFunction('day', Day::class);
        $config->addCustomNumericFunction('hour', Hour::class);
        $config->addCustomNumericFunction('minute', Minute::class);
        $host = getenv('MYSQL_HOST');
        $user = getenv('MYSQL_USER');
        $port = getenv('MYSQL_PORT') ?: 3306;
        $password = getenv('MYSQL_PASSWORD');
        $db = getenv('MYSQL_DATABASE');
        $params = ['host' => $host,
            'port' => $port,
            'user' => $user,
            'driver' => 'mysqli',
            'password' => $password,
            'dbname' => $db, ];

        return EntityManager::create($params, $config);
    }
}
