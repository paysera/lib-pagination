<?php

declare(strict_types=1);

namespace Paysera\Pagination\Tests\Functional\Service\Doctrine;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\SimplifiedXmlDriver;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

abstract class DoctrineTestCase extends TestCase
{
    protected function createTestEntityManager(): EntityManager
    {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('Extension pdo_sqlite is required.');
        }

        $paths = [
            __DIR__ . '/../../Resources/config/doctrine' => 'Paysera\Pagination\Tests\Functional\Fixtures',
        ];

        $xmlDriver = new SimplifiedXmlDriver($paths, '.orm.xml');

        $config = ORMSetup::createConfiguration(false, sys_get_temp_dir(), new ArrayAdapter());
        if (PHP_VERSION_ID >= 80400 && method_exists($config, 'enableNativeLazyObjects')) {
            $config->enableNativeLazyObjects(true);
        }
        $config->setMetadataDriverImpl($xmlDriver);

        $connection = DriverManager::getConnection(
            [
                'driver' => 'pdo_sqlite',
                'memory' => true,
            ],
            $config
        );

        $entityManager = new EntityManager($connection, $config);

        $metadataFactory = $entityManager->getMetadataFactory();
        $metadataFactory->getAllMetadata();

        $metadata = $metadataFactory->getLoadedMetadata();

        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        return $entityManager;
    }
}
