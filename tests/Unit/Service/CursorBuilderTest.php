<?php
declare(strict_types=1);

namespace Paysera\Pagination\Tests\Unit\Service;

use Paysera\Pagination\Entity\ParsedCursor;
use Paysera\Pagination\Exception\InvalidCursorException;
use Paysera\Pagination\Service\CursorBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\PropertyAccess;

class CursorBuilderTest extends TestCase
{
    /**
     * @var CursorBuilder
     */
    private $cursorBuilder;

    protected function setUp(): void
    {
        $this->cursorBuilder = new CursorBuilder(PropertyAccess::createPropertyAccessor());
    }

    /**
     * @dataProvider cursorDataProvider
     */
    public function testParsesTheValuesAndTheInclusionMark(string $cursor, ParsedCursor $expected)
    {
        $this->assertEquals($expected, $this->cursorBuilder->parseCursor($cursor, 2));
    }

    public static function cursorDataProvider(): array
    {
        return [
            'item included' => [
                '="a","2"',
                (new ParsedCursor())->setCursorElements(['a', '2'])->setCursoredItemIncluded(true),
            ],
            'item excluded' => ['"a","2"', (new ParsedCursor())->setCursorElements(['a', '2'])],
        ];
    }

    /**
     * @dataProvider invalidCursorDataProvider
     */
    public function testRejectsAnInvalidCursor(string $cursor)
    {
        $this->expectException(InvalidCursorException::class);

        $this->cursorBuilder->parseCursor($cursor, 2);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidCursorDataProvider(): array
    {
        return [
            'not JSON' => ['not-a-cursor'],
            'one value where two are needed' => ['"a"'],
            'three values where two are needed' => ['"a","b","c"'],
            'a number where a string is needed' => ['"a",2'],
        ];
    }
}
