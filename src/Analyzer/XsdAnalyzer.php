<?php
declare(strict_types=1);
namespace Thunder\Xsdragon\Analyzer;

use Thunder\Xsdragon\Logger\LoggerInterface;
use Thunder\Xsdragon\Schema\All;
use Thunder\Xsdragon\Schema\Attribute;
use Thunder\Xsdragon\Schema\Choice;
use Thunder\Xsdragon\Schema\ComplexContent;
use Thunder\Xsdragon\Schema\ComplexType;
use Thunder\Xsdragon\Schema\Element;
use Thunder\Xsdragon\Schema\Extension;
use Thunder\Xsdragon\Schema\Restrictions;
use Thunder\Xsdragon\Schema\Schema;
use Thunder\Xsdragon\Schema\SchemaContainer;
use Thunder\Xsdragon\Schema\Sequence;
use Thunder\Xsdragon\Schema\SimpleContent;
use Thunder\Xsdragon\Schema\SimpleType;
use Thunder\Xsdragon\Schema\Union;
use Thunder\Xsdragon\Utility\XsdUtility;

final class XsdAnalyzer
{
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function createFromStrings(array $strings): SchemaContainer
    {
        $schemas = [];
        $doc = new \DOMDocument();
        foreach($strings as $string) {
            $xml = clone $doc;
            $xml->loadXML($string);

            foreach($this->rootNode($xml) as $schema) {
                $schemas[] = $schema;
            }
        }

        return new SchemaContainer($schemas);
    }

