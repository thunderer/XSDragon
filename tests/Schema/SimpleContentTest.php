<?php
declare(strict_types=1);
namespace Thunder\Xsdragon\Tests\Schema;

use PHPUnit\Framework\TestCase;
use Thunder\Xsdragon\Schema\Restrictions;
use Thunder\Xsdragon\Schema\SimpleContent;

final class SimpleContentTest extends TestCase
{
    public function testSimpleContent()
    {
        $restriction = Restrictions::createFromMaxInclusive('xsd:int', 5);
        $simpleContent = new SimpleContent($restriction);

        $this->assertSame($restriction, $simpleContent->getType());
    }

    public function testSimpleContentExceptionWhenInvalidType()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid SimpleContent type');
        new SimpleContent(new \stdClass());
    }
}
