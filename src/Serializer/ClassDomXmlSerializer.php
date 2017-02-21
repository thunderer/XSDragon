<?php
declare(strict_types=1);
namespace Thunder\Xsdragon\Serializer;

use Thunder\Xsdragon\Schema\All;
use Thunder\Xsdragon\Schema\Choice;
use Thunder\Xsdragon\Schema\ComplexType;
use Thunder\Xsdragon\Schema\Element;
use Thunder\Xsdragon\Schema\Schema;
use Thunder\Xsdragon\Schema\SchemaContainer;
use Thunder\Xsdragon\Schema\Sequence;
use Thunder\Xsdragon\Schema\SimpleType;
use Thunder\Xsdragon\Utility\XsdUtility;
use Thunder\Xsdragon\Xml\XmlObjectInterface;

final class ClassDomXmlSerializer implements XmlSerializerInterface
{
    /** @var SchemaContainer */
    private $schemas;

    public function __construct(SchemaContainer $schemas)
    {
        $this->schemas = $schemas;
    }

    public function serialize(XmlObjectInterface $object): string
    {
        $doc = new \DOMDocument();
        $doc->formatOutput = true;
        $schema = $this->schemas->findSchemaByNs($object->getXmlNamespace());
        $type = $schema->findTypeByName($object->getXmlName());

        $this->log('', 0);
        $this->log('INIT '.get_class($type), 0);
        $this->doSerialize($type, $object, $schema, $doc, null, 1);
        $this->log('EXIT '.get_class($type), 0);

        return $doc->saveXML();
    }

    private function doSerialize($type, XmlObjectInterface $object, Schema $schema, \DOMDocument $doc, \DOMElement $parent = null, int $level)
    {
        if($type instanceof Element) {
            $this->log('RElement '.get_class($object), $level);
            $subXsdType = $this->findTypeEverywhere($this->schemas->findSchemaByNs($object->getXmlNamespace()), $type->getType())[1];
            $root = $doc->createElementNS($type->getNamespaceUri(), $type->getName());
            $parent ? $parent->appendChild($root) : $doc->appendChild($root);
            $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns', $schema->getNamespace());
            foreach($this->schemas->findUrisFor($schema) as $prefix => $uri) {
                if(false === in_array($uri, ['http://www.w3.org/2001/XMLSchema'], true)) {
                    $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:'.$prefix, $uri);
                }
            }
            $this->complexType($subXsdType, $object->getType(), $schema, $object->getXmlNamespace(), $doc, $root, $level);

            return;
        }

        throw new \RuntimeException(sprintf('Invalid type %s!', is_object($type) ? get_class($type) : var_export($type, true)));
    }

    private function xmlPrefix(string $uri, Schema $schema): string
    {
        return XsdUtility::xmlPrefix($this->schemas, $uri, $schema);
    }

    private function complexType(ComplexType $xsdType, XmlObjectInterface $object, Schema $schema, string $ns, \DOMDocument $doc, \DOMElement $parent = null, int $level)
    {
        foreach($xsdType->getAttributes() as $attribute) {
            $value = $object->{'get'.ucfirst($attribute->getName())}();
            $this->log('Attribute'.' '.$attribute->getName().' '.$value, $level);
            $parent->setAttribute($attribute->getName(), $value);
        }

        $this->logType('ComplexType', $object, $level);
        $type = $xsdType->getType();
        switch(true) {
            case $type instanceof Sequence: {
                $this->sequence($type, $object, $schema, $object->getXmlNamespace(), $doc, $parent, $level + 1);
                break;
            }
            case $type instanceof Choice: {
                $this->choice($type, $object, $schema, $object->getXmlNamespace(), $doc, $parent, $level + 1);
                break;
            }
            case $type instanceof All: {
                $this->all($type, $object, $schema, $object->getXmlNamespace(), $doc, $parent, $level + 1);
                break;
            }
            default: {
                throw new \RuntimeException(sprintf('Invalid type %s!', is_object($type) ? get_class($type) : $type));
            }
        }
    }

    private function all(All $all, $object, Schema $schema, string $ns, \DOMDocument $doc, \DOMElement $parent = null, int $level)
    {
        $this->log('All', $level);
        foreach($all->getElements() as $element) {
            if($element instanceof Element) {
                $this->element($element, $object->{'get'.ucfirst($element->getName())}(), $schema, $ns, $doc, $parent, $level + 1);
            } else {
                throw new \RuntimeException('Invalid type!');
            }
        }
    }

    private function logType(string $type, $value, int $level)
    {
        $this->log($type.' '.(is_object($value) ? get_class($value) : gettype($value)), $level);
    }

