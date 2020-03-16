<?php
declare(strict_types=1);

namespace Paysera\Pagination\Service\Doctrine;

use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\Query\Expr\Andx;
use Doctrine\ORM\Query\Expr\Orx;
use Doctrine\ORM\Query\Expr\GroupBy;
use Doctrine\ORM\Query\Expr\Comparison;
use Doctrine\ORM\QueryBuilder;
use Paysera\Pagination\Entity\Doctrine\AnalysedQuery;
use Paysera\Pagination\Entity\OrderingConfiguration;
use Paysera\Pagination\Entity\Doctrine\ConfiguredQuery;
use Paysera\Pagination\Entity\Pager;
use Paysera\Pagination\Entity\Result;
use Paysera\Pagination\Exception\InvalidGroupByException;
use Paysera\Pagination\Service\CursorBuilderInterface;

class ResultProvider
{
    private $queryAnalyser;
    private $cursorBuilder;

    public function __construct(QueryAnalyser $queryAnalyser, CursorBuilderInterface $cursorBuilder)
    {
        $this->queryAnalyser = $queryAnalyser;
        $this->cursorBuilder = $cursorBuilder;
    }

    public function getResultForQuery(ConfiguredQuery $configuredQuery, Pager $pager): Result
    {
        $analysedQuery = $this->queryAnalyser->analyseQuery($configuredQuery, $pager);

        $result = $this->buildResult($analysedQuery, $pager);

        if ($configuredQuery->isTotalCountNeeded()) {
            $totalCount = $this->calculateTotalCount($pager, count($result->getItems()));
            if ($totalCount === null) {
                $totalCount = $this->findCount($analysedQuery);
            }
            $result->setTotalCount($totalCount);
        }

        return $result;
    }

    public function getTotalCountForQuery(ConfiguredQuery $configuredQuery): int
    {
        $analysedQuery = $this->queryAnalyser->analyseQueryWithoutPager($configuredQuery);

        return $this->findCount($analysedQuery);
    }

    private function buildResult(AnalysedQuery $analysedQuery, Pager $pager)
    {
        $items = $this->findItems($analysedQuery, $pager);

        if (count($items) === 0) {
            return $this->buildResultForEmptyItems($analysedQuery, $pager);
        }

        $orderingConfigurations = $analysedQuery->getOrderingConfigurations();
        $previousCursor = $this->cursorBuilder->getCursorFromItem($items[0], $orderingConfigurations);
        $nextCursor = $this->cursorBuilder->getCursorFromItem($items[count($items) - 1], $orderingConfigurations);

        return (new Result())
            ->setItems($items)
            ->setPreviousCursor($previousCursor)
            ->setNextCursor($nextCursor)
            ->setHasPrevious($this->existsBeforeCursor($previousCursor, $analysedQuery))
            ->setHasNext($this->existsAfterCursor($nextCursor, $analysedQuery))
        ;
    }

    private function findItems(AnalysedQuery $analysedQuery, Pager $pager)
    {
        $pagedQueryBuilder = $this->pageQueryBuilder($analysedQuery, $pager);
        $query = $pagedQueryBuilder->getQuery();
        $items = $query->getResult();

        if ($pager->getBefore() !== null) {
            return array_reverse($items);
        }
        return $items;
    }

