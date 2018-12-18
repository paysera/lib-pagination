<?php
declare(strict_types=1);

namespace Paysera\Pagination\Entity;

class Pager
{
    /**
     * @var array
     */
    private $orderingPairs;

    /**
     * null means no limit
     * @var int|null
     */
    private $limit;

    /**
     * Items to skip for paging
     * @var int|null
     */
    private $offset;

    /**
     * Used instead of offset for cursor-based paging
     * @var string|null
     */
    private $after;

    /**
     * Used instead of offset for cursor-based paging
     * @var string
     */
    private $before;

    public function __construct()
    {
        $this->orderingPairs = [];
    }

    /**
     * @param OrderingPair $orderingPair
     * @return $this
     */
    public function addOrderBy(OrderingPair $orderingPair): self
    {
        $this->orderingPairs[] = $orderingPair;
        return $this;
    }

    /**
     * @param OrderingPair[]|array $orderingPairs
     * @return $this
     */
    public function setOrderingPairs(array $orderingPairs): self
    {
        $this->orderingPairs = $orderingPairs;
        return $this;
    }

    /**
     * @return array
     */
    public function getOrderingPairs(): array
    {
        return $this->orderingPairs;
    }

    /**
     * @param int|null $offset
     * @return $this
     */
    public function setOffset($offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getOffset()
    {
        return $this->offset;
    }

    /**
     * @param int|null $limit
     * @return $this
     */
    public function setLimit($limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getLimit()
    {
        return $this->limit;
    }

    /**
     * @return string|null
     */
    public function getAfter()
    {
        return $this->after;
    }

    /**
     * @param string|null $after
     * @return $this
     */
    public function setAfter($after): self
    {
        $this->after = $after;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getBefore()
    {
        return $this->before;
    }

    /**
     * @param string|null $before
     * @return $this
     */
    public function setBefore($before): self
    {
        $this->before = $before;
        return $this;
    }
}
