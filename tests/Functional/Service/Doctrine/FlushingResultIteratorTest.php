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

        $resultIterator = new FlushingResultIterator(
            new ResultProvider(
                new QueryAnalyser(),
                new CursorBuilder(PropertyAccess::createPropertyAccessor())
            ),
            new TestLogger(),
            2,
            $entityManager
        );
        $queryBuilder = $entityManager->createQueryBuilder()
            ->select('p')
            ->from(ParentTestEntity::class, 'p')
        ;

        $firstItem = null;
        $firstItemManagedOnTheSecondPage = null;
        $iterated = 0;
        foreach ($resultIterator->iterate(new ConfiguredQuery($queryBuilder)) as $parent) {
            $parent->setName($parent->getName() . '-seen');
            $iterated++;
            if ($firstItem === null) {
                $firstItem = $parent;
            }
            if ($iterated === 3) {
                $firstItemManagedOnTheSecondPage = $entityManager->contains($firstItem);
            }
        }

        $storedNames = $entityManager->createQueryBuilder()
            ->select('p.name')
            ->from(ParentTestEntity::class, 'p')
            ->orderBy('p.id')
            ->getQuery()
            ->getScalarResult()
        ;
        $this->assertSame(5, $iterated);
        $this->assertFalse($firstItemManagedOnTheSecondPage);
        $this->assertSame(
            ['P0-seen', 'P1-seen', 'P2-seen', 'P3-seen', 'P4-seen'],
            array_column($storedNames, 'name')
        );
    }
}
