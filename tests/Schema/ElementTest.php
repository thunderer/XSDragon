<?php
declare(strict_types=1);
namespace Thunder\Xsdragon\Tests\Schema;

use PHPUnit\Framework\TestCase;
use Thunder\Xsdragon\Schema\Element;
use Thunder\Xsdragon\Schema\Restrictions;
use Thunder\Xsdragon\Schema\SimpleType;

final class ElementTest extends TestCase
{
    public function testElement()
    {
        $element = new Element('ns', 'name', 'type', 1, 1, false, 'doc');

        $this->assertSame('name', $element->getName());
        $this->assertSame('type', $element->getType());
        $this->assertSame(1, $element->getMinOccurs());
        $this->assertSame(1, $element->getMaxOccurs());
        $this->assertFalse($element->isNullable());
        $this->assertSame('doc', $element->getDocumentation());
    }

    public function testElementWithoutDocumentation()
    {
        $element = new Element('ns', 'name', 'type', 0, 'unbounded', false, null);

        $this->assertSame('name', $element->getName());
        $this->assertSame('type', $element->getType());
        $this->assertSame(0, $element->getMinOccurs());
        $this->assertSame('unbounded', $element->getMaxOccurs());
        $this->assertFalse($element->isNullable());
        $this->assertNull($element->getDocumentation());
    }

    public function testElementWithSimpleType()
    {
        $simpleType = new SimpleType('ns', 'name', 'type', Restrictions::createFromMinLength('xsd:string', 5));
        $element = new Element('ns', 'name',$simpleType , 0, 'unbounded', false, null);

        $this->assertSame('name', $element->getName());
        $this->assertSame($simpleType, $element->getType());
        $this->assertSame(0, $element->getMinOccurs());
        $this->assertSame('unbounded', $element->getMaxOccurs());
        $this->assertFalse($element->isNullable());
        $this->assertNull($element->getDocumentation());
    }

    public function testElementWithInvalidType()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid element type stdClass!');
        new Element('ns', 'name', new \stdClass(), 1, 1, false, null);
    }

    public function testElementWithInvalidMinOccurs()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Choice minOccurs can be either null, non-negative integer or string `unbounded`,');
        new Element('ns', 'name', 'type', 'random', 1, false, null);
    }

    public function testElementWithInvalidMaxOccurs()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Choice maxOccurs can be either null, non-negative integer or string `unbounded`,');
        new Element('ns', 'name', 'type', 0, 'invalid', false, null);
    }
}
