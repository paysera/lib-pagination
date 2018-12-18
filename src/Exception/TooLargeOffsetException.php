<?php
declare(strict_types=1);

namespace Paysera\Pagination\Exception;

class TooLargeOffsetException extends PaginationException
{
    private $maximumOffset;
    private $givenOffset;

    public function __construct(int $maximumOffset, int $givenOffset)
    {
        parent::__construct(sprintf(
            'Given offset (%s) is bigger than maximum allowed (%s). Please use cursor-based navigation',
            $givenOffset,
            $maximumOffset
        ));
        $this->maximumOffset = $maximumOffset;
        $this->givenOffset = $givenOffset;
    }

    /**
     * @return int
     */
    public function getMaximumOffset(): int
    {
        return $this->maximumOffset;
    }

    /**
     * @return int
     */
    public function getGivenOffset(): int
    {
        return $this->givenOffset;
    }
}
