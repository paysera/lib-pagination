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

    /**
     * @dataProvider orderingPairProvider
     */
    public function testState(callable $createOrderingPair, array $expected)
    {
        $orderingPair = $createOrderingPair();

        $this->assertSame(
            $expected,
            [
                'orderBy' => $orderingPair->getOrderBy(),
                'orderingDirectionSet' => $orderingPair->isOrderingDirectionSet(),
                'orderAscending' => $orderingPair->isOrderAscending(),
            ]
        );
    }

    public static function orderingPairProvider(): array
    {
        return [
            'direction given to the constructor' => [
                function () {
                    return new OrderingPair('name', true);
                },
                ['orderBy' => 'name', 'orderingDirectionSet' => true, 'orderAscending' => true],
            ],
            'field and direction set' => [
                function () {
                    return (new OrderingPair('name'))->setOrderBy('id')->setOrderAscending(true);
                },
                ['orderBy' => 'id', 'orderingDirectionSet' => true, 'orderAscending' => true],
            ],
            'direction changed to descending' => [
                function () {
                    return (new OrderingPair('name', true))->setOrderAscending(false);
                },
                ['orderBy' => 'name', 'orderingDirectionSet' => true, 'orderAscending' => false],
            ],
        ];
    }
}
