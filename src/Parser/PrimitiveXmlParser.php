<?php
declare(strict_types=1);
namespace Thunder\Xsdragon\Parser;

use Thunder\Xsdragon\Logger\LoggerInterface;
use Thunder\Xsdragon\NamespaceResolver\NamespaceResolverInterface;
use Thunder\Xsdragon\Schema\All;
use Thunder\Xsdragon\Schema\Attribute;
use Thunder\Xsdragon\Schema\Choice;
use Thunder\Xsdragon\Schema\ComplexContent;
use Thunder\Xsdragon\Schema\ComplexType;
use Thunder\Xsdragon\Schema\Element;
use Thunder\Xsdragon\Schema\Schema;
use Thunder\Xsdragon\Schema\SchemaContainer;
use Thunder\Xsdragon\Schema\Sequence;
use Thunder\Xsdragon\Schema\SimpleContent;
use Thunder\Xsdragon\Schema\SimpleType;
use Thunder\Xsdragon\Utility\XsdUtility;

final class PrimitiveXmlParser implements XmlParserInterface
{
    private $schemas;
    private $namespaceResolver;
    private $logger;

    public function __construct(SchemaContainer $schemas, NamespaceResolverInterface $namespaceResolver, LoggerInterface $logger)
    {
        $this->schemas = $schemas;
        $this->namespaceResolver = $namespaceResolver;
        $this->logger = $logger;
    }

    public function parse(string $xmlString)
    {
        $this->log(0, '');
        $xml = new \DOMDocument();
        $xml->loadXML($xmlString);
        $namespaces = [];
        $this->findXmlNamespaces($xml->documentElement, $namespaces);

        $schema = $this->schemas->findSchemaByNs($namespaces['']);
        $node = $xml->documentElement;
        $type = $schema->findTypeByName($node->nodeName);
        $this->log(0, 'ROOT', $type->getName(), $node->nodeName);

        if($type instanceof Element) {
            $args = $this->element($node, $schema, $type, 0);

            return is_array($args) ? $this->createInstanceWithArgs($schema, $type->getName(), $args, 0) : $args;
        }

        throw new \RuntimeException(sprintf('Invalid root `%s`!', XsdUtility::describe($type)));
    }

    private function element(\DOMElement $node = null, Schema $schema, Element $element, int $level)
    {
        if(null === $node) {
            $this->log($level, 'Null', $element->getName());
            return null;
        }

        $type = $element->getType();
        if(is_string($type)) {
            $qualified = XsdUtility::qualifiedName($this->schemas, $schema, $type);
            if(XsdUtility::isPrimitiveType($qualified)) {
                $this->log($level, 'Primitive', $type, $element->getName(), $node->nodeValue);
                return $node->nodeValue;
            }

            list($schema, $realType) = $this->findTypeEverywhere($schema, $type);
            if($realType instanceof ComplexType) {
                return $this->complexTypeBody($node, $realType, $schema, $element, $level);
            } elseif($realType instanceof SimpleType) {
                $this->log($level, 'SimpleType', $node->nodeName, $realType->getName(), $node->nodeValue);
                return $node->nodeValue;
            } else {
                throw new \RuntimeException(sprintf('Invalid Element string type %s!', XsdUtility::describe($realType)));
            }
        } elseif($type instanceof ComplexType) {
            $this->log($level, 'ComplexType', $node->nodeName, '*INLINE*', $node->nodeValue);
            return $this->complexTypeBody($node, $type, $schema, $element, $level);
        } elseif($type instanceof SimpleType) {
            $this->log($level, 'SimpleType', $node->nodeName, '*INLINE*', $node->nodeValue);
            return $node->nodeValue;
        } else {
            throw new \RuntimeException(sprintf('Invalid Element type %s', XsdUtility::describe($type)));
        }
    }

