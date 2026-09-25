<?php
declare(strict_types=1);

namespace Paysera\Pagination\Tests\Unit\Entity;

use Paysera\Pagination\Entity\Result;
use PHPUnit\Framework\TestCase;

class ResultTest extends TestCase
{
    /**
     * @dataProvider resultProvider
     */
    public function testState(callable $createResult, array $expected)
    {
        $result = $createResult();

        $this->assertSame(
            $expected,
            [
                'items' => $result->getItems(),
                'iterated' => iterator_to_array($result),
                'totalCount' => $result->getTotalCount(),
                'hasNext' => $result->hasNext(),
                'hasPrevious' => $result->hasPrevious(),
                'nextCursor' => $result->getNextCursor(),
                'previousCursor' => $result->getPreviousCursor(),
            ]
        );
    }

    public static function resultProvider(): array
    {
        return [
            'new result' => [
                function () {
                    return new Result();
                },
                [
                    'items' => [],
                    'iterated' => [],
                    'totalCount' => null,
                    'hasNext' => null,
                    'hasPrevious' => null,
                    'nextCursor' => null,
                    'previousCursor' => null,
                ],
            ],
            'every field set' => [
                function () {
                    return (new Result())
                        ->setItems(['a'])
                        ->setTotalCount(3)
                        ->setHasNext(true)
                        ->setHasPrevious(false)
                        ->setNextCursor('"2"')
                        ->setPreviousCursor('"1"');
                },
                [
                    'items' => ['a'],
                    'iterated' => ['a'],
                    'totalCount' => 3,
                    'hasNext' => true,
                    'hasPrevious' => false,
                    'nextCursor' => '"2"',
                    'previousCursor' => '"1"',
                ],
            ],
            'total count unset and flags flipped' => [
                function () {
                    return (new Result())
                        ->setTotalCount(3)
                        ->setTotalCount(null)
                        ->setHasNext(false)
                        ->setHasPrevious(true);
                },
                [
                    'items' => [],
                    'iterated' => [],
                    'totalCount' => null,
                    'hasNext' => false,
                    'hasPrevious' => true,
                    'nextCursor' => null,
                    'previousCursor' => null,
                ],
            ],
            'items added after the ones set' => [
                function () {
                    return (new Result())->setItems(['a'])->addItem('b')->addItem('c');
                },
                [
                    'items' => ['a', 'b', 'c'],
                    'iterated' => ['a', 'b', 'c'],
                    'totalCount' => null,
                    'hasNext' => null,
                    'hasPrevious' => null,
                    'nextCursor' => null,
                    'previousCursor' => null,
                ],
            ],
        ];
    }
}
