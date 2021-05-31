<?php
declare(strict_types=1);

namespace Paysera\Pagination\Service\ODM;

use Paysera\Pagination\Entity\ODM\AnalysedQuery;
use Paysera\Pagination\Entity\OrderingConfiguration;
use Paysera\Pagination\Entity\ODM\ConfiguredQuery;
use Paysera\Pagination\Entity\OrderingPair;
use Paysera\Pagination\Entity\Pager;
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
    public function analyseQuery(ConfiguredQuery $configuredQuery, Pager $pager)
    {
        $analysedQuery = $this->analyseQueryWithoutPager($configuredQuery);

        if (
            $pager->getOffset() !== null
            && $configuredQuery->hasMaximumOffset()
            && $pager->getOffset() > $configuredQuery->getMaximumOffset()
        ) {
            throw new TooLargeOffsetException($configuredQuery->getMaximumOffset(), $pager->getOffset());
        }

        $orderingConfigurations = $this->mapToOrderingConfigurations(
            $configuredQuery,
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
    public function analyseQueryWithoutPager(ConfiguredQuery $configuredQuery)
    {
        $queryBuilder = $configuredQuery->getQueryBuilder();

        return (new AnalysedQuery())
            ->setQueryBuilder($queryBuilder)
        ;
    }

    /**
     * @param ConfiguredQuery $configuredQuery
     * @param array|OrderingPair[] $orderingPairs
     * @return array|OrderingConfiguration[]
     */
    private function mapToOrderingConfigurations(ConfiguredQuery $configuredQuery, array $orderingPairs)
    {
        $orderingConfigurations = [];
        $idIncluded = false;
        $defaultAscending = null;
        $orderByIdExpression = self::ID_FIELD;

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