    public function createFromDirectories(array $paths): SchemaContainer
    {
        $files = [];

        foreach($paths as $path) {
            /** @var \SplFileInfo $file */
            foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path)) as $file) {
                if($file->getExtension() !== 'xsd') { continue; }

                $files[] = file_get_contents($file->getPathname());
            }
        }

        return $this->createFromStrings($files);
    }

    private function rootNode(\DOMDocument $node): array
    {
        $schemas = [];

        $nodeName = $this->prepareXmlName($node);
        /** @var \DOMElement $node */
        foreach($node->childNodes as $child) {
            $childName = $this->prepareXmlName($child);
            switch($childName) {
                case '#comment': { break; }
                case '{http://www.w3.org/2001/XMLSchema}schema': { $schemas[] = $this->schema($child); break; }
                default: { throw new \RuntimeException(sprintf('Unhandled %s node %s!', $nodeName, $childName)); }
            }
        }

        return $schemas;
    }

    private function schema(\DOMElement $node): Schema
    {
        $nsAttrs = (new \DOMXPath($node->ownerDocument))->query('namespace::*');
        $namespaces = [];
        foreach($nsAttrs as $attr) {
            // FIXME: make this work on URIs, not hardcoded aliases
            if(in_array($attr->nodeName, ['xmlns', 'xmlns:xml'], true)) {
                continue;
            }
            $namespaces[$attr->localName] = $attr->nodeValue;
        }

        $namespaceUri = $node->getAttribute('xmlns');
        $this->log(0, 'Schema', $namespaceUri);
        // FIXME: make Schema immutable, remove add*()ers
        $schema = new Schema($namespaceUri, $namespaces);

        $nodeName = $this->prepareXmlName($node);
        /** @var \DOMElement $child */
        foreach($node->childNodes as $child) {
            $childName = $this->prepareXmlName($child);
            switch($childName) {
                case '#text': { break; }
                case '#comment': { break; }
                case '{http://www.w3.org/2001/XMLSchema}annotation': { $documentation = $this->annotation($child); break; }
                case '{http://www.w3.org/2001/XMLSchema}simpleType': { $schema->addSimpleType($this->simpleType($namespaceUri, $child)); break; }
                case '{http://www.w3.org/2001/XMLSchema}import': { $schema->addImport($this->import($child)); break; }
                case '{http://www.w3.org/2001/XMLSchema}include': { break; } // FIXME: add support
                case '{http://www.w3.org/2001/XMLSchema}element': { $schema->addElement($this->element($namespaceUri, $child)); break; }
                case '{http://www.w3.org/2001/XMLSchema}complexType': { $schema->addComplexType($this->complexType($namespaceUri, $child)); break; }
                case '{http://www.w3.org/2001/XMLSchema}attributeGroup': { break; } // FIXME: add support
                default: { throw new \RuntimeException(sprintf('Unhandled %s node %s!', $nodeName, $childName)); }
            }
        }

        return $schema;
    }

    private function import(\DOMElement $node)
    {
        return $node->getAttribute('namespace');
    }

    private function element(string $namespaceUri, \DOMElement $node): Element
    {
        $documentation = null;
        $simpleType = null;
        $complexType = null;

        $nodeName = $this->prepareXmlName($node);
        /** @var \DOMElement $child */
        foreach($node->childNodes as $child) {
            $childName = $this->prepareXmlName($child);
            switch($childName) {
                case '#text': { break; }
                case '{http://www.w3.org/2001/XMLSchema}annotation': { $documentation = $this->annotation($child); break; }
                case '{http://www.w3.org/2001/XMLSchema}simpleType': { $simpleType = $this->simpleType($namespaceUri, $child); break; }
                case '{http://www.w3.org/2001/XMLSchema}complexType': { $complexType = $this->complexType($namespaceUri, $child); break; }
                default: { throw new \RuntimeException(sprintf('Unhandled %s node %s!', $nodeName, $childName)); }
            }
        }

        $type = $node->hasAttribute('type') ? $node->getAttribute('type') : null;
        $types = array_filter([$type, $simpleType, $complexType]);
        if(count($types) === 1) {
            $type = array_shift($types);
        } elseif(empty($types)) {
            $type = null;
        } else {
            throw new \RuntimeException(vsprintf('Element can have either type attribute, SimpleType, or ComplexType, got %s, XML %s!', [
                XsdUtility::describe($types),
                XsdUtility::formatXmlNode($node),
            ]));
        }

        $minOccurs = $this->extractOccursAttributeValue($node, 'minOccurs');
        $maxOccurs = $this->extractOccursAttributeValue($node, 'maxOccurs');
        $nullable = $node->getAttribute('nillable') === 'true';

        return new Element($namespaceUri, $node->getAttribute('name'), $type, $minOccurs, $maxOccurs, $nullable, $documentation);
    }

    private function complexType(string $namespaceUri, \DOMElement $node): ComplexType
    {
        $documentation = null;
        $sequence = null;
        $all = null;
        $complexContent = null;
        $simpleContent = null;
        $choice = null;
        $attributes = [];

        $nodeName = $this->prepareXmlName($node);
        /** @var \DOMElement $child */
        foreach($node->childNodes as $child) {
            $childName = $this->prepareXmlName($child);
            switch($childName) {
                case '#text': { break; }
                case '#comment': { break; }
                case '{http://www.w3.org/2001/XMLSchema}annotation': { $documentation = $this->annotation($child); break; }
                case '{http://www.w3.org/2001/XMLSchema}sequence': { $sequence = $this->sequence($namespaceUri, $child); break; }
                case '{http://www.w3.org/2001/XMLSchema}attribute': { $attributes[] = $this->attribute($namespaceUri, $child); break; }
                case '{http://www.w3.org/2001/XMLSchema}all': { $all = $this->all($namespaceUri, $child); break; }
                case '{http://www.w3.org/2001/XMLSchema}complexContent': { $complexContent = $this->complexContent($namespaceUri, $child); break; }
                case '{http://www.w3.org/2001/XMLSchema}simpleContent': { $simpleContent = $this->simpleContent($namespaceUri, $child); break; }
                case '{http://www.w3.org/2001/XMLSchema}choice': { $choice = $this->choice($namespaceUri, $child); break; }
                case '{http://www.w3.org/2001/XMLSchema}attributeGroup': { break; } // FIXME: add support
                default: { throw new \RuntimeException(sprintf('Unhandled %s node %s!', $nodeName, $childName)); }
            }
        }

        $types = array_filter([$sequence, $all, $complexContent, $simpleContent, $choice]);
        if(count($types) > 1) {
            throw new \RuntimeException(vsprintf('ComplexType can have at most one inner type, %s found: %s! XML: %s', [
                count($types),
                implode(', ', array_map('get_class', $types)),
                $node->ownerDocument->saveXML($node),
            ]));
        }

        return new ComplexType($namespaceUri, $node->getAttribute('name'), array_shift($types), $attributes, $documentation);
    }

    private function simpleType(string $namespaceUri, \DOMElement $node): SimpleType
    {
        $documentation = null;
        $union = null;
        $restrictions = null;

        $nodeName = $this->prepareXmlName($node);
        /** @var \DOMElement $child */
        foreach($node->childNodes as $child) {
            $childName = $this->prepareXmlName($child);
            switch($childName) {
                case '#text': { break; }
                case '{http://www.w3.org/2001/XMLSchema}union': { $union = $this->union($namespaceUri, $child); break; } // FIXME: add support
                case '{http://www.w3.org/2001/XMLSchema}annotation': { $documentation = $this->annotation($child); break; }
                case '{http://www.w3.org/2001/XMLSchema}restriction': { $restrictions = $this->restrictions($child); break; }
                case '{http://www.w3.org/2001/XMLSchema}list': { break; } // FIXME: add support
                // FIXME: throw my own exception with both nodes and produce proper message with formatted XML
                default: { throw new \RuntimeException(sprintf('Unhandled %s node %s!', $nodeName, $childName)); }
            }
        }

        return new SimpleType($namespaceUri, $node->getAttribute('name'), $documentation, $restrictions ?: $union);
    }

    private function union(string $namespaceUri, \DOMElement $node): Union
    {
        // FIXME: better validation (regex), preg_split() for valid, but malformed content?
        $memberTypes = $node->hasAttribute('memberTypes') ? explode(' ', $node->getAttribute('memberTypes')) : [];

        return new Union($namespaceUri, $memberTypes);
    }

    private function sequence(string $namespaceUri, \DOMElement $node): Sequence
    {
        $elements = [];

        $nodeName = $this->prepareXmlName($node);
        /** @var \DOMElement $child */
        foreach($node->childNodes as $child) {
            $childName = $this->prepareXmlName($child);
            switch($childName) {
                case '#comment': { break; }
                case '#text': { break; }
                case '{http://www.w3.org/2001/XMLSchema}annotation': { $documentation = $this->annotation($child); break; }
                case '{http://www.w3.org/2001/XMLSchema}element': { $elements[] = $this->element($namespaceUri, $child); break; }
                case '{http://www.w3.org/2001/XMLSchema}choice': { $elements[] = $this->choice($namespaceUri, $child); break; }
                case '{http://www.w3.org/2001/XMLSchema}sequence': { $elements[] = $this->sequence($namespaceUri, $child); break; }
                case '{http://www.w3.org/2001/XMLSchema}any': { break; } // FIXME: add support
                default: { throw new \RuntimeException(sprintf('Unhandled %s node %s!', $nodeName, $childName)); }
            }
        }

        return new Sequence($namespaceUri, $elements);
    }

    private function choice(string $namespaceUri, \DOMElement $node): Choice
    {
        $elements = [];

        $nodeName = $this->prepareXmlName($node);
        /** @var \DOMElement $child */
        foreach($node->childNodes as $child) {
            $childName = $this->prepareXmlName($child);
            switch($childName) {
                case '#text': { break; }
                case '{http://www.w3.org/2001/XMLSchema}annotation': { $documentation = $this->annotation($child); break; }
                case '{http://www.w3.org/2001/XMLSchema}sequence': { $elements[] = $this->sequence($namespaceUri, $child); break; }
                case '{http://www.w3.org/2001/XMLSchema}element': { $elements[] = $this->element($namespaceUri, $child); break; }
                default: { throw new \RuntimeException(sprintf('Unhandled %s node %s!', $nodeName, $childName)); }
            }
        }

        $minOccurs = $this->extractOccursAttributeValue($node, 'minOccurs');
        $maxOccurs = $this->extractOccursAttributeValue($node, 'maxOccurs');

        return new Choice($namespaceUri, $elements, $minOccurs, $maxOccurs);
    }

    private function all(string $namespaceUri, \DOMElement $node): All
    {
        $elements = [];

        $nodeName = $this->prepareXmlName($node);
        /** @var \DOMElement $child */
        foreach($node->childNodes as $child) {
            $childName = $this->prepareXmlName($child);
            switch($childName) {
                case '#text': { break; }
                case '{http://www.w3.org/2001/XMLSchema}element': { $elements[] = $this->element($namespaceUri, $child); break; }
                default: { throw new \RuntimeException(sprintf('Unhandled %s node %s!', $nodeName, $childName)); }
            }
        }

        return new All($namespaceUri, $elements);
    }

    private function attribute(string $namespaceUri, \DOMElement $node): Attribute
    {
        $simpleType = null;

        $nodeName = $this->prepareXmlName($node);
        /** @var \DOMElement $child */
        foreach($node->childNodes as $child) {
            $childName = $this->prepareXmlName($child);
            switch($childName) {
                case '#text': { break; }
                case '{http://www.w3.org/2001/XMLSchema}annotation': { $documentation = $this->annotation($child); break; }
                case '{http://www.w3.org/2001/XMLSchema}simpleType': { $simpleType = $this->simpleType($namespaceUri, $child); break; }
                default: { throw new \RuntimeException(sprintf('Unhandled %s node %s!', $nodeName, $childName)); }
            }
        }

        $use = $node->hasAttribute('use') ? $node->getAttribute('use') : 'optional';
        $type = $node->hasAttribute('type') ? $node->getAttribute('type') : $simpleType;

        return new Attribute($namespaceUri, $node->getAttribute('name'), $type, $use);
    }

    private function restrictions(\DOMElement $node): Restrictions
    {
        $hasEnums = false;
        $hasPatterns = false;
        $enumerations = [];
        $patterns = [];
        $minLength = null;
        $maxLength = null;
        $minInclusive = null;
        $maxInclusive = null;
        $length = null;
        $fractionDigits = null;

        $nodeName = $this->prepareXmlName($node);
        /** @var \DOMElement $child */
        foreach($node->childNodes as $child) {
            $childName = $this->prepareXmlName($child);
            switch($childName) {
                case '#comment': { break; }
                case '#text': { break; }
                case '{http://www.w3.org/2001/XMLSchema}enumeration': { $hasEnums = true; $enumerations[] = $child->getAttribute('value'); break; }
                case '{http://www.w3.org/2001/XMLSchema}pattern': { $hasPatterns = true; $patterns[] = $child->getAttribute('value'); break; }
                case '{http://www.w3.org/2001/XMLSchema}minLength': { $minLength = (int)$child->getAttribute('value'); break; }
                case '{http://www.w3.org/2001/XMLSchema}maxLength': { $maxLength = (int)$child->getAttribute('value'); break; }
                case '{http://www.w3.org/2001/XMLSchema}minInclusive': { $minInclusive = (int)$child->getAttribute('value'); break; }
                case '{http://www.w3.org/2001/XMLSchema}maxInclusive': { $maxInclusive = (int)$child->getAttribute('value'); break; }
                case '{http://www.w3.org/2001/XMLSchema}length': { $length = (int)$child->getAttribute('value'); break; }
                case '{http://www.w3.org/2001/XMLSchema}fractionDigits': { $fractionDigits = (int)$child->getAttribute('value'); break; }
                default: { throw new \RuntimeException(sprintf('Unhandled restriction %s!', $childName)); }
            }
        }

        return new Restrictions($node->getAttribute('base'),
            $hasEnums ? $enumerations : null,
            $hasPatterns ? $patterns : null,
            $length, $minLength, $maxLength, $minInclusive, $maxInclusive, $fractionDigits);
    }

    private function annotation(\DOMElement $node): string
    {
        $documentation = null;

        $nodeName = $this->prepareXmlName($node);
        /** @var \DOMElement $child */
        foreach($node->childNodes as $child) {
            $childName = $this->prepareXmlName($child);
            switch($childName) {
                case '#text': { break; }
                case '{http://www.w3.org/2001/XMLSchema}documentation': { $documentation = trim($child->nodeValue); break; }
                default: { throw new \RuntimeException(sprintf('Unhandled %s node %s!', $nodeName, $childName)); }
            }
        }

        return $documentation;
    }

    private function simpleContent(string $namespaceUri, \DOMElement $node): SimpleContent
    {
        $extension = null;

        $nodeName = $this->prepareXmlName($node);
        /** @var \DOMElement $child */
        foreach($node->childNodes as $child) {
            $childName = $this->prepareXmlName($child);
            switch($childName) {
                case '#text': { break; }
                // FIXME: it may have optional Annotation
                case '{http://www.w3.org/2001/XMLSchema}extension': { $extension = $this->extension($namespaceUri, $child); break; }
                // FIXME: it must have either Extension or Restrictions type
                default: { throw new \RuntimeException(sprintf('Unhandled %s node %s!', $nodeName, $childName)); }
            }
        }

        return new SimpleContent($extension);
    }

    private function complexContent(string $namespaceUri, \DOMElement $node): ComplexContent
    {
        $extension = null;
        $restrictions = null;

        $nodeName = $this->prepareXmlName($node);
        /** @var \DOMElement $child */
        foreach($node->childNodes as $child) {
            $childName = $this->prepareXmlName($child);
            switch($childName) {
                case '#text': { break; }
                // FIXME: it may have optional Annotation
                case '{http://www.w3.org/2001/XMLSchema}extension': { $extension = $this->extension($namespaceUri, $child); break; }
                case '{http://www.w3.org/2001/XMLSchema}restriction': { break; } // FIXME: add support for ComplexContent Restrictions
                default: { throw new \RuntimeException(sprintf('Unhandled %s node %s!', $nodeName, $childName)); }
            }
        }

        return new ComplexContent($namespaceUri, $node->getAttribute('base'), $extension ?: $restrictions);
    }

    private function extension(string $namespaceUri, \DOMElement $node): Extension
    {
        $elements = [];
        $attributes = [];

        $nodeName = $this->prepareXmlName($node);
        /** @var \DOMElement $child */
        foreach($node->childNodes as $child) {
            $childName = $this->prepareXmlName($child);
            switch($childName) {
                case '#text': { break; }
                case '{http://www.w3.org/2001/XMLSchema}sequence': { $elements[] = $this->sequence($namespaceUri, $child); break; }
                case '{http://www.w3.org/2001/XMLSchema}choice': { $choice = $this->choice($namespaceUri, $child); break; }
                case '{http://www.w3.org/2001/XMLSchema}attribute': { $attributes[] = $this->attribute($namespaceUri, $child); break; }
                case '{http://www.w3.org/2001/XMLSchema}attributeGroup': { break; } // FIXME: add support
                case '{http://www.w3.org/2001/XMLSchema}anyAttribute': { break; } // FIXME: add support
                default: { throw new \RuntimeException(sprintf('Unhandled %s node %s!', $nodeName, $childName)); }
            }
        }

        return new Extension($namespaceUri, $node->getAttribute('base'), $elements, $attributes);
    }

    /* --- UTILITIES -------------------------------------------------------- */

    private function extractOccursAttributeValue(\DOMElement $node, $name)
    {
        if(false === $node->hasAttribute($name)) {
            return null;
        }

        $value = $node->getAttribute($name);

        return is_numeric($value) ? (int)$value : $value;
    }

    private function prepareXmlName(\DOMNode $node)
    {
        return $this->option($node, [
            \DOMDocument::class => function(\DOMDocument $node) { return $node->nodeName; },
            \DOMComment::class => function(\DOMComment $node) { return $node->nodeName; },
            \DOMText::class => function(\DOMText $node) { return $node->nodeName; },
            \DOMElement::class => function(\DOMElement $node) { return sprintf('{%s}%s', $node->namespaceURI, $node->localName); }
        ]);
    }

    private function option($value, array $handlers)
    {
        if(false === array_key_exists(get_class($value), $handlers)) {
            throw new \RuntimeException(sprintf('Invalid %s type, expected one of %s!', get_class($value), json_encode(array_keys($handlers))));
        }

        return $handlers[get_class($value)]($value);
    }

    private function log(int $level, string ...$message): void
    {
        XsdUtility::log($this->logger, $level, ...$message);
    }
}