    private function complexTypeBody(\DOMElement $node, ComplexType $complexType, Schema $schema, Element $element, int $level)
    {
        $name = $complexType->getName();
        $attributes = $this->dispatch($name, $element, $complexType->getAttributes(), $node, $schema, $level);
        $innerType = $complexType->getType();
        if($innerType instanceof Sequence) {
            $this->log($level, 'Sequence', $node->nodeName, $name);
            return array_merge($attributes, $this->dispatch($name, $element, $innerType->getElements(), $node, $schema, $level + 1));
        } elseif($innerType instanceof All) {
            $this->log($level, 'All', $node->nodeName, $name);
            return array_merge($attributes, $this->dispatch($name, $element, $innerType->getElements(), $node, $schema, $level + 1));
        } elseif($innerType instanceof Choice) {
            $this->log($level, 'Choice', $node->nodeName, $name);
            return $this->choice($element, $attributes, $innerType, $node, $schema, $level + 1);
        } elseif(null === $innerType) {
            $this->log($level, 'Null', $node->nodeName);
            return $this->createInstanceWithArgs($schema, $name, $attributes, $level);
        } elseif($innerType instanceof SimpleContent) {
            $this->log($level, 'SimpleContent', $node->nodeName, $name.'_'.$element->getName());
            $attributes = $this->dispatch($name, $element, $innerType->getType()->getAttributes(), $node, $schema, $level);
            return array_merge($attributes, [$node->nodeValue]);
        } elseif($innerType instanceof ComplexContent) {
            $this->log($level, 'ComplexContent', $node->nodeName, $name);
            $extensionItems = $this->complexTypeBody($node, XsdUtility::findTypeEverywhere($this->schemas, $schema, $innerType->getType()->getBase()), $schema, $element, $level);
            return array_merge($extensionItems, $this->dispatch($name, $element, $innerType->getType()->getElements()[0]->getElements(), $node, $schema, $level + 1));
        } else {
            throw new \RuntimeException(sprintf('Invalid inner type %s!', XsdUtility::describe($innerType)));
        }
    }

    private function choice(Element $parent, array $attributes, Choice $choice, \DOMElement $node, Schema $schema, int $level)
    {
        $options = [];
        while(true) {
            foreach($node->childNodes as $childNode) {
                if(false === $childNode instanceof \DOMElement) {
                    $node->removeChild($childNode);
                }
            }
            if($node->childNodes->length === 0) {
                return $options;
            }
            foreach($choice->getElements() as $element) {
                if($element instanceof Element) {
                    $this->log($level, 'Choice/Element', $node->nodeName, $element->getName(), $parent->getType());
                    $value = $this->getXmlChildren($node, $element->getName());
                    $value = array_shift($value);
                    if($value) {
                        $node->removeChild($value);
                        $args = $this->element($value, $schema, $element, $level + 1);
                        $object = is_array($args) ? $this->createInstanceWithArgs($schema, $element->getType(), $args, $level + 1) : $args;

                        $choiceParentType = $this->findTypeEverywhere($this->schemas->findSchemaByNs($parent->getNamespaceUri()), $parent->getType())[1]->getType();
                        $suffix = $choiceParentType === $choice ? '' : '_Choice';

                        // FIXME: find a better way to generate root-level Element with Choice
                        $options[] = is_object($object) && $level === 1
                            ? $this->createInstanceWithNamedCtor($schema, $parent->getName().$suffix, $element->getName(), array_merge($attributes, [$object]), $level)
                            : $this->createInstanceWithNamedCtor($schema, $parent->getType().$suffix, $element->getName(), array_merge($attributes, [$object]), $level);
                    }
                } elseif($element instanceof Sequence) {
                    $this->log($level, 'Choice/Sequence', $node->nodeName, $parent->getType());
                    $args = [];
                    // FIXME: should be probably a dispatch() call
                    foreach($element->getElements() as $seqElement) {
                        if($seqElement instanceof Element) {
                            $this->log($level + 1, 'Choice/Sequence/Element', $node->nodeName, $seqElement->getName(), $parent->getType());
                            $value = $node->getElementsByTagName($seqElement->getName())[0];
                            if($value) {
                                $arg = $this->element($value, $schema, $seqElement, $level + 2);
                                $args[] = is_array($arg) ? $this->createInstanceWithArgs($schema, $seqElement->getType(), $arg, $level + 1) : $arg;
                            } else {
                                $args[] = null;
                            }
                        } else {
                            throw new \RuntimeException('Woah, mate, my head is going to explode!');
                        }
                    }

                    // FIXME: XSD should handle this, but I'm unsure about partial elements
                    if(array_filter($args)) {
                        $choiceParentType = $this->findTypeEverywhere($this->schemas->findSchemaByNs($parent->getNamespaceUri()), $parent->getType())[1]->getType();
                        $suffix = $choiceParentType === $choice ? '' : '_Choice';

                        $options[] = $this->createInstanceWithNamedCtor($schema, $parent->getType().$suffix, $parent->getType().'_Sequence', array_merge($attributes, $args), $level);
                    }
                } else {
                    throw new \RuntimeException(sprintf('Invalid Choice element %s!', XsdUtility::describe($element)));
                }
            }

            // this is not a multiple choice, so we return the first match
            // FIXME: detect multiple choice correctly
            if('unbounded' !== $choice->getMaxOccurs()) {
                if(1 !== count($options)) {
                    throw new \RuntimeException('Choice caught no options!');
                }

                return array_shift($options);
            }
        }

        throw new \RuntimeException('Choice caught no options!');
    }

