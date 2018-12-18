<?php
declare(strict_types=1);

namespace Paysera\Pagination\Entity;

use RuntimeException;

class OrderingPair
{
    /**
     * @var string
     */
    private $orderBy;

    /**
     * @var bool|null
     */
    private $orderAscending;

    public function __construct(string $orderBy, bool $orderAscending = null)
    {
        $this->orderBy = $orderBy;
        $this->orderAscending = $orderAscending;
    }

    /**
     * @return string
     */
    public function getOrderBy(): string
    {
        return $this->orderBy;
    }

    /**
     * @param string $orderBy
     * @return $this
     */
    public function setOrderBy(string $orderBy): self
    {
        $this->orderBy = $orderBy;
        return $this;
    }

    /**
     * @return bool
     */
    public function isOrderingDirectionSet(): bool
    {
        return $this->orderAscending !== null;
    }

    /**
     * @return bool
     *
     * @throws RuntimeException if ordering direction was not set – use isOrderingDirectionSet beforehand
     */
    public function isOrderAscending(): bool
    {
        if ($this->orderAscending === null) {
            throw new RuntimeException('Ordering direction not set');
        }
        return $this->orderAscending;
    }

    /**
     * @param bool $orderAscending
     * @return $this
     */
    public function setOrderAscending(bool $orderAscending): self
    {
        $this->orderAscending = $orderAscending;
        return $this;
    }
}
