<?php
declare(strict_types=1);

namespace Paysera\Pagination\Exception;

class InvalidOrderByException extends PaginationException
{
    /**
     * @var string
     */
    private $orderBy;

    public function __construct(string $orderBy)
    {
        parent::__construct(sprintf('Unsupported order-by field: "%s"', $orderBy));
        $this->orderBy = $orderBy;
    }

    /**
     * @return string
     */
    public function getOrderBy(): string
    {
        return $this->orderBy;
    }
}
