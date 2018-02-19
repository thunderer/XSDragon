<?php
declare(strict_types=1);
namespace Thunder\Xsdragon\Tests\Schema;

use PHPUnit\Framework\TestCase;
use Thunder\Xsdragon\Schema\ComplexType;
use Thunder\Xsdragon\Schema\Element;
use Thunder\Xsdragon\Schema\Restrictions;
use Thunder\Xsdragon\Schema\Schema;
use Thunder\Xsdragon\Schema\SimpleType;

final class SchemaTest extends TestCase
{
    public function testSchema()
    {
        $schema = new Schema('location', 'ns', ['prefix' => 'uri']);

        $this->assertSame('location', $schema->getLocation());
        $this->assertSame('ns', $schema->getNamespace());
        $this->assertSame(['prefix' => 'uri'], $schema->getNamespaces());
        $this->assertSame([], $schema->getElements());
        $this->assertSame([], $schema->getComplexTypes());
        $this->assertSame([], $schema->getSimpleTypes());
        $this->assertSame([], $schema->getImports());
    }

    public function testExceptionWhenSchemaDoesNotContainTypeWithName()
    {
        $schema = new Schema('location', 'ns', []);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to find type with name 404!');
        $schema->findTypeByName('404');
    }

    public function testExceptionWhenAddingDuplicateElement()
    {
        $element = new Element('ns', 'XXX', 'type', 1, 1, false, 'doc');
        $schema = new Schema('location', 'ns', []);
        $schema->addElement($element);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Duplicate Element identifier XXX!');
        $schema->addElement($element);
    }

    public function testExceptionWhenAddingDuplicateComplexType()
    {
        $complexType = new ComplexType('ns', 'XXX', null, [], 'doc');
        $schema = new Schema('location', 'ns', []);
        $schema->addComplexType($complexType);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Duplicate ComplexType identifier XXX!');
        $schema->addComplexType($complexType);
    }

    public function testExceptionWhenAddingDuplicateSimpleType()
    {
        $simpleType = new SimpleType('ns', 'XXX', 'doc', Restrictions::createFromLength('xsd:string', 5));
        $schema = new Schema('location', 'ns', []);
        $schema->addSimpleType($simpleType);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Duplicate SimpleType identifier XXX!');
        $schema->addSimpleType($simpleType);
    }
}
