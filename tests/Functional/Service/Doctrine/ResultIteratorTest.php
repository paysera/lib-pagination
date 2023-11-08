<?php
declare(strict_types=1);

namespace Paysera\Pagination\Tests\Functional\Service\Doctrine;

use Doctrine\DBAL\Exception;
use Doctrine\ORM\Exception\MissingMappingDriverImplementation;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\Tools\ToolsException;
use Doctrine\Persistence\Mapping\MappingException;
use Paysera\Pagination\Entity\OrderingConfiguration;
use Doctrine\ORM\EntityManager;
use Paysera\Pagination\Service\Doctrine\QueryAnalyser;
use Paysera\Pagination\Entity\Doctrine\ConfiguredQuery;
use Paysera\Pagination\Entity\OrderingPair;
use Paysera\Pagination\Entity\Pager;
use Paysera\Pagination\Service\Doctrine\ResultIterator;
use Paysera\Pagination\Service\Doctrine\ResultProvider;
use Paysera\Pagination\Service\CursorBuilder;
use Paysera\Pagination\Tests\Functional\Fixtures\ChildTestEntity;
use Paysera\Pagination\Tests\Functional\Fixtures\ParentTestEntity;
use Paysera\Pagination\Tests\Functional\Fixtures\TestLogger;
use ReflectionException;
use Symfony\Component\PropertyAccess\PropertyAccess;

class ResultIteratorTest extends DoctrineTestCase
{
    /**
     * @var ResultIterator
     */
    private $resultIterator;

    /**
     * @var TestLogger
     */
    private $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = new TestLogger();

        $this->resultIterator = new ResultIterator(
            new ResultProvider(
                new QueryAnalyser(),
                new CursorBuilder(PropertyAccess::createPropertyAccessor())
            ),
            $this->logger,
            9
        );
    }

    /**
     * @param EntityManager $entityManager
     * @return void
     * @throws ORMException
     * @throws OptimisticLockException
     */
    private function createTestData(EntityManager $entityManager): void
    {
        for ($parentIndex = 0; $parentIndex < 10; $parentIndex++) {
            for ($i = 0; $i < 3; $i++) {
                $parent = (new ParentTestEntity())->setName(sprintf('P%s', $parentIndex));
                $entityManager->persist($parent);
                $child = (new ChildTestEntity())->setName(sprintf('C%s%s', $parentIndex, 1))->setParent($parent);
                $entityManager->persist($child);
                $child = (new ChildTestEntity())->setName(sprintf('C%s%s', $parentIndex, 2))->setParent($parent);
                $entityManager->persist($child);
            }
        }

        $entityManager->flush();
    }

    /**
     * @return void
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws Exception
     * @throws MissingMappingDriverImplementation
     * @throws ToolsException
     * @throws MappingException
     * @throws ReflectionException
     */
    public function testIterateWithStartPager(): void
    {
        $entityManager = $this->createTestEntityManager();
        $this->createTestData($entityManager);

        $queryBuilder = $entityManager->createQueryBuilder()
            ->select('p')
            ->from('PaginationTest:ParentTestEntity', 'p')
            ->join('p.children', 'c')
        ;

        $configuredQuery = (new ConfiguredQuery($queryBuilder))->addOrderingConfigurations([
            'name' => new OrderingConfiguration('p.name', 'name'),
            'id' => new OrderingConfiguration('p.id', 'id'),
        ]);

        $items = [];
        $startPager = (new Pager())
            ->addOrderBy(new OrderingPair('name', true))
            ->addOrderBy(new OrderingPair('id', true))
            // IMPORTANT! These are *not* logical from end-user perspective, as it goes to SQL and includes children
            ->setLimit(4)
            ->setOffset(40)
        ;
        foreach ($this->resultIterator->iterate($configuredQuery, $startPager) as $item) {
            $items[] = sprintf('%s.%s', $item->getName(), $item->getId());
        }

        $this->assertEquals(['P6.21', 'P7.22', 'P7.23', 'P7.24', 'P8.25', 'P8.26', 'P8.27', 'P9.28', 'P9.29', 'P9.30'], $items);
        $this->assertCount(5, array_filter($this->logger->getLogs(), function ($logInfo) {
            return $logInfo[0] === 'info';
        }));
    }

    /**
     * @return void
     * @throws Exception
     * @throws MappingException
     * @throws MissingMappingDriverImplementation
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws ReflectionException
     * @throws ToolsException
     */
    public function testIterate(): void
    {
        $entityManager = $this->createTestEntityManager();
        $this->createTestData($entityManager);

        $queryBuilder = $entityManager->createQueryBuilder()
            ->select('p')
            ->from('PaginationTest:ParentTestEntity', 'p')
        ;

        $configuredQuery = new ConfiguredQuery($queryBuilder);

        $this->assertCount(30, iterator_to_array($this->resultIterator->iterate($configuredQuery)));
        $this->assertCount(4, array_filter($this->logger->getLogs(), function ($logInfo) {
            return $logInfo[0] === 'info';
        }));

        $this->assertCount(30, iterator_to_array($this->resultIterator->iterate(
            $configuredQuery,
            new Pager()
        )));
        $this->assertCount(4 + 4, array_filter($this->logger->getLogs(), function ($logInfo) {
            return $logInfo[0] === 'info';
        }));
    }

    /**
     * @return void
     * @throws Exception
     * @throws MappingException
     * @throws MissingMappingDriverImplementation
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws ReflectionException
     * @throws ToolsException
     */
    public function testTransformResultItems(): void
    {
        $entityManager = $this->createTestEntityManager();
        $this->createTestData($entityManager);

        $queryBuilder = $entityManager->createQueryBuilder()
            ->select('p')
            ->from('PaginationTest:ParentTestEntity', 'p')
        ;

        $configuredQuery = new ConfiguredQuery($queryBuilder);
        $configuredQuery->setItemTransformer(function ($item) {
            return $item->getName();
        });
        $resultArray = iterator_to_array($this->resultIterator->iterate($configuredQuery));
        [$resultItem] = $resultArray;
        $this->assertEquals('P9', $resultItem);

        $configuredQuery = new ConfiguredQuery($queryBuilder);
        $resultArray = iterator_to_array($this->resultIterator->iterate($configuredQuery));
        [$resultItem] = $resultArray;
        $this->assertEquals('P9', $resultItem->getName());
    }
}
