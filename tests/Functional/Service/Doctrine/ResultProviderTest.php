<?php
declare(strict_types=1);

namespace Paysera\Pagination\Tests\Functional\Service\Doctrine;

use DateTime;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\Exception\MissingMappingDriverImplementation;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\ToolsException;
use Doctrine\Persistence\Mapping\MappingException;
use Paysera\Pagination\Entity\OrderingConfiguration;
use Doctrine\ORM\EntityManager;
use Paysera\Pagination\Exception\InvalidGroupByException;
use Paysera\Pagination\Service\Doctrine\QueryAnalyser;
use Paysera\Pagination\Entity\Doctrine\ConfiguredQuery;
use Paysera\Pagination\Entity\OrderingPair;
use Paysera\Pagination\Entity\Pager;
use Paysera\Pagination\Entity\Result;
use Paysera\Pagination\Service\Doctrine\ResultProvider;
use Paysera\Pagination\Exception\InvalidOrderByException;
use Paysera\Pagination\Exception\TooLargeOffsetException;
use Paysera\Pagination\Service\CursorBuilder;
use Paysera\Pagination\Tests\Functional\Fixtures\ChildTestEntity;
use Paysera\Pagination\Tests\Functional\Fixtures\DateTimeEntity;
use Paysera\Pagination\Tests\Functional\Fixtures\ParentTestEntity;
use ReflectionException;
use Symfony\Component\PropertyAccess\PropertyAccess;

