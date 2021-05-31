<?php
declare(strict_types=1);

namespace Paysera\Pagination\Entity\ODM;

use Doctrine\ODM\MongoDB\Query\Builder as QueryBuilder;
use Paysera\Pagination\Entity\OrderingConfiguration;

/**
 * @internal
 */
class AnalysedQuery
{
    /**
     * @var QueryBuilder
     */
    private $queryBuilder;

    /**
     * @var OrderingConfiguration[]
     */
    private $orderingConfigurations;

    /**
     * @return QueryBuilder
     */
    public function cloneQueryBuilder(): QueryBuilder
    {
        return clone $this->queryBuilder;
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @return $this
     */
    public function setQueryBuilder(QueryBuilder $queryBuilder): self
    {
        $this->queryBuilder = $queryBuilder;
        return $this;
    }

    /**
     * @return OrderingConfiguration[]
     */
    public function getOrderingConfigurations(): array
    {
        return $this->orderingConfigurations;
    }

    /**
     * @param OrderingConfiguration[] $orderingConfigurations
     * @return $this
     */
    public function setOrderingConfigurations(array $orderingConfigurations): self
    {
        $this->orderingConfigurations = $orderingConfigurations;
        return $this;
    }
}
