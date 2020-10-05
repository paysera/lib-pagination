<?php
declare(strict_types=1);

namespace Paysera\Pagination\Entity\Doctrine;

use Doctrine\ORM\QueryBuilder;
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
     * @var string
     */
    private $rootAlias;

    /**
     * @var OrderingConfiguration[]
     */
    private $orderingConfigurations;

    /**
     * @var callable|null
     */
    private $queryModifier;

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
     * @return string
     */
    public function getRootAlias(): string
    {
        return $this->rootAlias;
    }

    /**
     * @param string $rootAlias
     * @return $this
     */
    public function setRootAlias(string $rootAlias): self
    {
        $this->rootAlias = $rootAlias;
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

    /**
     * @return callable|null
     */
    public function getQueryModifier()
    {
        return $this->queryModifier;
    }

    public function setQueryModifier(callable $queryModifier): self
    {
        $this->queryModifier = $queryModifier;

        return $this;
    }
}
