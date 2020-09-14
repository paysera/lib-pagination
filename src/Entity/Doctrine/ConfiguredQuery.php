<?php
declare(strict_types=1);

namespace Paysera\Pagination\Entity\Doctrine;

use Doctrine\ORM\QueryBuilder;
use Paysera\Pagination\Entity\OrderingConfiguration;
use Paysera\Pagination\Exception\InvalidOrderByException;
use InvalidArgumentException;
use RuntimeException;

/**
 * @deprecated Will be removed with next major version
 * @see ResultConfiguration
 */
class ConfiguredQuery
{
    /**
     * @var QueryBuilder
     */
    private $queryBuilder;

    /**
     * @var array|OrderingConfiguration[] associative, keys are strings for ordering fields
     */
    private $orderingConfigurations;

    /**
     * @var bool
     */
    private $totalCountNeeded;

    /**
     * @var int|null
     */
    private $maximumOffset;

    /**
     * @var callable
     */
    private $itemTransformer;

    public function __construct(QueryBuilder $queryBuilder)
    {
        $this->queryBuilder = $queryBuilder;
        $this->orderingConfigurations = [];
        $this->totalCountNeeded = false;
    }

    public function addOrderingConfiguration(string $orderBy, OrderingConfiguration $configuration): self
    {
        if ($configuration->getAccessorPath() === null && $configuration->getAccessorClosure() === null) {
            throw new InvalidArgumentException(
                'Must set either accessorPath or accessorClosure for every OrderingConfiguration'
            );
        }
        $this->orderingConfigurations[$orderBy] = $configuration;
        return $this;
    }

    /**
     * @param array|OrderingConfiguration[] $orderingConfigurations array of `orderBy => OrderingConfiguration` pairs
     * @return ConfiguredQuery
     */
    public function addOrderingConfigurations(array $orderingConfigurations): self
    {
        foreach ($orderingConfigurations as $orderBy => $configuration) {
            $this->addOrderingConfiguration($orderBy, $configuration);
        }

        return $this;
    }

    public function getOrderingConfigurationFor(string $orderBy): OrderingConfiguration
    {
        if (!isset($this->orderingConfigurations[$orderBy])) {
            throw new InvalidOrderByException($orderBy);
        }

        return $this->orderingConfigurations[$orderBy];
    }

    /**
     * @return QueryBuilder
     */
    public function getQueryBuilder(): QueryBuilder
    {
        return $this->queryBuilder;
    }

    /**
     * @return bool
     */
    public function isTotalCountNeeded(): bool
    {
        return $this->totalCountNeeded;
    }

    /**
     * @param bool $totalCountNeeded
     * @return $this
     */
    public function setTotalCountNeeded(bool $totalCountNeeded): self
    {
        $this->totalCountNeeded = $totalCountNeeded;
        return $this;
    }

    /**
     * @param int $maximumOffset
     * @return $this
     */
    public function setMaximumOffset(int $maximumOffset): self
    {
        $this->maximumOffset = $maximumOffset;
        return $this;
    }

    public function hasMaximumOffset(): bool
    {
        return $this->maximumOffset !== null;
    }

    /**
     * @return int
     * @throws RuntimeException if maximum offset was not set. Check with hasMaximumOffset beforehand
     */
    public function getMaximumOffset(): int
    {
        if ($this->maximumOffset === null) {
            throw new RuntimeException('Maximum offset was not set');
        }

        return $this->maximumOffset;
    }

    /**
     * @return callable|null
     */
    public function getItemTransformer()
    {
        return $this->itemTransformer;
    }

    public function setItemTransformer(callable $itemTransformer): self
    {
        $this->itemTransformer = $itemTransformer;

        return $this;
    }
}