    private function dispatch(string $parentName, Element $parent, array $elements, \DOMElement $node, Schema $schema, int $level): array
    {
        $result = [];

        foreach($elements as $element) {
            if($element instanceof Element) {
                $realType = $this->resolveRealTypeName($schema, $element->getType()) ?: $parentName.'_'.ucfirst($element->getName());
                $isPrimitive = XsdUtility::isPrimitiveType(XsdUtility::qualifiedName($this->schemas, $schema, $realType));
                $isSingle = $element->getMaxOccurs() !== 'unbounded';
                $xmlElements = $node->getElementsByTagName($element->getName());
                $logArgs = ['Element', $node->nodeName, $element->getName(), XsdUtility::occurs($element), $realType ?: '*EMPTY*',
                    ($isPrimitive ? 'Primitive' : 'Complex').'/'.($isSingle ? 'Single' : 'Multiple')];

                if($isPrimitive) {
                    if($isSingle) {
                        $this->log($level, ...$logArgs);
                        $result[] = $this->element($xmlElements[0], $schema, $element, $level + 1);
                        continue;
                    }

                    $items = [];
                    foreach($xmlElements as $xmlElement) {
                        $this->log($level, ...$logArgs);
                        $items[] = $this->element($xmlElement, $schema, $element, $level + 1);
                    }
                    $result[] = $items;
                    continue;
                }

                if($isSingle) {
                    $this->log($level, ...$logArgs);
                    if(null === $xmlElements[0]) {
                        $this->log($level + 1, 'NULL');
                        $result[] = null;
                        continue;
                    }
                    $args = $this->element($xmlElements[0], $schema, $element, $level + 1);
                    $result[] = is_array($args) ? $this->createInstanceWithArgs($schema, $realType, $args, $level) : $args;
                    continue;
                }

                $items = [];
                foreach($xmlElements as $xmlElement) {
                    $this->log($level, ...$logArgs);
                    $args = $this->element($xmlElement, $schema, $element, $level + 1);
                    $items[] = is_array($args) ? $this->createInstanceWithArgs($schema, $realType, $args, $level) : $args;
                }
                $result[] = $items;
            } elseif($element instanceof Sequence) {
                $this->log($level, 'DSequence', $node->nodeName);
                $args = $this->dispatch($parentName, $parent, $element->getElements(), $node, $schema, $level + 1);
                $result[] = $this->createInstanceWithArgs($schema, $parent->getType().'_Sequence', $args, $level);
            } elseif($element instanceof Choice) {
                $this->log($level, 'DChoice', $node->nodeName);
                $result[] = $this->choice($parent, [], $element, $node, $schema, $level + 1);
            } elseif($element instanceof Attribute) {
                $value = $node->getAttribute($element->getName());
                switch($element->getType()) {
                    // FIXME: make this handle namespaces
                    case 'xsd:string': { $value = (string)$value; break; }
                    case 'xsd:int': { $value = (int)$value; break; }
                    case 'xsd:boolean': { $value = ['true' => true, 'false' => false][$value]; break; }
                    // default: { $value = $this->createInstanceWithArgs($schema, $element->getType(), [$value], $level); }
                }
                $this->log($level, 'Attribute', $node->nodeName, $element->getName(), '`'.XsdUtility::describe($value).'`', XsdUtility::describe($element->getType()), $element->getUse());
                $result[] = $value;
            } else {
                throw new \RuntimeException(sprintf('Invalid dispatch type %s!', XsdUtility::describe($element)));
            }
        }

        return $result;
    }

