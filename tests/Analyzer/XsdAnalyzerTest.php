<?php
declare(strict_types=1);
namespace Thunder\Xsdragon\Tests\Analyzer;

use PHPUnit\Framework\TestCase;
use Thunder\Xsdragon\Analyzer\XsdAnalyzer;
use Thunder\Xsdragon\Logger\NullLogger;
use Thunder\Xsdragon\Schema\Attribute;
use Thunder\Xsdragon\Schema\Choice;
use Thunder\Xsdragon\Schema\ComplexType;
use Thunder\Xsdragon\Schema\Element;
use Thunder\Xsdragon\Schema\Restrictions;
use Thunder\Xsdragon\Schema\SchemaContainer;
use Thunder\Xsdragon\Schema\Sequence;
use Thunder\Xsdragon\Schema\SimpleType;

final class XsdAnalyzerTest extends TestCase
{
    public function testCreateFromDirectories()
    {
        $this->assertInstanceOf(SchemaContainer::class, (new XsdAnalyzer(new NullLogger()))->createFromDirectories([__DIR__.'/../fixture/xsd']));
    }

    /* --- ELEMENT ---------------------------------------------------------- */

    public function testElementWithDocumentationAndSimpleType()
    {
        $xsd = (new XsdAnalyzer(new NullLogger()))->createFromStrings(['<?xml version="1.0" encoding="UTF-8"?>
<xsd:schema xmlns="http://example.org/schema" xmlns:thunder="http://example.org/schemax" targetNamespace="http://example.org/schema" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
    <xsd:element name="elementWithComplexType">
        <xsd:annotation>
            <xsd:documentation>doc</xsd:documentation>
        </xsd:annotation>
        <xsd:simpleType>
            <xsd:restriction base="xsd:decimal">
                <xsd:fractionDigits value="6" />
            </xsd:restriction>
        </xsd:simpleType>
    </xsd:element>
</xsd:schema>']);
        $schema = $xsd->findSchemaByNs('http://example.org/schema');

        $uri = 'http://example.org/schema';
        $this->assertSame(1, $xsd->countSchemas());
        $this->assertTrue($schema->hasElementWithName('elementWithComplexType'));
        $simpleType = new SimpleType($uri, null, null, Restrictions::createFromFractionDigits('xsd:decimal', 6));
        $this->assertEquals([
            'elementWithComplexType' => new Element($uri, 'elementWithComplexType', $simpleType, null, null, false, 'doc')
        ], $schema->getElements());
        $this->assertSame('http://example.org/schema', $schema->getNamespace());
        $this->assertSame(['xsd' => 'http://www.w3.org/2001/XMLSchema', 'thunder' => 'http://example.org/schemax'], $schema->getNamespaces());
    }

    /* --- COMPLEX TYPE ----------------------------------------------------- */

    public function testComplexTypeWithSequence()
    {
        $xsd = (new XsdAnalyzer(new NullLogger()))->createFromStrings(['<?xml version="1.0" encoding="UTF-8"?>
<xsd:schema xmlns="http://example.org/schema" xmlns:thunder="http://example.org/schemax" targetNamespace="http://example.org/schema" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
    <xsd:complexType name="complexTypeWithSequence">
        <xsd:sequence>
            <xsd:element name="seqEl0" type="seqElType0" minOccurs="1" maxOccurs="1" />
            <xsd:element name="seqEl1" type="seqElType1" minOccurs="0" maxOccurs="1" />
        </xsd:sequence>
        <xsd:attribute name="version" type="xsd:decimal" use="required" />
    </xsd:complexType>
</xsd:schema>']);
        $schema = $xsd->findSchemaByNs('http://example.org/schema');

        $this->assertSame(1, $xsd->countSchemas());
        $this->assertTrue($schema->hasComplexTypeWithName('complexTypeWithSequence'));
        $uri = 'http://example.org/schema';
        $this->assertEquals([
            'complexTypeWithSequence' => new ComplexType($uri, 'complexTypeWithSequence', new Sequence($uri, [
                new Element($uri, 'seqEl0', 'seqElType0', 1, 1, false, null),
                new Element($uri, 'seqEl1', 'seqElType1', 0, 1, false, null),
            ]), [new Attribute($uri, 'version', 'xsd:decimal', 'required')], null)
        ], $schema->getComplexTypes());
        $this->assertSame('http://example.org/schema', $schema->getNamespace());
        $this->assertSame(['xsd' => 'http://www.w3.org/2001/XMLSchema', 'thunder' => 'http://example.org/schemax'], $schema->getNamespaces());
    }

    public function testComplexTypeWithChoice()
    {
        $xsd = (new XsdAnalyzer(new NullLogger()))->createFromStrings(['<?xml version="1.0" encoding="UTF-8"?>
<xsd:schema xmlns="http://example.org/schema" xmlns:thunder="http://example.org/schemax" targetNamespace="http://example.org/schema" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
    <xsd:complexType name="complexTypeWithChoice">
        <xsd:choice>
            <xsd:element name="seqEl0" type="seqElType0" minOccurs="1" maxOccurs="1" />
            <xsd:element name="seqEl1" type="seqElType1" minOccurs="0" maxOccurs="1" />
        </xsd:choice>
        <xsd:attribute name="version" type="xsd:decimal" use="required" />
    </xsd:complexType>
</xsd:schema>']);
        $schema = $xsd->findSchemaByNs('http://example.org/schema');

        $this->assertSame(1, $xsd->countSchemas());
        $this->assertTrue($schema->hasComplexTypeWithName('complexTypeWithChoice'));
        $uri = 'http://example.org/schema';
        $this->assertEquals([
            'complexTypeWithChoice' => new ComplexType($uri, 'complexTypeWithChoice', new Choice($uri, [
                new Element($uri, 'seqEl0', 'seqElType0', 1, 1, false, null),
                new Element($uri, 'seqEl1', 'seqElType1', 0, 1, false, null),
            ], null, null), [new Attribute($uri, 'version', 'xsd:decimal', 'required')], null)
        ], $schema->getComplexTypes());
        $this->assertSame('http://example.org/schema', $schema->getNamespace());
        $this->assertSame(['xsd' => 'http://www.w3.org/2001/XMLSchema', 'thunder' => 'http://example.org/schemax'], $schema->getNamespaces());
    }

    public function testComplexTypeWithSequenceWithUnboundedChoice()
    {
        $xsd = (new XsdAnalyzer(new NullLogger()))->createFromStrings(['<?xml version="1.0" encoding="UTF-8"?>
<xsd:schema xmlns="http://example.org/schema" xmlns:thunder="http://example.org/schemax" targetNamespace="http://example.org/schema" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
    <xsd:complexType name="complexTypeWithSequenceWithUnboundedChoice">
        <xsd:sequence>
            <xsd:choice minOccurs="0" maxOccurs="unbounded">
                <xsd:element name="seqEl0" type="seqElType0" minOccurs="1" maxOccurs="1" />
                <xsd:element name="seqEl1" type="seqElType1" minOccurs="0" maxOccurs="1" />
            </xsd:choice>
        </xsd:sequence>
        <xsd:attribute name="version" type="xsd:decimal" use="required" />
    </xsd:complexType>
</xsd:schema>']);
        $schema = $xsd->findSchemaByNs('http://example.org/schema');

        $this->assertSame(1, $xsd->countSchemas());
        $this->assertTrue($schema->hasComplexTypeWithName('complexTypeWithSequenceWithUnboundedChoice'));
        $uri = 'http://example.org/schema';
        $this->assertEquals([
            'complexTypeWithSequenceWithUnboundedChoice' => new ComplexType($uri, 'complexTypeWithSequenceWithUnboundedChoice', new Sequence($uri, [new Choice($uri, [
                new Element($uri, 'seqEl0', 'seqElType0', 1, 1, false, null),
                new Element($uri, 'seqEl1', 'seqElType1', 0, 1, false, null),
            ], 0, 'unbounded')]), [new Attribute($uri, 'version', 'xsd:decimal', 'required')], null)
        ], $schema->getComplexTypes());
        $this->assertSame('http://example.org/schema', $schema->getNamespace());
        $this->assertSame(['xsd' => 'http://www.w3.org/2001/XMLSchema', 'thunder' => 'http://example.org/schemax'], $schema->getNamespaces());
    }

    /* --- SIMPLE TYPE ------------------------------------------------------ */

    public function testSimpleTypeEnumeration()
    {
        $xsd = (new XsdAnalyzer(new NullLogger()))->createFromStrings(['<?xml version="1.0" encoding="UTF-8"?>
<xsd:schema xmlns="http://example.org/schema" xmlns:thunder="http://example.org/schemax" targetNamespace="http://example.org/schema" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
    <xsd:simpleType name="SampleType">
        <xsd:annotation>
            <xsd:documentation>A little explanation.</xsd:documentation>
        </xsd:annotation>
        <xsd:restriction base="xsd:string">
            <xsd:enumeration value="one" />
            <xsd:enumeration value="two" />
            <xsd:enumeration value="xxx" />
        </xsd:restriction>
    </xsd:simpleType>
</xsd:schema>']);
        $schema = $xsd->findSchemaByNs('http://example.org/schema');

        $this->assertSame(1, $xsd->countSchemas());
        $this->assertTrue($schema->hasSimpleTypeWithName('SampleType'));
        $this->assertEquals([
            'SampleType' => new SimpleType('http://example.org/schema', 'SampleType', 'A little explanation.', Restrictions::createFromEnumerations('xsd:string', ['one', 'two', 'xxx'])),
        ], $schema->getSimpleTypes());
        $this->assertSame('http://example.org/schema', $schema->getNamespace());
        $this->assertSame(['xsd' => 'http://www.w3.org/2001/XMLSchema', 'thunder' => 'http://example.org/schemax'], $schema->getNamespaces());
    }

    public function testSimpleTypeMinLength()
    {
        $xsd = (new XsdAnalyzer(new NullLogger()))->createFromStrings(['<?xml version="1.0" encoding="UTF-8"?>
<xsd:schema xmlns="http://example.org/schema" xmlns:thunder="http://example.org/schemax" targetNamespace="http://example.org/schema" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
    <xsd:simpleType name="SampleType">
        <xsd:annotation>
            <xsd:documentation>A little explanation.</xsd:documentation>
        </xsd:annotation>
        <xsd:restriction base="xsd:string">
            <xsd:minLength value="5" />
        </xsd:restriction>
    </xsd:simpleType>
</xsd:schema>']);
        $schema = $xsd->findSchemaByNs('http://example.org/schema');

        $this->assertSame(1, $xsd->countSchemas());
        $this->assertTrue($schema->hasSimpleTypeWithName('SampleType'));
        $this->assertEquals([
            'SampleType' => new SimpleType('http://example.org/schema', 'SampleType', 'A little explanation.', Restrictions::createFromMinLength('xsd:string', 5)),
        ], $schema->getSimpleTypes());
        $this->assertSame('http://example.org/schema', $schema->getNamespace());
        $this->assertSame(['xsd' => 'http://www.w3.org/2001/XMLSchema', 'thunder' => 'http://example.org/schemax'], $schema->getNamespaces());
    }
}
