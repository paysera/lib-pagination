<?php
declare(strict_types=1);

namespace Paysera\Pagination\Entity;

use Closure;
use RuntimeException;

class OrderingConfiguration
{
    /**
     * @var string
     */
    private $orderByExpression;

    /**
     * @var string|null
     */
    private $accessorPath;

    /**
     * @var Closure|null
     */
    private $accessorClosure;

    /**
     * @var bool
     */
    private $orderAscending;

    public function __construct(string $orderByExpression, string $accessorPath = null)
    {
        $this->orderByExpression = $orderByExpression;
        $this->accessorPath = $accessorPath;
        $this->orderAscending = false;
    }

    /**
     * @return string
     */
    public function getOrderByExpression(): string
    {
        return $this->orderByExpression;
    }

    /**
     * @return string|null
     */
    public function getAccessorPath()
    {
        return $this->accessorPath;
    }

    /**
     * @param string $accessorPath
     * @return $this
     */
    public function setAccessorPath(string $accessorPath): self
    {
        if ($this->accessorClosure !== null) {
            throw new RuntimeException('Cannot set both accessor path and closure, choose one');
        }
        $this->accessorPath = $accessorPath;
        return $this;
    }

    /**
     * @return Closure|null
     */
    public function getAccessorClosure()
    {
        return $this->accessorClosure;
    }

    /**
     * @param Closure $accessorClosure
     * @return $this
     */
    public function setAccessorClosure(Closure $accessorClosure): self
    {
        if ($this->accessorPath !== null) {
            throw new RuntimeException('Cannot set both accessor path and closure, choose one');
        }
        $this->accessorClosure = $accessorClosure;
        return $this;
    }

    /**
     * @return bool
     */
    public function isOrderAscending(): bool
    {
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

    /**
     * @return $this
     */
    public function defaultAscending(): self
    {
        return $this->setOrderAscending(true);
    }

    /**
     * @return $this
     */
    public function defaultDescending(): self
    {
        return $this->setOrderAscending(false);
    }
}
