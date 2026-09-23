<?php
declare(strict_types=1);

namespace Paysera\Pagination\Tests\Unit\Entity;

use Paysera\Pagination\Entity\Result;
use PHPUnit\Framework\TestCase;

class ResultTest extends TestCase
{
    public function testANewResultHasNoItemsNoTotalCountAndNoDirections()
    {
        $result = new Result();

        $this->assertSame([], $result->getItems());
        $this->assertNull($result->getTotalCount());
        $this->assertNull($result->hasNext());
        $this->assertNull($result->hasPrevious());
    }

    public function testGettersReturnWhatWasSet()
    {
        $result = (new Result())
            ->setItems(['a'])
            ->setTotalCount(3)
            ->setHasNext(true)
            ->setHasPrevious(false)
            ->setNextCursor('"2"')
            ->setPreviousCursor('"1"')
        ;

        $this->assertSame(3, $result->getTotalCount());
        $this->assertTrue($result->hasNext());
        $this->assertFalse($result->hasPrevious());
        $this->assertSame('"2"', $result->getNextCursor());
        $this->assertSame('"1"', $result->getPreviousCursor());
        $this->assertNull($result->setTotalCount(null)->getTotalCount());
    }

    public function testAddedItemsAreAppendedAndIterated()
    {
        $result = (new Result())->setItems(['a'])->addItem('b')->addItem('c');

        $this->assertSame(['a', 'b', 'c'], $result->getItems());
        $this->assertSame(['a', 'b', 'c'], iterator_to_array($result));
    }
}
