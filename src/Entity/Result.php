<?php
declare(strict_types=1);

namespace Paysera\Pagination\Entity;

use Traversable;
use ArrayIterator;
use IteratorAggregate;

class Result implements IteratorAggregate
{
    /**
     * @var int|null
     */
    private $totalCount;

    /**
     * @var bool|null
     */
    private $hasNext;

    /**
     * @var bool|null
     */
    private $hasPrevious;

    /**
     * @var string|null
     */
    private $nextCursor;

    /**
     * @var string|null
     */
    private $previousCursor;

    /**
     * @var array
     */
    private $items;

    public function __construct()
    {
        $this->items = [];
    }

    /**
     * Sets totalCount
     *
     * @param int|null $totalCount
     *
     * @return $this
     */
    public function setTotalCount(int $totalCount = null): self
    {
        $this->totalCount = $totalCount;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getTotalCount()
    {
        return $this->totalCount;
    }

    /**
     * @return bool|null
     */
    public function hasNext()
    {
        return $this->hasNext;
    }

    /**
     * @param bool|null $hasNext
     * @return $this
     */
    public function setHasNext($hasNext): self
    {
        $this->hasNext = $hasNext;
        return $this;
    }

    /**
     * @return bool|null
     */
    public function hasPrevious()
    {
        return $this->hasPrevious;
    }

    /**
     * @param bool|null $hasPrevious
     * @return $this
     */
    public function setHasPrevious($hasPrevious): self
    {
        $this->hasPrevious = $hasPrevious;
        return $this;
    }

    /**
     * @return null|string
     */
    public function getNextCursor()
    {
        return $this->nextCursor;
    }

    /**
     * @param null|string $nextCursor
     * @return $this
     */
    public function setNextCursor($nextCursor): self
    {
        $this->nextCursor = $nextCursor;
        return $this;
    }

    /**
     * @return null|string
     */
    public function getPreviousCursor()
    {
        return $this->previousCursor;
    }

    /**
     * @param null|string $previousCursor
     * @return $this
     */
    public function setPreviousCursor($previousCursor): self
    {
        $this->previousCursor = $previousCursor;
        return $this;
    }

    /**
     * @param array $items
     *
     * @return $this
     */
    public function setItems(array $items): self
    {
        $this->items = $items;

        return $this;
    }

    /**
     * @return array
     */
    public function getItems()
    {
        return $this->items;
    }

    /**
     * @param mixed $item
     *
     * @return $this
     */
    public function addItem($item): self
    {
        $this->items[] = $item;
        return $this;
    }

    /**
     * Retrieve an external iterator
     *
     * @return Traversable
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }
}
