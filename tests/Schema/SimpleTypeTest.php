<?php
declare(strict_types=1);
namespace Thunder\Xsdragon\Tests\Schema;

use PHPUnit\Framework\TestCase;
use Thunder\Xsdragon\Schema\Restrictions;
use Thunder\Xsdragon\Schema\SimpleType;

final class SimpleTypeTest extends TestCase
{
    public function testSimpleType()
    {
        $restrictions = Restrictions::createFromMinLength('xsd:string', 5);
        $simpleType = new SimpleType('ns', 'name', 'doc', $restrictions);

        $this->assertSame('ns', $simpleType->getNamespaceUri());
        $this->assertSame('name', $simpleType->getName());
        $this->assertSame('doc', $simpleType->getDocumentation());
        $this->assertSame($restrictions, $simpleType->getType());
    }

    public function testSimpleTypeWithoutDocumentation()
    {
        $restrictions = Restrictions::createFromMinLength('xsd:string', 5);
        $simpleType = new SimpleType('ns', 'name', null, $restrictions);

        $this->assertSame('name', $simpleType->getName());
        $this->assertNull($simpleType->getDocumentation());
        $this->assertSame($restrictions, $simpleType->getType());
    }

    public function testSimpleTypeExceptionWhenInvalidType()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('SimpleType type can be either null, Restrictions or Union, ');
        new SimpleType('ns', 'name', null, new \stdClass());
    }
}