    private function simpleType(SimpleType $simpleType, $object, Schema $schema, \DOMDocument $doc, \DOMElement $parent = null, int $level)
    {
        $this->log('SimpleType '.$simpleType->getName().' '.$object->getValue(), $level);
        $parent->nodeValue = (string)$object->getValue();
    }

    private function element(Element $element, $objects, Schema $schema, string $ns, \DOMDocument $doc, \DOMElement $parent = null, int $level)
    {
        $xmlPrefix = $this->xmlPrefix($ns, $schema);
        $this->log(sprintf("\e[36m%s\e[0m \e[31m%s\e[0m \e[34m%s\e[0m \e[33m%s\e[0m \e[32m%s\e[0m",
            'Element', $element->getName(), $element->getType(), XsdUtility::describe($objects), ''), $level);
        $scalars = ['xsd:nonNegativeInteger'];
        if(in_array($element->getType(), $scalars)) {
            $type = null;
        } else {
            try {
                $type = $this->findTypeEverywhere($schema, $element->getType())[1];
            } catch(\Exception $e) {
                $type = $this->findTypeEverywhere($this->schemas->findSchemaByNs($element->getNamespaceUri()), $element->getType())[1];
            }
        }

        foreach(is_array($objects) ? $objects : [$objects] as $object) {
            if(null === $object) {
                continue;
            }
            if($object) {
                $xmlElement = $doc->createElementNs($ns, $xmlPrefix.$element->getName());
            }

            switch(true) {
                case $type instanceof Sequence: {
                    $this->sequence($type, $object, $schema, $object->getXmlNamespace(), $doc, $xmlElement, $level + 1);
                    $parent->appendChild($xmlElement);
                    break;
                }
                case $type instanceof ComplexType: {
                    $this->complexType($type, $object, $schema, $object->getXmlNamespace(), $doc, $xmlElement, $level + 1);
                    $parent->appendChild($xmlElement);
                    break;
                }
                case $type instanceof SimpleType: {
                    $this->simpleType($type, $object, $schema, $doc, $xmlElement, $level + 1);
                    $parent->appendChild($xmlElement);
                    break;
                }
                case $type instanceof Element: {
                    $this->element($type, $object, $schema, $object->getXmlNamespace(), $doc, $xmlElement, $level + 1);
                    $parent->appendChild($xmlElement);
                    break;
                }
                case null === $type: {
                    if(null === $object) {
                        break;
                    }
                    $xmlElement->nodeValue = $object->{'get'.ucfirst($element->getName())}();
                    $parent->appendChild($xmlElement);
                    break;
                }
                default: {
                    throw new \RuntimeException(sprintf('Invalid type %s!', get_class($element)));
                }
            }
        }
    }

    private function choice(Choice $choice, $object, Schema $schema, string $ns, \DOMDocument $doc, \DOMElement $parent = null, int $level)
    {
        $this->log('Choice', $level);
        foreach($choice->getElements() as $type) {
            switch(true) {
                case $type instanceof Element: {
                    $this->element($type, $object->{'get'.ucfirst($type->getName())}(), $schema, $ns, $doc, $parent, $level + 1);
                    break;
                }
                default: {
                    throw new \RuntimeException(sprintf('Invalid type %s!', get_class($type)));
                }
            }
        }
    }

    private function sequence(Sequence $sequence, $object, Schema $schema, string $ns, \DOMDocument $doc, \DOMElement $parent = null, int $level)
    {
        $this->log('Sequence ', $level);
        foreach($sequence->getElements() as $element) {
            switch(true) {
                case $element instanceof Element: {
                    $method = 'get'.ucfirst($element->getName());
                    if(false === method_exists($object, $method)) {
                        throw new \BadMethodCallException(sprintf('Object %s does not have method %s!', get_class($object), $method));
                    }
                    $this->element($element, $object->{$method}(), $schema, $ns, $doc, $parent, $level + 1);
                    break;
                }
                default: {
                    throw new \RuntimeException(sprintf('Invalid type %s!', get_class($element)));
                }
            }
        }
    }

    private function findTypeEverywhere(Schema $schema, string $name): array
    {
        if(false !== strpos($name, ':')) {
            list($prefix, $name) = explode(':', $name, 2);
            $namespaces = $schema->getNamespaces();
            if(false === array_key_exists($prefix, $namespaces)) {
                throw new \RuntimeException(sprintf('Failed to find prefix %s among %s!', $prefix.':'.$name, json_encode($namespaces)));
            }
            $schema = $this->schemas->findSchemaByNs($namespaces[$prefix]);

            return [$schema, $schema->findTypeByName($name)];
        }

        return [$schema, $schema->findTypeByName($name)];
    }

    private function log(string $message, int $level)
    {
        //  echo str_pad('', 2 * $level, ' ').$message."\n";
    }
}
