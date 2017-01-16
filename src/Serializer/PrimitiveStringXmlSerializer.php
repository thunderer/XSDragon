<?php
declare(strict_types=1);
namespace Thunder\Xsdragon\Serializer;

use Thunder\Xsdragon\Logger\LoggerInterface;
use Thunder\Xsdragon\Schema\All;
use Thunder\Xsdragon\Schema\Attribute;
use Thunder\Xsdragon\Schema\Choice;
use Thunder\Xsdragon\Schema\ComplexType;
use Thunder\Xsdragon\Schema\Element;
use Thunder\Xsdragon\Schema\Schema;
use Thunder\Xsdragon\Schema\SchemaContainer;
use Thunder\Xsdragon\Schema\Sequence;
use Thunder\Xsdragon\Schema\SimpleType;
use Thunder\Xsdragon\Utility\XsdUtility;
use Thunder\Xsdragon\Xml\XmlObjectInterface;

final class PrimitiveStringXmlSerializer implements XmlSerializerInterface
{
    /** @var SchemaContainer */
    private $schemas;
    private $logger;

    public function __construct(SchemaContainer $schemas, LoggerInterface $logger)
    {
        $this->schemas = $schemas;
        $this->logger = $logger;
    }

    public function serialize(XmlObjectInterface $object): string
    {
        $schema = $this->schemas->findSchemaByNs($object->getXmlNamespace());
        $element = $schema->findTypeByName($object->getXmlName());

        if(false === $element instanceof Element) {
            throw new \RuntimeException(sprintf('Invalid type %s!', XsdUtility::describe($element)));
        }

        $this->log(0, '');
        $this->log(0, 'Root', XsdUtility::describe($element), XsdUtility::describe($object));
        $this->log(1, 'DElement', $element->getName(), XsdUtility::describe($object));

        $xmlns = ' xmlns="'.$schema->getNamespace().'"';
        foreach($this->schemas->findUrisFor($schema) as $prefix => $uri) {
            if(false === in_array($uri, ['http://www.w3.org/2001/XMLSchema'], true)) {
                $xmlns .= ' xmlns:'.$prefix.'="'.$uri.'"';
            }
        }

        return '<'.$element->getName().$xmlns.$this->attributes($element, $schema, $object, 1).'>'
            .$this->element($element, $schema, $object, 1)
            .'</'.$element->getName().'>';
    }

    private function attributes(Element $element, Schema $schema, $object, int $level): string
    {
        $type = $element->getType();
        if(is_string($type)) {
            $realType = $this->findTypeEverywhere($schema, $element->getNamespaceUri(), $type);
            if($realType instanceof ComplexType) {
                return array_reduce($realType->getAttributes(), function(string $state, Attribute $attribute) use($object, $level) {
                    $value = $object->{'get'.ucfirst($attribute->getName())}();
                    $this->log($level, 'Attribute', $attribute->getName(), $attribute->getType(), $attribute->getUse(), $value);

                    return $state.' '.$attribute->getName().'="'.$value.'"';
                }, '');
            }
        }

        return '';
    }

    private function element(Element $element, Schema $schema, $object, int $level): string
    {
        if(null === $object) {
            $this->log($level, 'Null', $element->getName(), XsdUtility::describe($object));
            return '';
        }

        $type = $element->getType();
        if(is_string($type)) {
            $realType = $this->findTypeEverywhere($schema, $element->getNamespaceUri(), $type);
            if($realType instanceof ComplexType) {
                $innerType = $realType->getType();
                if($innerType instanceof Sequence) {
                    $this->log($level, 'Sequence', $type, XsdUtility::describe($object));
                    return $this->dispatch($innerType->getElements(), $schema, $object, $level + 1);
                } elseif($innerType instanceof All) {
                    $this->log($level, 'All', $type, XsdUtility::describe($object));
                    return $this->dispatch($innerType->getElements(), $schema, $object, $level + 1);
                } elseif($innerType instanceof Choice) {
                    $this->log($level, 'Choice', $type, XsdUtility::describe($object));
                    return $this->dispatch($innerType->getElements(), $schema, $object, $level + 1);
                } else {
                    throw new \RuntimeException(sprintf('Invalid Element complex type %s!', XsdUtility::describe($innerType)));
                }
            } elseif($realType instanceof SimpleType) {
                $this->log($level, 'SimpleType', $realType->getName(), $realType->getType()->getBase(), XsdUtility::describe($object));
                return (string)$object;
            } elseif(null === $realType) {
                $this->log($level, 'Null', '*NULL*', '*NULL*', XsdUtility::describe($object));
                return (string)$object;
            } else {
                throw new \RuntimeException(sprintf('Invalid Element string type %s!', XsdUtility::describe($realType)));
            }
        } else {
            throw new \RuntimeException(sprintf('Invalid Element type %s', XsdUtility::describe($type)));
        }
    }

    private function dispatch(array $elements, Schema $schema, XmlObjectInterface $object, int $level): string
    {
        return array_reduce($elements, function(string $state, Element $element) use($schema, $object, $level): string {
            $value = $object->{'get'.ucfirst($element->getName())}();
            $this->log($level, 'DElement', $element->getName(), XsdUtility::describe($value), is_array($value) ? 'MULTI' : '');

            switch(true) {
                case null === $value: { $value = []; break; }
                case is_scalar($value): { $value = [$value]; break; }
                case is_object($value): { $value = [$value]; break; }
            }

            return $state.array_reduce($value, function(string $state, $innerObject) use($element, $schema, $object, $level): string {
                $prefix = XsdUtility::xmlPrefix($this->schemas, $object->getXmlNamespace(), $schema);
                $content = $this->element($element, $schema, $innerObject, $level + 1);

                return $state.'<'.$prefix.$element->getName().'>'.$content.'</'.$prefix.$element->getName().'>';
            }, '');
        }, '');
    }

    /* --- UTILITIES -------------------------------------------------------- */

    private function findTypeEverywhere(Schema $schema, string $ns, string $type)
    {
        $qualified = XsdUtility::qualifiedName($this->schemas, $schema, $type);
        if(XsdUtility::isPrimitiveType($qualified)) {
            return null;
        } else {
            try {
                return XsdUtility::findTypeEverywhere($this->schemas, $schema, $type);
            } catch(\Exception $e) {
                return XsdUtility::findTypeEverywhere($this->schemas, $this->schemas->findSchemaByNs($ns), $type);
            }
        }
    }

    private function log(int $level, string ...$message): void
    {
        XsdUtility::log($this->logger, $level, ...$message);
    }
}
