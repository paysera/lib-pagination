<?php
declare(strict_types=1);

namespace Paysera\Pagination\Tests\Functional\Service\Doctrine;

use Paysera\Pagination\Entity\Doctrine\ConfiguredQuery;
use Paysera\Pagination\Service\CursorBuilder;
use Paysera\Pagination\Service\Doctrine\FlushingResultIterator;
use Paysera\Pagination\Service\Doctrine\QueryAnalyser;
use Paysera\Pagination\Service\Doctrine\ResultProvider;
use Paysera\Pagination\Tests\Functional\Fixtures\ParentTestEntity;
use Paysera\Pagination\Tests\Functional\Fixtures\TestLogger;
use Symfony\Component\PropertyAccess\PropertyAccess;

class FlushingResultIteratorTest extends DoctrineTestCase
{
    public function testFlushesAndClearsTheEntityManagerAfterEveryPage()
    {
        $entityManager = $this->createTestEntityManager();
        for ($index = 0; $index < 5; $index++) {
            $entityManager->persist((new ParentTestEntity())->setName(sprintf('P%s', $index)));
        }
        $entityManager->flush();
        $entityManager->clear();
        $pageSize = 2;

        $resultIterator = new FlushingResultIterator(
            new ResultProvider(
                new QueryAnalyser(),
                new CursorBuilder(PropertyAccess::createPropertyAccessor())
            ),
            new TestLogger(),
            $pageSize,
            $entityManager
        );
        $queryBuilder = $entityManager->createQueryBuilder()
            ->select('p')
            ->from(ParentTestEntity::class, 'p')
        ;

        $iterated = [];
        $firstItemManagedOnTheSecondPage = null;
        foreach ($resultIterator->iterate(new ConfiguredQuery($queryBuilder)) as $parent) {
            $parent->setName($parent->getName() . '-seen');
            $iterated[] = $parent;
            if (count($iterated) === $pageSize + 1) {
                $firstItemManagedOnTheSecondPage = $entityManager->contains($iterated[0]);
            }
        }

        $this->assertCount(5, $iterated);
        $this->assertFalse($firstItemManagedOnTheSecondPage);
        $this->assertSame([], array_filter($iterated, [$entityManager, 'contains']));
        $this->assertSame(
            ['P0-seen', 'P1-seen', 'P2-seen', 'P3-seen', 'P4-seen'],
            $entityManager->createQueryBuilder()
                ->select('p.name')
                ->from(ParentTestEntity::class, 'p')
                ->orderBy('p.id')
                ->getQuery()
                ->getSingleColumnResult()
        );
    }
}
