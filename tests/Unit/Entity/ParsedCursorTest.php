<?php
declare(strict_types=1);

namespace Paysera\Pagination\Tests\Unit\Entity;

use InvalidArgumentException;
use Paysera\Pagination\Entity\ParsedCursor;
use PHPUnit\Framework\TestCase;

class ParsedCursorTest extends TestCase
{
    public function testElementsAreReadByIndexAndAMissingOneIsRejected()
    {
        $parsedCursor = (new ParsedCursor())->setCursorElements(['a', 'b']);

        $this->assertSame(['a', 'b'], $parsedCursor->getCursorElements());
        $this->assertSame('b', $parsedCursor->getElementAtIndex(1));

        $this->expectException(InvalidArgumentException::class);
        $parsedCursor->getElementAtIndex(2);
    }
}