    private function pageQueryBuilder(AnalysedQuery $analysedQuery, Pager $pager)
    {
        $queryBuilder = $analysedQuery->cloneQueryBuilder();

        if ($pager->getLimit() !== null) {
            $queryBuilder->setMaxResults($pager->getLimit());
        }

        $orderingConfigurations = $analysedQuery->getOrderingConfigurations();
        if ($pager->getBefore() !== null) {
            $orderingConfigurations = $this->reverseOrderingDirection($orderingConfigurations);
        }

        $this->applyOrdering($queryBuilder, $orderingConfigurations);

        if ($pager->getOffset() !== null) {
            $this->applyOffset($queryBuilder, $pager->getOffset());
        } elseif ($pager->getBefore() !== null) {
            $this->applyBefore($queryBuilder, $pager->getBefore(), $analysedQuery);
        } elseif ($pager->getAfter() !== null) {
            $this->applyAfter($queryBuilder, $pager->getAfter(), $analysedQuery);
        }

        return $queryBuilder;
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param array|OrderingConfiguration[] $orderingConfigurations
     */
    private function applyOrdering(QueryBuilder $queryBuilder, array $orderingConfigurations)
    {
        foreach ($orderingConfigurations as $orderingConfiguration) {
            $queryBuilder->addOrderBy(
                $orderingConfiguration->getOrderByExpression(),
                $orderingConfiguration->isOrderAscending() ? 'ASC' : 'DESC'
            );
        }
    }

    private function applyOffset(QueryBuilder $queryBuilder, int $offset)
    {
        $queryBuilder->setFirstResult($offset);
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param string $after
     * @param AnalysedQuery $analysedQuery
     */
    private function applyAfter(QueryBuilder $queryBuilder, string $after, AnalysedQuery $analysedQuery)
    {
        $this->applyCursor($queryBuilder, $after, $analysedQuery, false);
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param string $after
     * @param AnalysedQuery $analysedQuery
     */
    private function applyBefore(QueryBuilder $queryBuilder, string $after, AnalysedQuery $analysedQuery)
    {
        $this->applyCursor($queryBuilder, $after, $analysedQuery, true);
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param string $cursor
     * @param AnalysedQuery $analysedQuery
     * @param bool $invert
     */
    private function applyCursor(QueryBuilder $queryBuilder, string $cursor, AnalysedQuery $analysedQuery, bool $invert)
    {
        $orderingConfigurations = $analysedQuery->getOrderingConfigurations();
        $parsedCursor = $this->cursorBuilder->parseCursor($cursor, count($orderingConfigurations));

        $expr = new Expr();
        $whereClause = new Orx();
        $previousConditions = new Andx();
        foreach ($orderingConfigurations as $index => $orderingConfiguration) {
            $useLargerThan = $orderingConfiguration->isOrderAscending();
            if ($invert) {
                $useLargerThan = !$useLargerThan;
            }
            $sign = $useLargerThan ? '>' : '<';
            if ($parsedCursor->isCursoredItemIncluded() && $index === count($orderingConfigurations) - 1) {
                $sign .= '=';
            }

            $currentCondition = clone $previousConditions;
            $currentCondition->add(new Comparison(
                $orderingConfiguration->getOrderByExpression(),
                $sign,
                $expr->literal($parsedCursor->getElementAtIndex($index))
            ));

            $whereClause->add($currentCondition);

            $previousConditions->add(new Comparison(
                $orderingConfiguration->getOrderByExpression(),
                '=',
                $expr->literal($parsedCursor->getElementAtIndex($index))
            ));
        }

        $queryBuilder->andWhere($whereClause);
    }

    private function buildResultForEmptyItems(AnalysedQuery $analysedQuery, Pager $pager): Result
    {
        if ($pager->getLimit() === 0) {
            return $this->buildResultForZeroLimit($analysedQuery, $pager);

        } elseif ($pager->getBefore() !== null) {
            $nextCursor = $this->cursorBuilder->invertCursorInclusion($pager->getBefore());
            return (new Result())
                ->setPreviousCursor($pager->getBefore())
                ->setNextCursor($nextCursor)
                ->setHasPrevious(false)
                ->setHasNext($this->existsAfterCursor($nextCursor, $analysedQuery))
            ;

        } elseif ($pager->getAfter() !== null) {
            $previousCursor = $this->cursorBuilder->invertCursorInclusion($pager->getAfter());
            return (new Result())
                ->setPreviousCursor($previousCursor)
                ->setNextCursor($pager->getAfter())
                ->setHasPrevious($this->existsBeforeCursor($previousCursor, $analysedQuery))
                ->setHasNext(false)
            ;

        } elseif ($pager->getOffset() !== null && $pager->getOffset() > 0) {
            return $this->buildResultForTooLargeOffset($analysedQuery);

        }

        return (new Result())
            ->setHasPrevious(false)
            ->setHasNext(false)
        ;
    }

    private function buildResultForZeroLimit(AnalysedQuery $analysedQuery, Pager $zeroLimitPager): Result
    {
        $pager = (clone $zeroLimitPager)->setLimit(1);
        $items = $this->findItems($analysedQuery, $pager);

        if (count($items) === 0) {
            return $this->buildResultForEmptyItems($analysedQuery, $pager);
        }

        $orderingConfigurations = $analysedQuery->getOrderingConfigurations();
        $previousCursor = $this->cursorBuilder->getCursorFromItem($items[0], $orderingConfigurations);
        $nextCursor = $this->cursorBuilder->buildCursorWithIncludedItem($previousCursor);

        return (new Result())
            ->setPreviousCursor($previousCursor)
            ->setNextCursor($nextCursor)
            ->setHasPrevious($this->existsBeforeCursor($previousCursor, $analysedQuery))
            ->setHasNext(true)
        ;

    }

    private function buildResultForTooLargeOffset(AnalysedQuery $analysedQuery): Result
    {
        $result = (new Result())->setHasNext(false);

        $pagerForLastElement = (new Pager())->setLimit(1);
        $modifiedAnalysedQuery = (clone $analysedQuery)->setOrderingConfigurations(
            $this->reverseOrderingDirection($analysedQuery->getOrderingConfigurations())
        );
        $items = $this->findItems($modifiedAnalysedQuery, $pagerForLastElement);
        if (count($items) === 0) {
            return $result->setHasPrevious(false);
        }

        $lastItemCursor = $this->cursorBuilder->getCursorFromItem($items[0], $modifiedAnalysedQuery->getOrderingConfigurations());
        return $result
            ->setHasPrevious(true)
            ->setPreviousCursor($this->cursorBuilder->buildCursorWithIncludedItem($lastItemCursor))
            ->setNextCursor($lastItemCursor)
        ;
    }

    /**
     * @param array|OrderingConfiguration[] $orderingConfigurations
     * @return array|OrderingConfiguration[]
     */
    private function reverseOrderingDirection(array $orderingConfigurations): array
    {
        $reversedOrderingConfigurations = [];
        foreach ($orderingConfigurations as $orderingConfiguration) {
            $reversedOrderingConfigurations[] = (clone $orderingConfiguration)
                ->setOrderAscending(!$orderingConfiguration->isOrderAscending())
            ;
        }
        return $reversedOrderingConfigurations;
    }

    private function existsBeforeCursor(string $previousCursor, AnalysedQuery $analysedQuery)
    {
        $nextPager = (new Pager())
            ->setBefore($previousCursor)
            ->setLimit(1)
        ;
        return count($this->findItems($analysedQuery, $nextPager)) > 0;
    }

    private function existsAfterCursor(string $nextCursor, AnalysedQuery $analysedQuery)
    {
        $nextPager = (new Pager())
            ->setAfter($nextCursor)
            ->setLimit(1)
        ;
        return count($this->findItems($analysedQuery, $nextPager)) > 0;
    }

    private function calculateTotalCount(Pager $filter, int $resultCount)
    {
        if (
            $filter->getOffset() !== null
            && ($filter->getLimit() === null || $resultCount < $filter->getLimit())
            && ($resultCount !== 0 || $filter->getOffset() === 0)
        ) {
            return $resultCount + $filter->getOffset();
        }

        return null;
    }

    private function findCount(AnalysedQuery $analysedQuery): int
    {
        $countQueryBuilder = $analysedQuery->cloneQueryBuilder();
        $groupByColumn = $this->getSingleValidGroupByColumn($countQueryBuilder);

        if ($groupByColumn !== null) {
            return $this->findCountWithGroupBy($groupByColumn, $analysedQuery);
        }

        $countQueryBuilder->select(sprintf('count(%s)', $analysedQuery->getRootAlias()));
        return (int)$countQueryBuilder->getQuery()->getSingleScalarResult();
    }

    private function findCountWithGroupBy(string $groupByColumn, AnalysedQuery $analysedQuery): int
    {
        $countQueryBuilder = $analysedQuery->cloneQueryBuilder();
        $countQueryBuilder
            ->resetDQLPart('groupBy')
            ->select(sprintf('count(distinct %s)', $groupByColumn))
        ;

        $nullQueryBuilder = $analysedQuery->cloneQueryBuilder()
            ->resetDQLPart('groupBy')
            ->select($analysedQuery->getRootAlias())
            ->setMaxResults(1)
            ->andWhere($groupByColumn . ' is null')
        ;

        $nonNullCount = (int)$countQueryBuilder->getQuery()->getSingleScalarResult();
        $nullExists = count($nullQueryBuilder->getQuery()->getScalarResult());

        return $nonNullCount + $nullExists;
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @return string|null
     */
    private function getSingleValidGroupByColumn(QueryBuilder $queryBuilder)
    {
        /** @var GroupBy[] $groupByParts */
        $groupByParts = $queryBuilder->getDQLPart('groupBy');

        if (count($groupByParts) === 0) {
            return null;
        }

        if (count($groupByParts) > 1) {
            $groupNames = array_map(
                function (GroupBy $groupBy) { return $groupBy->getParts()[0]; },
                $groupByParts
            );
            throw new InvalidGroupByException(implode(', ', $groupNames));
        }

        if (count($groupByParts) === 1 && count($groupByParts[0]->getParts()) > 1) {
            throw new InvalidGroupByException(implode(', ', $groupByParts[0]->getParts()));
        }

        return $groupByParts[0]->getParts()[0];
    }
}
