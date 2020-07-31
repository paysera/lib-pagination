<?php
declare(strict_types=1);

namespace Paysera\Pagination\Tests\Functional\Service\Doctrine;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Cache\ArrayCache;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AnnotationDriver;
use Doctrine\ORM\Tools\SchemaTool;
use Paysera\Pagination\Tests\Functional\Fixtures\ChildTestEntity;
use Paysera\Pagination\Tests\Functional\Fixtures\DateTimeEntity;
use Paysera\Pagination\Tests\Functional\Fixtures\ParentTestEntity;
use PHPUnit\Framework\TestCase;
use Doctrine\ORM\Configuration;

abstract class DoctrineTestCase extends TestCase
{
    protected function createTestEntityManager()
    {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('Extension pdo_sqlite is required.');
        }

        $entityManager = EntityManager::create([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ], $this->createTestConfiguration());

        $schemaTool = new SchemaTool($entityManager);
        $metadataFactory = $entityManager->getMetadataFactory();
        $metadataFactory->getMetadataFor(ParentTestEntity::class);
        $metadataFactory->getMetadataFor(ChildTestEntity::class);
        $metadataFactory->getMetadataFor(DateTimeEntity::class);
        $metadata = $metadataFactory->getLoadedMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        return $entityManager;
    }

    protected function createTestConfiguration()
    {
        $config = new Configuration();
        $config->setEntityNamespaces(['PaginationTest' => 'Paysera\Pagination\Tests\Functional\Fixtures']);
        $config->setAutoGenerateProxyClasses(true);
        $config->setProxyDir(sys_get_temp_dir());
        $config->setProxyNamespace('PaginationTest\Doctrine');
        $config->setMetadataDriverImpl(new AnnotationDriver(new AnnotationReader()));
        $config->setQueryCacheImpl(new ArrayCache());
        $config->setMetadataCacheImpl(new ArrayCache());
        AnnotationRegistry::registerLoader('class_exists');

        return $config;
    }
}