    /* --- UTILITIES -------------------------------------------------------- */

    private function getXmlChildren(\DOMElement $xml, string $name)
    {
        $children = [];
        /** @var \DOMElement $node */
        foreach($xml->childNodes as $node) {
            if($node->localName === $name) {
                $children[] = $node;
            }
        }

        return $children;
    }

    private function findXmlNamespaces(\DOMElement $xml, array &$ns)
    {
        if(false === array_key_exists($xml->prefix, $ns)) {
            $this->log(0, 'Namespace', $xml->prefix ?: '*EMPTY*', $xml->namespaceURI);
            $ns[$xml->prefix] = $xml->namespaceURI ?? $ns[$xml->prefix] ?? null;
        }

        /** @var \DOMElement $node */
        foreach($xml->childNodes as $node) {
            if('#text' === $node->nodeName) { continue; }
            if('#comment' === $node->nodeName) { continue; }
            $this->findXmlNamespaces($node, $ns);
        }
    }

    private function createInstanceWithArgs(Schema $schema, string $name, array $args, int $level)
    {
        $this->log($level, 'OBJECT', ucfirst($name), XsdUtility::describe($args));

        try {
            if(strpos($name, ':')) {
                list($prefix, $name) = explode(':', $name, 2);
                $schemaUris = $this->schemas->findUrisFor($schema);
                $schema = $this->schemas->findSchemaByNs($schemaUris[$prefix]);
            }

            return (new \ReflectionClass($this->namespaceResolver->convertUriToFqcn($schema->getNamespace(), $name)))->newInstanceArgs($args);
        } catch(\Throwable $e) {
            throw new \RuntimeException($e->getMessage().' Class: '.$name.' Args: '.var_export($args, true).' XMLNS: '.$schema->getNamespace());
        }
    }

    private function resolveRealTypeName(Schema $schema, $type): string
    {
        if($type instanceof SimpleType) {
            $type = $type->getType()->getBase();
        }

        if($type instanceof ComplexType) {
            $type = $type->getName();
        }

        if(false === is_string($type)) {
            throw new \RuntimeException(sprintf('Invalid parameter type %s!', XsdUtility::describe($type)));
        }

        $qualified = XsdUtility::qualifiedName($this->schemas, $schema, $type);
        if(strpos($type, ':') && false === XsdUtility::isPrimitiveType($qualified)) {
            try {
                $realType = $schema->findTypeByName($type);
            } catch(\RuntimeException $e) {
                list($prefix, $newType) = explode(':', $type, 2);
                try {
                    $realType = $schema->findTypeByName($newType);
                } catch(\RuntimeException $e) {
                    $schemaUris = $this->schemas->findUrisFor($schema);
                    if(false === array_key_exists($prefix, $schemaUris)) {
                        throw new \RuntimeException(sprintf('Prefix `%s` not found among schema `%s` namespaces %s!', $prefix, $schema->getNamespace(), var_export($schemaUris, true)));
                    }
                    $realType = $this->schemas->findSchemaByNs($schemaUris[$prefix])->findTypeByName($newType);
                }
            }
            if($realType instanceof SimpleType) {
                $type = $realType->getType()->getBase();
            }
        }

        try {
            $typeObject = $schema->findTypeByName($type);

            if($typeObject instanceof SimpleType) {
                return $typeObject->getType()->getBase();
            }

            return $type;
        } catch(\RuntimeException $e) {
            return $type;
        }
    }

    private function createInstanceWithNamedCtor(Schema $schema, string $name, string $property, array $args, int $level)
    {
        $this->log($level, 'OBJECT', ucfirst($name).'::'.$property, XsdUtility::describe($args));

        try {
            if(strpos($name, ':')) {
                list($prefix, $name) = explode(':', $name, 2);
            }
            $fqcn = $this->namespaceResolver->convertUriToFqcn($schema->getNamespace(), $name);

            return call_user_func_array([$fqcn, 'createFrom'.ucfirst($property)], $args);
        } catch(\Throwable $e) {
            throw new \RuntimeException($e->getMessage().' Class: '.$name.' Args: '.var_export($args, true).' Methods: '.var_export(get_class_methods($fqcn), true));
        }
    }

    private function log(int $level, string ...$message)
    {
        XsdUtility::log($this->logger, $level, ...$message);
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
}
