<?php
declare(strict_types=1);

namespace Paysera\Pagination\Tests\Functional\Service\Doctrine;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Exception\MissingMappingDriverImplementation;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\Mapping\Driver\AnnotationDriver;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\ToolsException;
use Doctrine\Persistence\Mapping\MappingException;
use Paysera\Pagination\Tests\Functional\Fixtures\ChildTestEntity;
use Paysera\Pagination\Tests\Functional\Fixtures\DateTimeEntity;
use Paysera\Pagination\Tests\Functional\Fixtures\ParentTestEntity;
use PHPUnit\Framework\TestCase;
use Doctrine\ORM\Configuration;
use ReflectionException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

abstract class DoctrineTestCase extends TestCase
{
    /**
     * @return EntityManager
     * @throws Exception
     * @throws MissingMappingDriverImplementation
     * @throws ORMException
     * @throws ToolsException
     * @throws MappingException
     * @throws ReflectionException
     */
    protected function createTestEntityManager(): EntityManager
    {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('Extension pdo_sqlite is required.');
        }

        $entityManager = new EntityManager(
            DriverManager::getConnection([
                'driver' => 'pdo_sqlite',
                'memory' => true,
            ]),
            $this->createTestConfiguration()
        );

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

    protected function createTestConfiguration(): Configuration
    {
        $config = new Configuration();
        $config->setEntityNamespaces(['PaginationTest' => 'Paysera\Pagination\Tests\Functional\Fixtures']);
        $config->setAutoGenerateProxyClasses(true);
        $config->setProxyDir(sys_get_temp_dir());
        $config->setProxyNamespace('PaginationTest\Doctrine');
        $config->setMetadataDriverImpl(new AnnotationDriver(new AnnotationReader()));
        $config->setQueryCache(new ArrayAdapter());
        $config->setMetadataCache(new ArrayAdapter());

        return $config;
    }
}
