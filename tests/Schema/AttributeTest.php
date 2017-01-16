<?php
declare(strict_types=1);
namespace Thunder\Xsdragon\Tests\Schema;

use PHPUnit\Framework\TestCase;
use Thunder\Xsdragon\Schema\Attribute;

final class AttributeTest extends TestCase
{
    public function testChoice()
    {
        $attribute = new Attribute('ns', 'name', 'type', 'optional');

        $this->assertSame('ns', $attribute->getNamespaceUri());
        $this->assertSame('name', $attribute->getName());
        $this->assertSame('type', $attribute->getType());
        $this->assertSame('optional', $attribute->getUse());
    }

    public function testExceptionWhenInvalidName()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Attribute name must not be empty!');
        new Attribute('ns', '', 'type', 'required');
    }

    public function testExceptionWhenInvalidType()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Attribute type must be either null, non-empty string or SimpleType object!');
        new Attribute('ns', 'name', '', 'required');
    }

    public function testExceptionWhenInvalidUse()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Attribute use must be one of');
        new Attribute('ns', 'name', 'type', 'require');
    }
}