class ResultProviderTest extends DoctrineTestCase
{
    /**
     * @var ResultProvider
     */
    private $resultProvider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resultProvider = new ResultProvider(
            new QueryAnalyser(),
            new CursorBuilder(PropertyAccess::createPropertyAccessor())
        );
    }

    private function createTestData(EntityManager $entityManager, int $groupEvery = 10)
    {
        for ($parentIndex = 0; $parentIndex < 30; $parentIndex++) {
            $parent = (new ParentTestEntity())->setName(sprintf('P%s', $parentIndex));
            if ($parentIndex % $groupEvery === 0) {
                $parent->setGroupKey(sprintf('group_%s', $parentIndex));
            }
            $entityManager->persist($parent);
        }

        $entityManager->flush();
    }

    public function testGetResultForQueryWithNewResources()
    {
        $entityManager = $this->createTestEntityManager();

        $queryBuilder = $entityManager->createQueryBuilder()
            ->select('p')
            ->from(ParentTestEntity::class, 'p')
        ;

        $configuredQuery = new ConfiguredQuery($queryBuilder);

        $pager = (new Pager())->setLimit(10);
        $result = $this->resultProvider->getResultForQuery($configuredQuery, $pager);
        $this->assertCount(0, $result->getItems());

        $this->createTestData($entityManager);

        $pager->setAfter($result->getNextCursor())->setLimit(10);
        $result = $this->resultProvider->getResultForQuery($configuredQuery, $pager);
        $this->assertCount(10, $result->getItems());

        $pager = (new Pager())->setLimit(15);
        $result = $this->resultProvider->getResultForQuery($configuredQuery, $pager);
        $this->assertCount(15, $result->getItems());
        $firstResultPreviousCursor = $result->getPreviousCursor();

        $pager->setAfter($result->getNextCursor())->setLimit(10);
        $result = $this->resultProvider->getResultForQuery($configuredQuery, $pager);
        $this->assertCount(10, $result->getItems());

        $pager->setAfter($result->getNextCursor());
        $result = $this->resultProvider->getResultForQuery($configuredQuery, $pager);
        $this->assertCount(5, $result->getItems());

        $this->createTestData($entityManager);

        $pager->setAfter($result->getNextCursor());
        $result = $this->resultProvider->getResultForQuery($configuredQuery, $pager);
        $this->assertCount(0, $result->getItems());

        $pager = (new Pager())->setBefore($firstResultPreviousCursor);
        $result = $this->resultProvider->getResultForQuery($configuredQuery, $pager);
        $this->assertCount(30, $result->getItems());

        $pager = (new Pager())->setLimit(15)->setOffset(80);
        $result = $this->resultProvider->getResultForQuery($configuredQuery, $pager);
        $this->assertCount(0, $result->getItems());

        $pager = (new Pager())->setAfter($result->getNextCursor())->setLimit(10);
        $result = $this->resultProvider->getResultForQuery($configuredQuery, $pager);
        $this->assertCount(0, $result->getItems());
        $this->assertSame($result->getNextCursor(), $pager->getAfter());

        $pager = (new Pager())->setBefore($result->getPreviousCursor())->setLimit(70);
        $result = $this->resultProvider->getResultForQuery($configuredQuery, $pager);
        $this->assertCount(60, $result->getItems());
    }

    /**
     * @dataProvider getResultProvider
     * @param Result $expectedResult
     * @param Pager $pager
     * @param bool $totalCountNeeded
     * @param mixed $find
     */
    public function testGetResultForQuery(Result $expectedResult, Pager $pager, $totalCountNeeded = false, $find = true)
    {
        $entityManager = $this->createTestEntityManager();
        $this->createTestData($entityManager);

        $queryBuilder = $entityManager->createQueryBuilder()
            ->select('p')
            ->from(ParentTestEntity::class, 'p')
        ;
        if (!$find) {
            $queryBuilder->andWhere('p.name = :name')->setParameter('name', 'non-existing');
        }

        $configuredQuery = (new ConfiguredQuery($queryBuilder))
            ->addOrderingConfigurations([
                'id' => (new OrderingConfiguration('p.id'))->setAccessorPath('id'),
            ])
            ->setTotalCountNeeded($totalCountNeeded)
        ;

        $result = $this->resultProvider->getResultForQuery($configuredQuery, $pager);

        $result->setItems(array_map(function (ParentTestEntity $item) {
            return (string)$item->getId();
        }, $result->getItems()));

        $this->assertEquals($expectedResult, $result);
    }

    public function getResultProvider(): array
    {
        return [
            'default' => [
                (new Result())
                    ->setItems(['30', '29', '28', '27', '26'])
                    ->setHasNext(true)
                    ->setHasPrevious(false)
                    ->setPreviousCursor('"30"')
                    ->setNextCursor('"26"'),
                (new Pager())
                    ->setLimit(5),
            ],
            'simple' => [
                (new Result())
                    ->setItems(['1', '2', '3', '4', '5'])
                    ->setHasNext(true)
                    ->setHasPrevious(false)
                    ->setPreviousCursor('"1"')
                    ->setNextCursor('"5"'),
                (new Pager())
                    ->setLimit(5)
                    ->addOrderBy(new OrderingPair('id', true)),
            ],
            'second page' => [
                (new Result())
                    ->setItems(['6', '7', '8', '9', '10'])
                    ->setHasNext(true)
                    ->setHasPrevious(true)
                    ->setPreviousCursor('"6"')
                    ->setNextCursor('"10"'),
                (new Pager())
                    ->setLimit(5)
                    ->addOrderBy(new OrderingPair('id', true))
                    ->setAfter('"5"'),
            ],
            'before first page' => [
                (new Result())
                    ->setItems([])
                    ->setHasNext(true)
                    ->setHasPrevious(false)
                    ->setPreviousCursor('"1"')
                    ->setNextCursor('="1"'),
                (new Pager())
                    ->setLimit(5)
                    ->addOrderBy(new OrderingPair('id', true))
                    ->setBefore('"1"'),
            ],
            'before second page' => [
                (new Result())
                    ->setItems(['1'])
                    ->setHasNext(true)
                    ->setHasPrevious(false)
                    ->setPreviousCursor('"1"')
                    ->setNextCursor('"1"'),
                (new Pager())
                    ->setLimit(5)
                    ->addOrderBy(new OrderingPair('id', true))
                    ->setBefore('"2"'),
            ],
            'before third page' => [
                (new Result())
                    ->setItems(['2', '3'])
                    ->setHasNext(true)
                    ->setHasPrevious(true)
                    ->setPreviousCursor('"2"')
                    ->setNextCursor('"3"'),
                (new Pager())
                    ->setLimit(2)
                    ->addOrderBy(new OrderingPair('id', true))
                    ->setBefore('"4"'),
            ],
            'after last page' => [
                (new Result())
                    ->setItems([])
                    ->setHasNext(false)
                    ->setHasPrevious(true)
                    ->setPreviousCursor('="30"')
                    ->setNextCursor('"30"'),
                (new Pager())
                    ->setLimit(5)
                    ->addOrderBy(new OrderingPair('id', true))
                    ->setOffset(50),
            ],
            'after last page with no results' => [
                (new Result())
                    ->setItems([])
                    ->setHasNext(false)
                    ->setHasPrevious(false),
                (new Pager())
                    ->setLimit(5)
                    ->addOrderBy(new OrderingPair('id', true))
                    ->setOffset(50),
                false,
                false,  // <--- find no results
            ],
            'after last page with cursor' => [
                (new Result())
                    ->setItems([])
                    ->setHasNext(false)
                    ->setHasPrevious(true)
                    ->setPreviousCursor('="31"')
                    ->setNextCursor('"31"'),
                (new Pager())
                    ->setLimit(5)
                    ->addOrderBy(new OrderingPair('id', true))
                    ->setAfter('"31"'),
            ],
            'after last page with cursor: follow up with previous' => [
                (new Result())
                    ->setItems(['29', '30'])
                    ->setHasNext(false)
                    ->setHasPrevious(true)
                    ->setPreviousCursor('"29"')
                    ->setNextCursor('"30"'),
                (new Pager())
                    ->setLimit(2)
                    ->addOrderBy(new OrderingPair('id', true))
                    ->setBefore('="34"'),
            ],
            'no items with inclusive cursor' => [
                (new Result())
                    ->setItems([])
                    ->setHasNext(false)
                    ->setHasPrevious(true)
                    ->setPreviousCursor('"32"')
                    ->setNextCursor('="32"'),
                (new Pager())
                    ->setLimit(2)
                    ->addOrderBy(new OrderingPair('id', true))
                    ->setAfter('="32"'),
            ],
            'no items with inclusive before cursor' => [
                (new Result())
                    ->setItems([])
                    ->setHasNext(true)
                    ->setHasPrevious(false)
                    ->setPreviousCursor('="-1"')
                    ->setNextCursor('"-1"'),
                (new Pager())
                    ->setLimit(2)
                    ->addOrderBy(new OrderingPair('id', true))
                    ->setBefore('="-1"'),
            ],
            'with zero limit and no offset' => [
                (new Result())
                    ->setItems([])
                    ->setHasNext(true)
                    ->setHasPrevious(false)
                    ->setPreviousCursor('"1"')
                    ->setNextCursor('="1"'),
                (new Pager())
                    ->setLimit(0)
                    ->addOrderBy(new OrderingPair('id', true)),
            ],
            'with zero limit and cursor' => [
                (new Result())
                    ->setItems([])
                    ->setHasNext(true)
                    ->setHasPrevious(true)
                    ->setPreviousCursor('"4"')
                    ->setNextCursor('="4"'),
                (new Pager())
                    ->setLimit(0)
                    ->addOrderBy(new OrderingPair('id', true))
                    ->setAfter('"3"'),
            ],
            'with zero limit and after-the-last cursor' => [
                (new Result())
                    ->setItems([])
                    ->setHasNext(false)
                    ->setHasPrevious(true)
                    ->setPreviousCursor('="34"')
                    ->setNextCursor('"34"'),
                (new Pager())
                    ->setLimit(0)
                    ->addOrderBy(new OrderingPair('id', true))
                    ->setAfter('"34"'),
            ],
            'with total count' => [
                (new Result())
                    ->setItems([])
                    ->setHasNext(false)
                    ->setHasPrevious(true)
                    ->setPreviousCursor('="34"')
                    ->setNextCursor('"34"')
                    ->setTotalCount(30),
                (new Pager())
                    ->setLimit(15)
                    ->addOrderBy(new OrderingPair('id', true))
                    ->setAfter('"34"'),
                true,
            ],
            'with calculated total count' => [
                (new Result())
                    ->setItems(['29', '30'])
                    ->setHasNext(false)
                    ->setHasPrevious(true)
                    ->setPreviousCursor('"29"')
                    ->setNextCursor('"30"')
                    ->setTotalCount(30),
                (new Pager())
                    ->addOrderBy(new OrderingPair('id', true))
                    ->setOffset(28),
                true,
            ],
        ];
    }

    /**
     * @dataProvider getResultProviderForSeveralLevelOrdering
     * @param Result $expectedResult
     * @param Pager $pager
     */
    public function testGetResultForQueryWithSeveralLevelOrdering(Result $expectedResult, Pager $pager)
    {
        $entityManager = $this->createTestEntityManager();
        $this->createHierarchicalData($entityManager);

        $queryBuilder = $entityManager->createQueryBuilder()
            ->select('c')
            ->from(ChildTestEntity::class, 'c')
            ->join('c.parent', 'p')
        ;

        $configuredQuery = (new ConfiguredQuery($queryBuilder))->addOrderingConfigurations([
            'name' => new OrderingConfiguration('c.name', 'name'),
            'parent_name' => new OrderingConfiguration('p.name', 'parent.name'),
            'id' => new OrderingConfiguration('c.id', 'id'),
        ]);

        $result = $this->resultProvider->getResultForQuery($configuredQuery, $pager);

        $result->setItems(array_map(function (ChildTestEntity $item) {
            return sprintf('%s.%s.%s', $item->getParent()->getName(), $item->getName(), $item->getId());
        }, $result->getItems()));

        $this->assertEquals($expectedResult, $result);
    }

    private function createHierarchicalData(EntityManager $entityManager)
    {
        for ($parentIndex = 1; $parentIndex < 10; $parentIndex++) {
            $reverseIndex = 10 - $parentIndex;
            foreach ([$parentIndex, $reverseIndex, $parentIndex, $reverseIndex, $parentIndex] as $childIndex) {
                $parent = (new ParentTestEntity())->setName(sprintf('P%s', $parentIndex));
                $entityManager->persist($parent);

                $child = (new ChildTestEntity())->setName(sprintf('C%s', $childIndex));
                $parent->addChild($child);
                $entityManager->persist($child);
            }
        }

        $entityManager->flush();
    }

    public function getResultProviderForSeveralLevelOrdering(): array
    {
        return [
            'name asc, parent_name asc, id asc' => [
                (new Result())
                    ->setItems(['P1.C1.1', 'P1.C1.3', 'P1.C1.5', 'P9.C1.42', 'P9.C1.44', 'P2.C2.6'])
                    ->setHasNext(true)
                    ->setHasPrevious(false)
                    ->setPreviousCursor('"C1","P1","1"')
                    ->setNextCursor('"C2","P2","6"'),
                (new Pager())
                    ->setLimit(6)
                    ->addOrderBy(new OrderingPair('name', true))
                    ->addOrderBy(new OrderingPair('parent_name', true))
                    ->addOrderBy(new OrderingPair('id', true)),
            ],
            'name asc, parent_name desc, id asc' => [
                (new Result())
                    ->setItems(['P9.C1.42', 'P9.C1.44', 'P1.C1.1', 'P1.C1.3', 'P1.C1.5', 'P8.C2.37'])
                    ->setHasNext(true)
                    ->setHasPrevious(false)
                    ->setPreviousCursor('"C1","P9","42"')
                    ->setNextCursor('"C2","P8","37"'),
                (new Pager())
                    ->setLimit(6)
                    ->addOrderBy(new OrderingPair('name', true))
                    ->addOrderBy(new OrderingPair('parent_name', false))
                    ->addOrderBy(new OrderingPair('id', true)),
            ],
            'name asc, parent_name asc, id asc with after' => [
                (new Result())
                    ->setItems(['P1.C1.3', 'P1.C1.5', 'P9.C1.42', 'P9.C1.44', 'P2.C2.6'])
                    ->setHasNext(true)
                    ->setHasPrevious(true)
                    ->setPreviousCursor('"C1","P1","3"')
                    ->setNextCursor('"C2","P2","6"'),
                (new Pager())
                    ->setLimit(5)
                    ->addOrderBy(new OrderingPair('name', true))
                    ->addOrderBy(new OrderingPair('parent_name', true))
                    ->addOrderBy(new OrderingPair('id', true))
                    ->setAfter('"C1","P1","1"'),
            ],
            'name asc, parent_name asc, id asc with before' => [
                (new Result())
                    ->setItems(['P1.C1.1', 'P1.C1.3', 'P1.C1.5'])
                    ->setHasNext(true)
                    ->setHasPrevious(false)
                    ->setPreviousCursor('"C1","P1","1"')
                    ->setNextCursor('"C1","P1","5"'),
                (new Pager())
                    ->setLimit(5)
                    ->addOrderBy(new OrderingPair('name', true))
                    ->addOrderBy(new OrderingPair('parent_name', true))
                    ->addOrderBy(new OrderingPair('id', true))
                    ->setBefore('"C1","P9","42"'),
            ],
            'name asc, parent_name asc, id asc with inclusive before' => [
                (new Result())
                    ->setItems(['P1.C1.1', 'P1.C1.3', 'P1.C1.5', 'P9.C1.42'])
                    ->setHasNext(true)
                    ->setHasPrevious(false)
                    ->setPreviousCursor('"C1","P1","1"')
                    ->setNextCursor('"C1","P9","42"'),
                (new Pager())
                    ->setLimit(5)
                    ->addOrderBy(new OrderingPair('name', true))
                    ->addOrderBy(new OrderingPair('parent_name', true))
                    ->addOrderBy(new OrderingPair('id', true))
                    ->setBefore('="C1","P9","42"'),
            ],
            'name asc, parent_name asc, id asc with inclusive after' => [
                (new Result())
                    ->setItems(['P1.C1.3', 'P1.C1.5', 'P9.C1.42', 'P9.C1.44', 'P2.C2.6'])
                    ->setHasNext(true)
                    ->setHasPrevious(true)
                    ->setPreviousCursor('"C1","P1","3"')
                    ->setNextCursor('"C2","P2","6"'),
                (new Pager())
                    ->setLimit(5)
                    ->addOrderBy(new OrderingPair('name', true))
                    ->addOrderBy(new OrderingPair('parent_name', true))
                    ->addOrderBy(new OrderingPair('id', true))
                    ->setAfter('="C1","P1","3"'),
            ],
            'name asc, parent_name asc, id desc with inclusive after' => [
                (new Result())
                    ->setItems(['P9.C1.44', 'P9.C1.42', 'P2.C2.10'])
                    ->setHasNext(true)
                    ->setHasPrevious(true)
                    ->setPreviousCursor('"C1","P9","44"')
                    ->setNextCursor('"C2","P2","10"'),
                (new Pager())
                    ->setLimit(3)
                    ->addOrderBy(new OrderingPair('name', true))
                    ->addOrderBy(new OrderingPair('parent_name', true))
                    ->addOrderBy(new OrderingPair('id', false))
                    ->setAfter('="C1","P9","44"'),
            ],
            'name desc, parent_name desc, id desc with after' => [
                (new Result())
                    ->setItems(['P9.C1.42', 'P1.C1.5', 'P1.C1.3'])
                    ->setHasNext(true)
                    ->setHasPrevious(true)
                    ->setPreviousCursor('"C1","P9","42"')
                    ->setNextCursor('"C1","P1","3"'),
                (new Pager())
                    ->setLimit(3)
                    ->addOrderBy(new OrderingPair('name', false))
                    ->addOrderBy(new OrderingPair('parent_name', false))
                    ->addOrderBy(new OrderingPair('id', false))
                    ->setAfter('"C1","P9","44"'),
            ],
        ];
    }

    /**
     * @dataProvider getResultProviderForDateTimeField
     * @param Result $expectedResult
     * @param Pager $pager
     */
    public function testGetResultForQueryWithDateTimeField(Result $expectedResult, Pager $pager)
    {
        $entityManager = $this->createTestEntityManager();
        $this->createDateTimeRelatedData($entityManager);

        $queryBuilder = $entityManager->createQueryBuilder()
            ->select('d')
            ->from(DateTimeEntity::class, 'd')
        ;

        $configuredQuery = (new ConfiguredQuery($queryBuilder))->addOrderingConfiguration(
            'created_at',
            new OrderingConfiguration('d.createdAt', 'createdAt')
        );

        $result = $this->resultProvider->getResultForQuery($configuredQuery, $pager);
        $result->setItems(array_map(function (DateTimeEntity $item) {
            return (string)$item->getId();
        }, $result->getItems()));

        $this->assertEquals($expectedResult, $result);
    }

    private function createDateTimeRelatedData(EntityManager $entityManager)
    {
        for ($i = 0; $i < 30; $i++) {
            $entity1 = (new DateTimeEntity())->setCreatedAt(new DateTime(sprintf('2020-02-02 12:00:%s', $i)));
            $entity2 = (new DateTimeEntity())->setCreatedAt(new DateTime(sprintf('2020-02-02 12:00:%s', $i)));
            $entityManager->persist($entity1);
            $entityManager->persist($entity2);
        }

        $entityManager->flush();
    }

    public function getResultProviderForDateTimeField(): array
    {
        return [
            'first page' => [
                (new Result())
                    ->setItems([1, 2, 3])
                    ->setHasNext(true)
                    ->setHasPrevious(false)
                    ->setPreviousCursor('"2020-02-02 12:00:00","1"')
                    ->setNextCursor('"2020-02-02 12:00:01","3"'),
                (new Pager())
                    ->setLimit(3)
                    ->addOrderBy(new OrderingPair('created_at', true)),
            ],
            'second page' => [
                (new Result())
                    ->setItems([4, 5, 6])
                    ->setHasNext(true)
                    ->setHasPrevious(true)
                    ->setPreviousCursor('"2020-02-02 12:00:01","4"')
                    ->setNextCursor('"2020-02-02 12:00:02","6"'),
                (new Pager())
                    ->setLimit(3)
                    ->addOrderBy(new OrderingPair('created_at', true))
                    ->setAfter('"2020-02-02 12:00:01","3"'),
            ],
            'first page from second' => [
                (new Result())
                    ->setItems([1, 2, 3])
                    ->setHasNext(true)
                    ->setHasPrevious(false)
                    ->setPreviousCursor('"2020-02-02 12:00:00","1"')
                    ->setNextCursor('"2020-02-02 12:00:01","3"'),
                (new Pager())
                    ->setLimit(3)
                    ->addOrderBy(new OrderingPair('created_at', true))
                    ->setBefore('"2020-02-02 12:00:01","4"'),
            ],
            'desc order' => [
                (new Result())
                    ->setItems([60, 59, 58])
                    ->setHasNext(true)
                    ->setHasPrevious(false)
                    ->setPreviousCursor('"2020-02-02 12:00:29","60"')
                    ->setNextCursor('"2020-02-02 12:00:28","58"'),
                (new Pager())
                    ->setLimit(3)
                    ->addOrderBy(new OrderingPair('created_at', false)),
            ],
        ];
    }

    public function testGetResultForQueryWithCustomAccessor()
    {
        $entityManager = $this->createTestEntityManager();
        $this->createTestData($entityManager);

        $queryBuilder = $entityManager->createQueryBuilder()
            ->select('p')
            ->from(ParentTestEntity::class, 'p')
        ;

        $configuredQuery = (new ConfiguredQuery($queryBuilder))->addOrderingConfigurations([
            'custom' => (new OrderingConfiguration('p.id'))
                ->setAccessorClosure(function (ParentTestEntity $parentTestEntity) {
                    return 'prefix"' . $parentTestEntity->getId();
                })
                ->defaultAscending(),
        ]);

        $result = $this->resultProvider->getResultForQuery(
            $configuredQuery,
            (new Pager())->addOrderBy(new OrderingPair('custom'))->setLimit(1)
        );

        $this->assertSame('"prefix\\"1"', $result->getPreviousCursor());
    }

    public function testGetResultForQueryWithInvalidOrder()
    {
        $entityManager = $this->createTestEntityManager();
        $this->createTestData($entityManager);

        $queryBuilder = $entityManager->createQueryBuilder()
            ->select('p')
            ->from(ParentTestEntity::class, 'p')
        ;

        $configuredQuery = (new ConfiguredQuery($queryBuilder))->addOrderingConfigurations([
            'id' => new OrderingConfiguration('p.id', 'id'),
        ]);

        try {
            $this->resultProvider->getResultForQuery(
                $configuredQuery,
                (new Pager())->addOrderBy(new OrderingPair('undefined_field'))
            );
            $this->fail('Exception must have been thrown');

        } catch (InvalidOrderByException $exception) {
            $this->assertSame('undefined_field', $exception->getOrderBy());
        }
    }

    public function testGetResultForQueryWithTooLargeOffset()
    {
        $entityManager = $this->createTestEntityManager();
        $this->createTestData($entityManager);

        $queryBuilder = $entityManager->createQueryBuilder()
            ->select('p')
            ->from(ParentTestEntity::class, 'p')
        ;

        $configuredQuery = (new ConfiguredQuery($queryBuilder))->setMaximumOffset(10);

        $result = $this->resultProvider->getResultForQuery($configuredQuery, (new Pager())->setOffset(10));
        $this->assertSame(count($result->getItems()), 20);

        try {
            $this->resultProvider->getResultForQuery($configuredQuery, (new Pager())->setOffset(11));
            $this->fail('Exception must have been thrown');

        } catch (TooLargeOffsetException $exception) {
            $this->assertSame(10, $exception->getMaximumOffset());
            $this->assertSame(11, $exception->getGivenOffset());
        }
    }

    /**
     * @dataProvider getResultProviderForGetTotalCountForQuery
     * @param int $expectedResult
     * @param QueryBuilder $queryBuilder
     */
    public function testGetTotalCountForQuery($expectedResult, QueryBuilder $queryBuilder)
    {
        $configuredQuery = (new ConfiguredQuery($queryBuilder))->setTotalCountNeeded(false);

        $result = $this->resultProvider->getTotalCountForQuery($configuredQuery);
        $this->assertSame($expectedResult, $result);
    }

    public function testGetTotalCountForQueryThrowsExceptionWithMoreThanOneGroupByArgument()
    {
        $entityManager = $this->createTestEntityManager();
        $this->createTestData($entityManager);
        $queryBuilder = $entityManager->createQueryBuilder()
            ->select('p')
            ->from(ParentTestEntity::class, 'p')
            ->groupBy('p.groupKey', 'p.name')
        ;

        $configuredQuery = (new ConfiguredQuery($queryBuilder))->setTotalCountNeeded(false);

        $this->expectException(InvalidGroupByException::class);
        $this->resultProvider->getTotalCountForQuery($configuredQuery);
    }

    public function testGetTotalCountForQueryThrowsExceptionWithMoreThanOneGroupByExpression()
    {
        $entityManager = $this->createTestEntityManager();
        $this->createTestData($entityManager);
        $queryBuilder = $entityManager->createQueryBuilder()
            ->select('p')
            ->from(ParentTestEntity::class, 'p')
            ->groupBy('p.groupKey')
            ->addGroupBy('p.name')
        ;

        $configuredQuery = (new ConfiguredQuery($queryBuilder))->setTotalCountNeeded(false);

        $this->expectException(InvalidGroupByException::class);
        $this->resultProvider->getTotalCountForQuery($configuredQuery);
    }

    public function testGetTotalCountForQueryGetsCorrectCountWhenNoNullsAreInResult()
    {
        $entityManager = $this->createTestEntityManager();
        $this->createTestData($entityManager, 1);
        $queryBuilder = $entityManager->createQueryBuilder()
            ->select('p')
            ->from(ParentTestEntity::class, 'p')
            ->groupBy('p.groupKey')
        ;

        $configuredQuery = (new ConfiguredQuery($queryBuilder))->setTotalCountNeeded(false);
        $this->assertSame(30, $this->resultProvider->getTotalCountForQuery($configuredQuery));
    }

    public function getResultProviderForGetTotalCountForQuery(): array
    {
        $entityManager = $this->createTestEntityManager();
        $this->createTestData($entityManager);
        $queryBuilder1 = $entityManager->createQueryBuilder()
            ->select('p')
            ->from(ParentTestEntity::class, 'p')
        ;
        $queryBuilder2 = $entityManager->createQueryBuilder()
            ->select('p')
            ->from(ParentTestEntity::class, 'p')
            ->groupBy('p.groupKey')
        ;

        return [
            [
                30,
                $queryBuilder1,
            ],
            [
                11,
                (clone $queryBuilder1)->andWhere('p.name LIKE :name')->setParameter('name', 'P2%'),
            ],
            [
                4,
                $queryBuilder2,
            ],
        ];
    }
}
