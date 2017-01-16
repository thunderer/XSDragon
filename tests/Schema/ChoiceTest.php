<?php
declare(strict_types=1);
namespace Thunder\Xsdragon\Tests\Schema;

use PHPUnit\Framework\TestCase;
use Thunder\Xsdragon\Schema\Choice;
use Thunder\Xsdragon\Schema\Sequence;

final class ChoiceTest extends TestCase
{
    public function testChoice()
    {
        $elements = [new Sequence('ns', [])];
        $choice = new Choice('ns', $elements, 2, 3);

        $this->assertSame(1, $choice->countElements());
        $this->assertSame($elements, $choice->getElements());
        $this->assertSame(2, $choice->getMinOccurs());
        $this->assertSame(3, $choice->getMaxOccurs());
    }

    public function testExceptionWhenEmptyElements()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Choice elements must not be empty!');
        new Choice('ns', [], 1, 1);
    }

    public function testExceptionWhenInvalidElements()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Choice element must be an object of type');
        new Choice('ns', [new \stdClass()], null, null);
    }

    public function testExceptionWhenInvalidMinOccurs()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Choice minOccurs can be either null, non-negative integer or string `unbounded`');
        new Choice('ns', [new Sequence('ns', [])], 'bounded', null);
    }

    public function testExceptionWhenInvalidMaxOccurs()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Choice maxOccurs can be either null, non-negative integer or string `unbounded`');
        new Choice('ns', [new Sequence('ns', [])], null, -2);
    }
}
