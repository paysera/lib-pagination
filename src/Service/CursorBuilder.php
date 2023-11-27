<?php
declare(strict_types=1);

namespace Paysera\Pagination\Service;

use DateTimeInterface;
use Paysera\Pagination\Entity\OrderingConfiguration;
use Paysera\Pagination\Entity\ParsedCursor;
use Paysera\Pagination\Exception\InvalidCursorException;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

class CursorBuilder implements CursorBuilderInterface
{
    private $propertyAccessor;

    public function __construct(PropertyAccessorInterface $propertyAccessor)
    {
        $this->propertyAccessor = $propertyAccessor;
    }

    public function parseCursor(string $cursor, int $requiredCount): ParsedCursor
    {
        $parsedCursor = new ParsedCursor();

        $serializedCursor = $cursor;
        if (substr($cursor, 0, 1) === '=') {
            $serializedCursor = substr($cursor, 1);
            $parsedCursor->setCursoredItemIncluded(true);
        }

        $decoded = json_decode(sprintf('[%s]', $serializedCursor), false);
        if (!is_array($decoded)) {
            throw new InvalidCursorException();
        }
        if (count($decoded) !== $requiredCount) {
            throw new InvalidCursorException();
        }
        foreach ($decoded as $value) {
            if (!is_string($value)) {
                throw new InvalidCursorException();
            }
        }

        return $parsedCursor->setCursorElements($decoded);
    }

    public function buildCursorWithIncludedItem(string $cursor): string
    {
        return '=' . $cursor;
    }

    public function invertCursorInclusion(string $cursor): string
    {
        return substr($cursor, 0, 1) === '=' ? substr($cursor, 1) : '=' . $cursor;
    }

    /**
     * @param mixed $item
     * @param array|\Paysera\Pagination\Entity\OrderingConfiguration[] $orderingConfigurations
     * @return string
     */
    public function getCursorFromItem($item, array $orderingConfigurations): string
    {
        $cursor = [];
        foreach ($orderingConfigurations as $orderingConfiguration) {
            $cursor[] = $this->getCursorItemValue($item, $orderingConfiguration);
        }

        return $this->convertArrayToCursor($cursor);
    }

    private function getCursorItemValue($item, OrderingConfiguration $orderingConfiguration): string
    {
        if ($orderingConfiguration->getAccessorPath() !== null) {
            $value = $this->propertyAccessor->getValue($item, $orderingConfiguration->getAccessorPath());
        } else {
            $value = call_user_func($orderingConfiguration->getAccessorClosure(), $item);
        }

        return $this->processValue($value);
    }

    private function processValue($value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return (string)$value;
    }

    private function convertArrayToCursor(array $cursorElements): string
    {
        return trim(json_encode($cursorElements), '[]');
    }
}
