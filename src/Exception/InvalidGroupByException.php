<?php
declare(strict_types=1);

namespace Paysera\Pagination\Exception;

class InvalidGroupByException extends PaginationException
{
    /**
     * @var string
     */
    private $groupBy;

    public function __construct(string $groupBy)
    {
        parent::__construct(sprintf(
            'Calculating total-count only supported with single group-by, instead provided: "%s"',
            $groupBy
        ));
        $this->groupBy = $groupBy;
    }

    /**
     * @return string
     */
    public function getGroupBy(): string
    {
        return $this->groupBy;
    }
}
