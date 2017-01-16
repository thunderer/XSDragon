<?php
declare(strict_types=1);
namespace Thunder\Xsdragon\Tests\Schema;

use PHPUnit\Framework\TestCase;
use Thunder\Xsdragon\Schema\All;
use Thunder\Xsdragon\Schema\Element;

final class AllTest extends TestCase
{
    public function testAll()
    {
        $elements = [new Element('ns', 'name', 'type', 1, 1, false, null)];
        $all = new All('ns', $elements);

        $this->assertSame('ns', $all->getNamespaceUri());
        $this->assertSame(1, $all->countElements());
    }

    public function testExceptionWhenEmptyElements()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('All elements must not be empty!');
        new All('ns', []);
    }

    public function testExceptionWhenInvalidElements()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('All element must be an object of type');
        new All('ns', [new \stdClass()]);
    }
}
