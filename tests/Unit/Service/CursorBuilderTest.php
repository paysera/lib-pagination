<?php
declare(strict_types=1);

namespace Paysera\Pagination\Tests\Unit\Service;

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

    public function testParsesTheValuesAndTheInclusionMark()
    {
        $included = $this->cursorBuilder->parseCursor('="a","2"', 2);
        $excluded = $this->cursorBuilder->parseCursor('"a","2"', 2);

        $this->assertTrue($included->isCursoredItemIncluded());
        $this->assertSame(['a', '2'], $included->getCursorElements());
        $this->assertFalse($excluded->isCursoredItemIncluded());
        $this->assertSame(['a', '2'], $excluded->getCursorElements());
    }

    /**
     * @dataProvider invalidCursorDataProvider
     */
    public function testRejectsAnInvalidCursor(string $cursor)
    {
        $this->expectException(InvalidCursorException::class);

        $this->cursorBuilder->parseCursor($cursor, 2);
    }

    public static function invalidCursorDataProvider(): array
    {
        return [
            'not JSON' => ['not-a-cursor'],
            'one value where two are needed' => ['"a"'],
            'a number where a string is needed' => ['"a",2'],
        ];
    }
}
