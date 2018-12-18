<?php
declare(strict_types=1);

namespace Paysera\Pagination\Service;

use Paysera\Pagination\Entity\OrderingConfiguration;
use Paysera\Pagination\Entity\ParsedCursor;

interface CursorBuilderInterface
{
    public function parseCursor(string $cursor, int $requiredCount): ParsedCursor;

    public function buildCursorWithIncludedItem(string $cursor): string;

    public function invertCursorInclusion(string $cursor): string;

    /**
     * @param mixed $item
     * @param array|OrderingConfiguration[] $orderingConfigurations
     * @return string
     */
    public function getCursorFromItem($item, array $orderingConfigurations): string;
}
