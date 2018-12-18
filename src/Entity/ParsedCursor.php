<?php
declare(strict_types=1);

namespace Paysera\Pagination\Entity;

use InvalidArgumentException;

class ParsedCursor
{
    /**
     * @var array
     */
    private $cursorElements;

    /**
     * @var bool
     */
    private $cursoredItemIncluded;

    public function __construct()
    {
        $this->cursorElements = [];
        $this->cursoredItemIncluded = false;
    }

    /**
     * @return array
     */
    public function getCursorElements(): array
    {
        return $this->cursorElements;
    }

    public function getElementAtIndex(int $index): string
    {
        if (!isset($this->cursorElements[$index])) {
            throw new InvalidArgumentException(sprintf('There is no element at index %s', $index));
        }

        return $this->cursorElements[$index];
    }

    /**
     * @param array $cursorElements
     * @return $this
     */
    public function setCursorElements(array $cursorElements): self
    {
        $this->cursorElements = $cursorElements;
        return $this;
    }

    /**
     * @return bool
     */
    public function isCursoredItemIncluded(): bool
    {
        return $this->cursoredItemIncluded;
    }

    /**
     * @param bool $cursoredItemIncluded
     * @return $this
     */
    public function setCursoredItemIncluded(bool $cursoredItemIncluded): self
    {
        $this->cursoredItemIncluded = $cursoredItemIncluded;
        return $this;
    }
}
