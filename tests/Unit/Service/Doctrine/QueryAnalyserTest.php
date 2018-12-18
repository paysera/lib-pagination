<?php
declare(strict_types=1);

namespace Paysera\Pagination\Tests\Unit\Service\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Andx;
use Doctrine\ORM\QueryBuilder;
use Paysera\Pagination\Entity\Doctrine\ConfiguredQuery;
use Paysera\Pagination\Entity\Pager;
use Paysera\Pagination\Service\Doctrine\QueryAnalyser;
use PHPUnit\Framework\TestCase;

class QueryAnalyserTest extends TestCase
{
    /**
     * @dataProvider providerForInvalidData
     * @expectedException \InvalidArgumentException
     * @param QueryBuilder $queryBuilder
     */
    public function testAnalyseQueryWithInvalidData(QueryBuilder $queryBuilder)
    {
        $analyser = new QueryAnalyser();

        $configuredQuery = new ConfiguredQuery($queryBuilder);
        $pager = new Pager();

        $analyser->analyseQuery($configuredQuery, $pager);
    }

    public function providerForInvalidData()
    {
        return [
            'Without select part' => [
                $this->createQueryBuilder(),
            ],
            'Invalid select part' => [
                $this->createQueryBuilder()->add('select', new Andx()),
            ],
            'Having no parts' => [
                $this->createQueryBuilder()->select([]),
            ],
            'Select expression' => [
                $this->createQueryBuilder()->select('count(a)'),
            ],
            'Few select roots' => [
                $this->createQueryBuilder()->select('a, b, c'),
            ],
        ];
    }

    private function createQueryBuilder()
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $this->createMock(EntityManagerInterface::class);

        return new QueryBuilder($entityManager);
    }
}
