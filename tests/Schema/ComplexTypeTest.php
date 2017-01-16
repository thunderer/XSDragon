<?php
declare(strict_types=1);
namespace Thunder\Xsdragon\Tests\Schema;

use PHPUnit\Framework\TestCase;
use Thunder\Xsdragon\Schema\All;
use Thunder\Xsdragon\Schema\Attribute;
use Thunder\Xsdragon\Schema\ComplexType;
use Thunder\Xsdragon\Schema\Element;

final class ComplexTypeTest extends TestCase
{
    public function testComplexType()
    {
        $attribute = new Attribute('ns', 'attr', 'aType', 'required');
        $all = new All('ns', [new Element('ns', 'name', 'type', 1, 1, false, 'doc')]);
        $complexType = new ComplexType('ns', 'name', $all, [$attribute], 'doc');

        $this->assertSame('ns', $complexType->getNamespaceUri());
        $this->assertSame('name', $complexType->getName());
        $this->assertSame($all, $complexType->getType());
        $this->assertSame(1, $complexType->countAttributes());
        $this->assertSame([$attribute], $complexType->getAttributes());
        $this->assertSame('doc', $complexType->getDocumentation());
    }

    public function testComplexTypeWithoutDocumentation()
    {
        $attribute = new Attribute('ns', 'attr', 'aType', 'required');
        $all = new All('ns', [new Element('ns', 'name', 'type', 1, 1, false, 'doc')]);
        $complexType = new ComplexType('ns', 'name', $all, [$attribute], null);

        $this->assertSame('name', $complexType->getName());
        $this->assertSame($all, $complexType->getType());
        $this->assertSame(1, $complexType->countAttributes());
        $this->assertSame([$attribute], $complexType->getAttributes());
        $this->assertNull($complexType->getDocumentation());
    }

    public function testComplexTypeWithoutAttributes()
    {
        $all = new All('ns', [new Element('ns', 'name', 'type', 1, 1, false, 'doc')]);
        $complexType = new ComplexType('ns', 'name', $all, [], 'doc');

        $this->assertSame('name', $complexType->getName());
        $this->assertSame($all, $complexType->getType());
        $this->assertSame(0, $complexType->countAttributes());
        $this->assertSame([], $complexType->getAttributes());
        $this->assertSame('doc', $complexType->getDocumentation());
    }

    public function testExceptionWhenEmptyElements()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid ComplexType type:');
        new ComplexType('ns', 'name', new \stdClass(), [], 'doc');
    }
}
