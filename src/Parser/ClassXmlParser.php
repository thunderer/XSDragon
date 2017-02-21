<?php
declare(strict_types=1);
namespace Thunder\Xsdragon\Parser;

use Thunder\Xsdragon\Logger\LoggerInterface;
use Thunder\Xsdragon\NamespaceResolver\NamespaceResolverInterface;
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

final class ClassXmlParser implements XmlParserInterface
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
        $xml = new \DOMDocument();
        $xml->loadXML($xmlString);
        $namespaces = [];
        $this->findXmlNamespaces($xml->documentElement, $namespaces);

        $schema = $this->schemas->findSchemaByNs($namespaces['']);
        $node = $xml->documentElement;
        $type = $schema->findTypeByName($node->nodeName);
        $this->log("\n".'ROOT '.$type->getName().' - '.$node->nodeName, 0);

        switch(true) {
            case $type instanceof Element: { $arg = $this->element($node, $schema, $type, 0); break; }
            case $type instanceof ComplexType: { $arg = $this->complexType($node, $schema, $type, 0); break; }
            default: { throw new \RuntimeException(sprintf('Invalid root %s!', get_class($type))); }
        }

        return $this->createInstanceWithArgs($schema, $type->getName(), [$arg]);
    }

    private function element(\DOMElement $xml, Schema $schema, Element $element, int $level)
    {
        $this->logInit('Element', $element->getName(), $xml->nodeName, $level);

        switch($element->getType()) {
            case 'xsd:dateTime': { return $xml->nodeValue; }
            case 'xsd:string': { return $xml->nodeValue; }
        }

        list($schema, $type) = $this->findTypeEverywhere($schema, $element->getType());

        if($type instanceof ComplexType) {
            $complexType = $this->complexType($xml, $schema, $type, $level + 1);
            $this->logExit('Element', $element->getName(), $xml->nodeName, $level);

            return $complexType;
        }

        if($type instanceof SimpleType) {
            $simpleType = $this->simpleType($xml, $schema, $type, $level + 1);
            $this->logExit('Element', $element->getName(), $xml->nodeName, $level);

            return $simpleType;
        }

        throw new \RuntimeException(sprintf('Invalid Element inner %s!', $type ? get_class($type) : gettype($type)));
    }

    private function complexType(\DOMElement $xml, Schema $schema, ComplexType $complexType, int $level)
    {
        $this->logInit('ComplexType', $complexType->getName(), $xml->nodeName, $level);
        $type = $complexType->getType();

        if($type instanceof Sequence) {
            $args = $this->sequence($complexType, $type, $xml, $schema, $level + 1);
            $this->logExit('ComplexType', $complexType->getName(), $xml->nodeName, $level);

            return $this->createInstanceWithArgs($schema, $complexType->getName(), $args);
        }

        if ($type instanceof All) {
            $all = $this->all($complexType, $type, $xml, $schema, $level + 1);
            $this->logExit('ComplexType', $complexType->getName(), $xml->nodeName, $level);

            return $all;
        }

        if($type instanceof Choice) {
            $choice = $this->choice($complexType, $type, $xml, $schema, $level + 1);
            $this->logExit('ComplexType', $complexType->getName(), $xml->nodeName, $level);

            return $choice;
        }

        if(null === $type) {
            $attributes = $this->attributes($complexType, $xml, $schema, $level + 1);
            $this->logExit('ComplexType', $complexType->getName(), $xml->nodeName, $level);

            return $this->createInstanceWithArgs($schema, $complexType->getName(), $attributes);
        }

        throw new \RuntimeException(sprintf('Invalid ComplexType inner %s!', is_object($type) ? get_class($type) : gettype($type)));
    }

    private function sequence(ComplexType $complexType, Sequence $type, \DOMElement $xml, Schema $schema, int $level)
    {
        $this->logInit('Sequence', $complexType->getName(), $xml->nodeName, $level);
        $args = $this->attributes($complexType, $xml, $schema, $level);
        foreach($type->getElements() as $element) {
            switch(true) {
                case $element instanceof Element: {
                    $xmlElements = $this->getXmlChildren($xml, $element->getName());
                    $argElements = [];
                    foreach($xmlElements as $xmlElement) {
                        $argElements[] = $this->element($xmlElement, $schema, $element, $level + 1);
                    }
                    $args[] = $element->getMaxOccurs() === 'unbounded' ? $argElements : ($argElements[0] ?? null);
                    break;
                }
                case $element instanceof Choice: {
                    $args[] = $this->choice($complexType, $element, $xml, $schema, $level + 1);
                    break;
                }
                case $element instanceof Sequence: {
                    $seqElements = $this->sequence($complexType, $element, $xml, $schema, $level + 1);

                    $suffix = $complexType->getType() instanceof Sequence ? '_Sequence' : '';
                    $args[] = $this->createInstanceWithArgs($schema, $complexType->getName().$suffix, $seqElements);
                    break;
                }
                default: {
                    throw new \RuntimeException(sprintf('Invaslid ComplexType/Sequence inner %s!', $element ? get_class($element) : gettype($element)));
                }
            }
        }
        $this->logExit('Sequence', $complexType->getName(), $xml->nodeName, $level);

        return $args;
    }

    private function all(ComplexType $complexType, All $type, \DOMElement $xml, Schema $schema, int $level)
    {
        $this->logInit('All', $complexType->getName(), $xml->nodeName, $level);
        $args = $this->attributes($complexType, $xml, $schema, $level);
        foreach($type->getElements() as $element) {
            if($element instanceof Element) {
                $this->logInit('A:Element', $element->getName(), $xml->nodeName, $level);
                $args[] = $this->element($xml->getElementsByTagName($element->getName())[0], $schema, $element, $level + 1);
                $this->logExit('A:Element', $element->getName(), $xml->nodeName, $level);
            } else {
                throw new \RuntimeException(sprintf('Invalid ComplexType/All inner %s!', $element ? get_class($element) : gettype($element)));
            }
        }
        $this->logExit('All', $complexType->getName(), $xml->nodeName, $level);

        return $this->createInstanceWithArgs($schema, $complexType->getName(), $args);
    }

    private function choice(ComplexType $complexType, Choice $choice, \DOMElement $xml, Schema $schema, int $level)
    {
        if($choice->getMaxOccurs() === 'unbounded') {
            $this->logInit('MChoice', $complexType->getName(), $xml->nodeName, $level);
            $args = []; // $this->processAttributes($complexType, $xml, $schema, $level);
            while(true) {
                foreach($xml->childNodes as $childNode) {
                    if(false === $childNode instanceof \DOMElement) {
                        $xml->removeChild($childNode);
                    }
                }
                $field = null;
                $option = null;
                foreach($choice->getElements() as $element) {
                    if($element instanceof Element) {
                        $xmlElements = $this->getXmlChildren($xml, $element->getName());
                        if($xmlElements) {
                            $field = $element->getName();
                            $option = [$this->element($xmlElements[0], $schema, $element, $level + 1)];
                            $xml->removeChild($xmlElements[0]);
                        }
                    } elseif($element instanceof Sequence) {
                        $seqOption = $this->sequence($complexType, $element, $xml, $schema, $level + 1);
                        if(array_filter($seqOption)) {
                            $field = $complexType->getName();
                            $option = $seqOption;
                        }
                    } else {
                        throw new \RuntimeException(sprintf('Invalid MChoice inner %s!', $element ? get_class($element) : gettype($element)));
                    }
                }
                if($xml->childNodes->length === 0) {
                    break;
                }
                $args[] = $this->createInstanceWithNamedCtor($schema, $complexType->getName().'_Choice', $field, $option);
            }
            $this->logExit('MChoice', $complexType->getName(), '', $level);

            return $args;
        }

        $this->logInit('Choice', $complexType->getName(), $xml->nodeName, $level);
        $args = $this->attributes($complexType, $xml, $schema, $level);
        $option = null;
        $field = null;
        foreach($choice->getElements() as $element) {
            if($element instanceof Element) {
                if($xml->getElementsByTagName($element->getName())->length) {
                    $field = $element->getName();
                    $args[] = $this->element($xml->getElementsByTagName($element->getName())[0], $schema, $element, $level + 1);
                }
            } elseif($element instanceof Sequence) {
                $seqOption = $this->sequence($complexType, $element, $xml, $schema, $level + 1);
                if(array_filter($seqOption)) {
                    $field = $complexType->getName();
                    $args = array_merge($args, $seqOption);
                }
            } else {
                throw new \RuntimeException(sprintf('Invalid Choice inner %s!', $element ? get_class($element) : gettype($element)));
            }
        }
        $this->logExit('Choice', $complexType->getName(), '', $level);

        $suffix = $complexType->getType() instanceof Choice ? '' : '_Choice';
        return $this->createInstanceWithNamedCtor($schema, $complexType->getName().$suffix, $field, $args);
    }

    private function simpleType(\DOMElement $xml, Schema $schema, SimpleType $simpleType, int $level)
    {
        $this->logValue('SimpleType', $simpleType->getName(), $xml->nodeName, $xml->nodeValue, $level);

        return $this->createInstanceWithArgs($schema, $simpleType->getName(), [$xml->nodeValue]);
    }

    private function attributes(ComplexType $complexType, \DOMElement $xml, Schema $schema, $level): array
    {
        return array_reduce($complexType->getAttributes(), function(array $args, Attribute $attribute) use($xml, $schema, $level) {
            $value = $xml->getAttribute($attribute->getName());
            $this->logValue('Attribute', $attribute->getName(), $xml->nodeName, $value ?: '[:EMPTY:]', $level);

            switch($attribute->getType()) {
                case 'xsd:string': { $args[] = $value; break; }
                case 'xsd:int': { $args[] = (int)$value; break; }
                case 'xsd:decimal': { $args[] = (string)$value; break; }
                case 'xsd:boolean': { $args[] = ['true' => true, 'false' => false][$value]; break; }
                default: { $args[] = $this->createInstanceWithArgs($schema, $attribute->getType(), [$value]); }
            }

            return $args;
        }, []);
    }

    /* --- UTILITIES -------------------------------------------------------- */

    private function findXmlNamespaces(\DOMElement $xml, array &$ns)
    {
        $ns[$xml->prefix] = $xml->namespaceURI;

        /** @var \DOMElement $node */
        foreach($xml->childNodes as $node) {
            if('#text' === $node->nodeName) { continue; }
            if('#comment' === $node->nodeName) { continue; }
            $this->findXmlNamespaces($node, $ns);
        }
    }

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

    private function createInstanceWithArgs(Schema $schema, string $name, array $args)
    {
        $this->logType('OBJECT', $name, '', '', 0);

        try {
            return (new \ReflectionClass($this->namespaceResolver->convertUriToFqcn($schema->getNamespace(), $name)))->newInstanceArgs($args);
        } catch(\Throwable $e) {
            throw new \RuntimeException($e->getMessage().' Class: '.$name.' Args: '.var_export($args, true));
        }
    }

    private function createInstanceWithNamedCtor(Schema $schema, string $name, string $property, array $args)
    {
        $this->logType('OBJECT', $name.'::'.$property, '', '', 0);

        try {
            return call_user_func_array([$this->namespaceResolver->convertUriToFqcn($schema->getNamespace(), $name), 'createFrom'.ucfirst($property)], $args);
        } catch(\Throwable $e) {
            throw new \RuntimeException($e->getMessage().' Class: '.$name.' Args: '.var_export($args, true));
        }
    }

    private function logType(string $logType, string $type, string $elementName, string $xmlName, int $level)
    {
        $this->log(sprintf("\e[36m%s\e[0m \e[38m%s\e[0m \e[32m%s\e[0m \e[31m%s\e[0m", $logType, $type, $elementName, $xmlName), $level);
    }

    private function logValue(string $type, string $elementName, string $xmlName, string $value, int $level)
    {
        $this->log(sprintf("\e[36mVALUE\e[0m \e[29m%s\e[0m \e[33m%s\e[0m \e[32m%s\e[0m \e[31m%s\e[0m \e[37m%s\e[0m", $level, $type, $elementName, $xmlName, $value), $level);
    }

    private function logInit(string $type, string $elementName, string $xmlName, int $level)
    {
        $this->log(sprintf("\e[34mINIT\e[0m \e[29m%s\e[0m \e[33m%s\e[0m \e[32m%s\e[0m \e[31m%s\e[0m", $level, $type, $elementName, $xmlName), $level);
    }

    private function logExit(string $type, string $elementName, string $xmlName, int $level)
    {
        $this->log(sprintf("\e[35mEXIT\e[0m \e[29m%s\e[0m \e[33m%s\e[0m \e[32m%s\e[0m \e[31m%s\e[0m", $level, $type, $elementName, $xmlName), $level);
    }

    private function log(string $message, int $level)
    {
        XsdUtility::log($this->logger, $level, $message);
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
