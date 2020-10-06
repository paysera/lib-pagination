<?php
declare(strict_types=1);

namespace Paysera\Pagination\Service\Doctrine;

use Doctrine\ORM\Query\Expr\Select;
use Doctrine\ORM\QueryBuilder;
use Paysera\Pagination\Entity\Doctrine\AnalysedQuery;
use Paysera\Pagination\Entity\OrderingConfiguration;
use Paysera\Pagination\Entity\Doctrine\ConfiguredQuery;
use Paysera\Pagination\Entity\OrderingPair;
use Paysera\Pagination\Entity\Pager;
use InvalidArgumentException;
use Paysera\Pagination\Exception\TooLargeOffsetException;

class QueryAnalyser
{
    const ID_FIELD = 'id';

    /**
     * @internal
     * @param ConfiguredQuery $configuredQuery
     * @param Pager $pager
     * @return AnalysedQuery
     */
    public function analyseQuery(ConfiguredQuery $configuredQuery, Pager $pager): AnalysedQuery
    {
        $analysedQuery = $this->analyseQueryWithoutPager($configuredQuery);
        if ($configuredQuery->getQueryModifier() !== null) {
            $analysedQuery->setQueryModifier($configuredQuery->getQueryModifier());
        }

        if (
            $pager->getOffset() !== null
            && $configuredQuery->hasMaximumOffset()
            && $pager->getOffset() > $configuredQuery->getMaximumOffset()
        ) {
            throw new TooLargeOffsetException($configuredQuery->getMaximumOffset(), $pager->getOffset());
        }

        $orderingConfigurations = $this->mapToOrderingConfigurations(
            $configuredQuery,
            $analysedQuery->getRootAlias(),
            $pager->getOrderingPairs()
        );

        return $analysedQuery
            ->setOrderingConfigurations($orderingConfigurations)
        ;
    }

    /**
     * @internal
     * @param ConfiguredQuery $configuredQuery
     * @return AnalysedQuery
     */
    public function analyseQueryWithoutPager(ConfiguredQuery $configuredQuery): AnalysedQuery
    {
        $queryBuilder = $configuredQuery->getQueryBuilder();
        $rootAlias = $this->getRootAlias($queryBuilder);
        if ($rootAlias === null) {
            throw new InvalidArgumentException('Invalid QueryBuilder passed - cannot resolve root select alias');
        }

        $analysedQuery = (new AnalysedQuery())
            ->setQueryBuilder($queryBuilder)
            ->setRootAlias($rootAlias)
        ;
        if ($configuredQuery->getQueryModifier() !== null) {
            $analysedQuery->setQueryModifier($configuredQuery->getQueryModifier());
        }

        return $analysedQuery;
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @return null|string
     */
    private function getRootAlias(QueryBuilder $queryBuilder)
    {
        $selectDqlParts = $queryBuilder->getDQLPart('select');
        if (!isset($selectDqlParts[0])) {
            return null;
        }
        $select = $selectDqlParts[0];
        if (!$select instanceof Select) {
            return null;
        }
        $selectParts = $select->getParts();
        if (!isset($selectParts[0]) || !is_string($selectParts[0])) {
            return null;
        }
        if (mb_strpos($selectParts[0], '(') !== false) {
            return null;
        }
        if (mb_strpos($selectParts[0], ',') !== false) {
            return null;
        }

        return $selectParts[0];
    }

    /**
     * @param ConfiguredQuery $configuredQuery
     * @param string $rootAlias
     * @param array|OrderingPair[] $orderingPairs
     * @return array|OrderingConfiguration[]
     */
    private function mapToOrderingConfigurations(
        ConfiguredQuery $configuredQuery,
        string $rootAlias,
        array $orderingPairs
    ): array {
        $orderingConfigurations = [];
        $idIncluded = false;
        $defaultAscending = null;
        $orderByIdExpression = sprintf('%s.%s', $rootAlias, self::ID_FIELD);

        foreach ($orderingPairs as $orderingPair) {
            $orderingConfiguration = $configuredQuery->getOrderingConfigurationFor($orderingPair->getOrderBy());

            if ($orderingPair->isOrderingDirectionSet()) {
                $orderingConfiguration->setOrderAscending($orderingPair->isOrderAscending());
            }

            if ($orderingConfiguration->getOrderByExpression() === $orderByIdExpression) {
                $idIncluded = true;
            }

            if ($defaultAscending === null) {
                $defaultAscending = $orderingConfiguration->isOrderAscending();
            }

            $orderingConfigurations[] = $orderingConfiguration;
        }

        if ($idIncluded) {
            return $orderingConfigurations;
        }

        $orderingConfigurations[] = (new OrderingConfiguration($orderByIdExpression, self::ID_FIELD))
            ->setOrderAscending($defaultAscending ?? false)
        ;

        return $orderingConfigurations;
    }
}
