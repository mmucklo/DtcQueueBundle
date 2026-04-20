<?php

namespace Dtc\QueueBundle\Tests\ORM;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Symfony\Component\DependencyInjection\Container;

class ContainerExtended extends Container
{
    public function __construct()
    {
        parent::__construct();
        $this->methodMap = ['doctrine.orm.default_entity_manager' => 'getDoctrine_Orm_DefaultEntityManagerService'];
    }

    public function getDoctrine_Orm_DefaultEntityManagerService($something = false)
    {
        $config = ORMSetup::createAttributeMetadataConfiguration([__DIR__.'/../..'], true);
        $host = getenv('MYSQL_HOST');
        $user = getenv('MYSQL_USER');
        $port = (int) (getenv('MYSQL_PORT') ?: 3306);
        $password = getenv('MYSQL_PASSWORD');
        $db = getenv('MYSQL_DATABASE');
        $params = ['host' => $host,
            'port' => $port,
            'user' => $user,
            'driver' => 'mysqli',
            'password' => $password,
            'dbname' => $db, ];

        $connection = DriverManager::getConnection($params, $config);
        return new EntityManager($connection, $config);
    }
}
