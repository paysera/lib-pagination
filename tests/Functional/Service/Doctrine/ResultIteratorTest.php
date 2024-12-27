<?php
declare(strict_types=1);

namespace Paysera\Pagination\Tests\Functional\Service\Doctrine;

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

    private function createTestData(EntityManager $entityManager)
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

    public function testIterateWithStartPager()
    {
        $entityManager = $this->createTestEntityManager();
        $this->createTestData($entityManager);

        $queryBuilder = $entityManager->createQueryBuilder()
            ->select('p')
            ->from(ParentTestEntity::class, 'p')
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

    public function testIterate()
    {
        $entityManager = $this->createTestEntityManager();
        $this->createTestData($entityManager);

        $queryBuilder = $entityManager->createQueryBuilder()
            ->select('p')
            ->from(ParentTestEntity::class, 'p')
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

    public function testTransformResultItems()
    {
        $entityManager = $this->createTestEntityManager();
        $this->createTestData($entityManager);

        $queryBuilder = $entityManager->createQueryBuilder()
            ->select('p')
            ->from(ParentTestEntity::class, 'p')
        ;

        $configuredQuery = new ConfiguredQuery($queryBuilder);
        $configuredQuery->setItemTransformer(function ($item) {
            return $item->getName();
        });
        $resultArray = iterator_to_array($this->resultIterator->iterate($configuredQuery));
        list($resultItem) = $resultArray;
        $this->assertEquals('P9', $resultItem);

        $configuredQuery = new ConfiguredQuery($queryBuilder);
        $resultArray = iterator_to_array($this->resultIterator->iterate($configuredQuery));
        list($resultItem) = $resultArray;
        $this->assertEquals('P9', $resultItem->getName());
    }
}
