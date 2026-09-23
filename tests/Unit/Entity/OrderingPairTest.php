<?php
declare(strict_types=1);

namespace Paysera\Pagination\Tests\Unit\Entity;

use Paysera\Pagination\Entity\OrderingPair;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class OrderingPairTest extends TestCase
{
    public function testAPairWithoutADirectionSaysSoAndRefusesToGuessOne()
    {
        $orderingPair = new OrderingPair('name');

        $this->assertFalse($orderingPair->isOrderingDirectionSet());

        $this->expectException(RuntimeException::class);
        $orderingPair->isOrderAscending();
    }

    public function testSettersChangeTheFieldAndTheDirection()
    {
        $orderingPair = (new OrderingPair('name'))->setOrderBy('id')->setOrderAscending(false);

        $this->assertSame('id', $orderingPair->getOrderBy());
        $this->assertTrue($orderingPair->isOrderingDirectionSet());
        $this->assertFalse($orderingPair->isOrderAscending());
    }
}
