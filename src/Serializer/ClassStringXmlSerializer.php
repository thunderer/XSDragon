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

final class ClassStringXmlSerializer implements XmlSerializerInterface
{
    /** @var SchemaContainer */
    private $schemas;

    public function __construct(SchemaContainer $schemas)
    {
        $this->schemas = $schemas;
    }

    public function serialize(XmlObjectInterface $object): string
    {
        $schema = $this->schemas->findSchemaByNs($object->getXmlNamespace());
        $type = $schema->findTypeByName($object->getXmlName());

        $this->log('', 0);
        $this->log('INIT '.get_class($type), 0);
        $string = $this->doSerialize($type, $object, $schema, 1);
        $this->log('EXIT '.get_class($type), 0);

        return $string;
    }

    private function doSerialize($type, $object, Schema $schema, int $level): string
    {
        if($type instanceof Element) {
            $this->log('RElement '.get_class($object), $level);

            $xmlns = ' xmlns="'.$schema->getNamespace().'"';
            foreach($this->schemas->findUrisFor($schema) as $prefix => $uri) {
                if(false === in_array($uri, ['http://www.w3.org/2001/XMLSchema'], true)) {
                    $xmlns .= ' xmlns:'.$prefix.'="'.$uri.'"';
                }
            }

            $subType = $this->findTypeEverywhere($schema, $type->getType());
            $subObject = $object->getType();

            $attributes = '';
            foreach($subType->getAttributes() as $attribute) {
                $value = $subObject->{'get'.ucfirst($attribute->getName())}();
                $attributes .= ' '.$attribute->getName().'="'.$value.'"';
            }

            return '<?xml version="1.0"?>'
                .'<'.$type->getName().$xmlns.$attributes.'>'
                .$this->complexType($subType, $subObject, $schema, $level)
                .'</'.$type->getName().'>';
        }

        throw new \RuntimeException(sprintf('Invalid type %s!', is_object($type) ? get_class($type) : var_export($type, true)));
    }

    private function complexType(ComplexType $complexType, XmlObjectInterface $object, Schema $schema, int $level): string
    {
        $this->logType('ComplexType', $object, $level);
        $type = $complexType->getType();
        switch(true) {
            case $type instanceof Sequence: { return $this->sequence($type, $object, $schema, $object->getXmlNamespace(), $level + 1); }
            case $type instanceof Choice: { return $this->choice($type, $object, $schema, $object->getXmlNamespace(), $level + 1); }
            case $type instanceof All: { return $this->all($type, $object, $schema, $object->getXmlNamespace(), $level + 1); }
            default: { throw new \RuntimeException(sprintf('Invalid type %s!', is_object($type) ? get_class($type) : $type)); }
        }
    }

    private function all(All $all, $object, Schema $schema, string $ns, int $level): string
    {
        $result = '';
        $this->log('All', $level);
        foreach($all->getElements() as $element) {
            if($element instanceof Element) {
                $result .= $this->element($element, $object->{'get'.ucfirst($element->getName())}(), $schema, $ns, $level + 1);
            } else {
                throw new \RuntimeException('Invalid type!');
            }
        }

        return $result;
    }

    private function simpleType(SimpleType $simpleType, $object, int $level): string
    {
        $this->log('SimpleType '.$simpleType->getName().' '.$object->getValue(), $level);

        return (string)$object->getValue();
    }

    private function element(Element $element, $objects, Schema $schema, string $ns, int $level)
    {
        $prefix = $this->xmlPrefix($ns, $schema);
        $this->log(sprintf("\e[36m%s\e[0m \e[31m%s\e[0m \e[34m%s\e[0m \e[33m%s\e[0m \e[32m%s\e[0m",
            'Element', $element->getName(), $element->getType(), XsdUtility::describe($objects), ''), $level);
        $scalars = ['xsd:nonNegativeInteger'];
        if(in_array($element->getType(), $scalars, true)) {
            $type = null;
        } else {
            try {
                $type = $this->findTypeEverywhere($schema, $element->getType());
            } catch(\Exception $e) {
                $type = $this->findTypeEverywhere($this->schemas->findSchemaByNs($element->getNamespaceUri()), $element->getType());
            }
        }

        $result = '';
        foreach(is_array($objects) ? $objects : [$objects] as $object) {
            if(null === $object) {
                continue;
            }
            $elementStr = '<'.$prefix.$element->getName().'>';
            $suffix = '</'.$prefix.$element->getName().'>';

            if($type instanceof Sequence) {
                $elementStr .= $this->sequence($type, $object, $schema, $object->getNamespace(), $level + 1);
            } elseif($type instanceof ComplexType) {
                $elementStr .= $this->complexType($type, $object, $schema, $level + 1);
            } elseif($type instanceof SimpleType) {
                $elementStr .= $this->simpleType($type, $object, $level + 1);
            } elseif($type instanceof Element) {
                $elementStr .= $this->element($type, $object, $schema, $object->getNamespace(), $level + 1);
            } elseif(null === $type) {
                if(null === $object) {
                    continue;
                }
                $elementStr .= $object->{'get'.ucfirst($element->getName())}();
            } else {
                throw new \RuntimeException(sprintf('Invalid type %s!', get_class($element)));
            }

            $result .= $elementStr.$suffix;
        }

        return $result;
    }

    private function choice(Choice $choice, $object, Schema $schema, string $ns, int $level): string
    {
        $return = '';
        $this->log('Choice', $level);
        foreach($choice->getElements() as $type) {
            switch(true) {
                case $type instanceof Element: {
                    $return .= $this->element($type, $object->{'get'.ucfirst($type->getName())}(), $schema, $ns, $level + 1);
                    break;
                }
                default: {
                    throw new \RuntimeException(sprintf('Invalid type %s!', get_class($type)));
                }
            }
        }

        return $return;
    }

    private function sequence(Sequence $sequence, $object, Schema $schema, string $ns, int $level): string
    {
        $return = '';
        $this->log('Sequence ', $level);
        foreach($sequence->getElements() as $element) {
            switch(true) {
                case $element instanceof Element: {
                    $method = 'get'.ucfirst($element->getName());
                    if(false === method_exists($object, $method)) {
                        throw new \BadMethodCallException(sprintf('Object %s does not have method %s!', get_class($object), $method));
                    }
                    $return .= $this->element($element, $object->{$method}(), $schema, $ns, $level + 1);
                    break;
                }
                default: {
                    throw new \RuntimeException(sprintf('Invalid type %s!', get_class($element)));
                }
            }
        }

        return $return;
    }

    /* --- UTILITIES -------------------------------------------------------- */

    private function xmlPrefix(string $uri, Schema $schema): string
    {
        return XsdUtility::xmlPrefix($this->schemas, $uri, $schema);
    }

    private function findTypeEverywhere(Schema $schema, string $name)
    {
        if(false !== strpos($name, ':')) {
            list($prefix, $name) = explode(':', $name, 2);
            $namespaces = $schema->getNamespaces();
            if(false === array_key_exists($prefix, $namespaces)) {
                throw new \RuntimeException(sprintf('Failed to find prefix %s among %s!', $prefix.':'.$name, json_encode($namespaces)));
            }

            return $this->schemas->findSchemaByNs($namespaces[$prefix])->findTypeByName($name);
        }

        return $schema->findTypeByName($name);
    }

    private function logType(string $type, $value, int $level): void
    {
        $this->log($type.' '.(is_object($value) ? get_class($value) : gettype($value)), $level);
    }

    private function log(string $message, int $level): void
    {
        // echo str_pad('', 2 * $level, ' ').$message."\n";
    }
}
