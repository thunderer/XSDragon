<?php
declare(strict_types=1);
namespace Thunder\Xsdragon\Generator;

use Thunder\Xsdragon\Logger\LoggerInterface;
use Thunder\Xsdragon\NamespaceResolver\NamespaceResolverInterface;
use Thunder\Xsdragon\Schema\All;
use Thunder\Xsdragon\Schema\Choice;
use Thunder\Xsdragon\Schema\ComplexContent;
use Thunder\Xsdragon\Schema\ComplexType;
use Thunder\Xsdragon\Schema\Element;
use Thunder\Xsdragon\Schema\Schema;
use Thunder\Xsdragon\Schema\SchemaContainer;
use Thunder\Xsdragon\Schema\Sequence;
use Thunder\Xsdragon\Schema\SimpleType;
use Thunder\Xsdragon\Filesystem\FilesystemInterface;

/**
 * @deprecated Class* classes (generator, parser, serializer) are outdated and not supported, need to be removed as dead code
 */
final class ClassPhpGenerator implements GeneratorInterface
{
    private $writer;
    private $namespaceResolver;
    private $logger;
    /** @var SchemaContainer */
    private $schemas;

    public function __construct(FilesystemInterface $writer, NamespaceResolverInterface $namespaceResolver, LoggerInterface $logger)
    {
        $this->writer = $writer;
        $this->namespaceResolver = $namespaceResolver;
        $this->logger = $logger;
    }

    public function generate(SchemaContainer $schemas)
    {
        $this->schemas = $schemas;

        foreach($schemas->getSchemas() as $schema) {
            $this->logType("\n".'Schema', $schema->getNamespace(), $this->convertUriToPhpNamespace($schema->getNamespace()), '', 0);
            foreach($schema->getElements() as $element) {
                $this->processElement($element, $schema, 1);
            }
            foreach($schema->getSimpleTypes() as $simpleType) {
                $this->processSimpleType($simpleType, $schema, 1);
            }
            foreach($schema->getComplexTypes() as $complexType) {
                $this->processComplexType($complexType, $schema, 1);
            }
        }

        $this->writeClassFile('schema', serialize($schemas));
    }

    private function processElement(Element $element, Schema $schema, int $level)
    {
        $ns = $this->convertUriToPhpNamespace($schema->getNamespace());
        $this->logType('Element', $element->getName(), $element->getType(), '', $level);
        $name = $this->prepareName($element->getName());
        $namespaces = [$schema->getNamespace() => ''];
        $xmlns = [];
        foreach($this->schemas->findUrisFor($schema) as $prefix => $uri) {
            if(in_array($uri, ['http://www.w3.org/2001/XMLSchema'], true)) {
                continue;
            }
            $namespaces[$uri] = $prefix.':';
            $xmlns[] = 'xmlns:'.$prefix.'="'.$uri.'"';
        }
        $replaces = [
            '<NS>' => $ns,
            '<XMLNS>' => $schema->getNamespace(),
            '<CLASS_NAME>' => $name,
            '<TYPE>' => $this->convertXsdTypeToName($element->getType()),
            '<XML>' => '<'.$element->getName().' xmlns="'.$schema->getNamespace().'" '.implode(' ', $xmlns).' \'.$this->type->toXmlAttributes().\'>\'.'
                .'$this->type->toXml(\''.$schema->getNamespace().'\', '.var_export($namespaces, true).')'
                .'.\'</'.$element->getName().'>',
            '<XML_NAME>' => $element->getName(),
        ];

        $this->writeClassFile($ns.'\\'.$name, str_replace(array_keys($replaces), array_values($replaces), static::ELEMENT_TEMPLATE));
    }

    private function scanKnownNs(Schema $schema, array &$namespaces, array &$xmlns)
    {
        foreach($schema->getNamespaces() as $prefix => $uri) {
            if(false === array_key_exists($uri, $namespaces)) {
                $namespaces[$uri] = $prefix.':';
                $xmlns[] = 'xmlns:'.$prefix.'="'.$uri.'"';
                $this->scanKnownNs($this->schemas->findSchemaByNs($uri), $namespaces, $xmlns);
            }
        }
    }

    private function processSequence(Sequence $type, ComplexType $complexType, Schema $schema, string $ns, int $level)
    {
        $this->logType('SEQUENCE', $complexType->getName(), $ns, '', $level);
        $context = new ClassContext();
        foreach($type->getElements() as $element) {
            switch(true) {
                case ($element instanceof Element): {
                    $codeType = $this->convertXsdTypeToName($element->getType());
                    $context->uses[] = $this->convertXsdTypeToFqcn($schema, $ns, $element->getType());
                    $context->properties[] = '    /** @var '.$codeType.' */'."\n".'    private $'.$element->getName().';';
                    $context->ctorArgs[] = '        '.$this->createParameter($element->getType(), $element->getName(), $element->getMinOccurs(), $element->getMaxOccurs());
                    $context->ctorAssigns[] = '        $this->'.$element->getName().' = $'.$element->getName().';';
                    $context->getters[] = '    public function get'.ucfirst($element->getName()).'(): '.$codeType.' { return $this->'.$element->getName().'; }';
                    $context->xmlGen[] = $this->generateXmlElement($element, $schema);
                    break;
                }
                case $element instanceof Choice: {
                    $this->processChoice($element, $complexType, $schema, $ns, $level + 1);
                    break;
                }
                case $element instanceof Sequence: {
                    // FIXME: this also needs to be a single element
                    $this->processSequence($element, $complexType, $schema, $ns, $level);
                    break;
                }
                default: {
                    throw new \RuntimeException(sprintf('Unhandled ComplexType/Sequence element %s!', get_class($element)));
                }
            }
        }

        $this->createComplexTypeClass($ns, ucfirst($complexType->getName()).'_Sequence', $context, 'sequence', $schema);
    }

    private function processChoice(Choice $type, ComplexType $complexType, Schema $schema, string $ns, int $level)
    {
        $this->logType('Choice', '', '', '', $level);
        $context = new ClassContext();
        $context->ctorVisibility = 'private';

        foreach($type->getElements() as $element) {
            if($element instanceof Element) {
                $this->logType('Element', $element->getName(), $element->getType(), '', $level + 1);
                $typeName = $this->convertXsdTypeToName($element->getType());
                $varName = $element->getName();
                $context->uses[] = $this->convertXsdTypeToFqcn($schema, $ns, $element->getType());
                $context->getters[] = '    public function get'.ucfirst($varName).'(): '.$typeName.' { return $this->'.$varName.'; }';
                $context->properties[] = '    /** @var '.$typeName.' */'."\n".'    private $'.$varName.';';
                $context->xmlGen[] = $this->generateXmlElement($element, $schema);
                $context->namedCtors[] = '    /** Choice/Element */
    public static function createFrom'.ucfirst($varName).'('.$typeName.' $'.$varName.'): self 
    {
        $self = new static();
        
        $self->'.$varName.' = $'.$varName.';
        
        return $self;
    }
';
            } elseif($element instanceof Sequence) {
                $this->logType('Sequence', '', '', '', $level + 1);
                $ctorParameters = [];
                $ctorAssigns = [];
                foreach($element->getElements() as $seqElement) {
                    if($seqElement instanceof Element) {
                        $typeName = $this->convertXsdTypeToName($seqElement->getType());
                        $varName = $seqElement->getName();
                        $ctorParameters[] = $typeName.' $'.$varName.' = null';
                        $ctorAssigns[] = '        $self->'.$varName.' = $'.$varName.';';
                        $context->getters[] = '    public function get'.ucfirst($varName).'(): '.$typeName.' { return $this->'.$varName.'; }';
                        $context->uses[] = $this->convertXsdTypeToFqcn($schema, $ns, $seqElement->getType());
                        $context->xmlGen[] = $this->generateXmlElement($seqElement, $schema);
                        $context->properties[] = '    /** @var '.$typeName.' */'."\n".'    private $'.$varName.';';
                    } else {
                        throw new \RuntimeException(sprintf('Invalid Choice/Sequence element %s!', get_class($element)));
                    }
                }

                $context->namedCtors[] = '    /** Choice/Sequence */
    public static function createFrom'.ucfirst($complexType->getName()).'('.implode(', ', $ctorParameters).'): self 
    {
        $self = new static();

'.implode("\n", $ctorAssigns).'
        
        return $self;
    }
';
            } else {
                throw new \RuntimeException(sprintf('Invalid Choice element %s!', get_class($element)));
            }
        }

        $this->createComplexTypeClass($ns, $this->convertXsdTypeToName($complexType->getName().'_Choice'), $context, 'CHOICE', $schema);
    }

    private function processComplexTypeAll(All $all, ClassContext $context, Schema $schema, string $ns, int $level)
    {
        $this->logType('ALL', '', $ns, '', $level);
        foreach($all->getElements() as $element) {
            if($element instanceof Element) {
                $codeType = $this->convertXsdTypeToName($element->getType());
                $context->uses[] = $this->convertXsdTypeToFqcn($schema, $ns, $element->getType());
                $context->properties[] = '    /** @var '.$codeType.' */'."\n".'    private $'.$element->getName().';';
                $context->ctorArgs[] = '        '.$this->createParameter($element->getType(), $element->getName(), $element->getMinOccurs(), $element->getMaxOccurs());
                $context->ctorAssigns[] = '        $this->'.$element->getName().' = $'.$element->getName().';';
                $context->getters[] = '    public function get'.ucfirst($element->getName()).'(): '.$codeType.' { return $this->'.$element->getName().'; }';
                $context->xmlGen[] = $this->generateXmlElement($element, $schema);
            } else {
                throw new \RuntimeException(sprintf('Unhandled ComplexType/All element %s!', get_class($element)));
            }
        }
    }

    private function processComplexType(ComplexType $complexType, Schema $schema, int $level)
    {
        $ns = $this->convertUriToPhpNamespace($schema->getNamespace());
        $this->logType('ComplexType', $complexType->getName(), '', '', $level);
        $name = $this->prepareName($complexType->getName());

        $context = new ClassContext();
        $context->xmlName = $complexType->getName();

        foreach($complexType->getAttributes() as $attribute) {
            $typeName = $this->convertXsdTypeToName($attribute->getType());
            $varName = $attribute->getName();
            $context->ctorVars[] = '$'.$varName;
            $context->ctorArgs[] = '        '.$this->createParameter($attribute->getType(), $varName, 1, 1);
            $context->ctorAssigns[] = '        $this->'.$varName.' = $'.$varName.';';
            $context->attributes[] = '    /** @var '.$typeName.' */'."\n".'    private $'.$varName.';';
            $context->getters[] = '    public function get'.ucfirst($varName).'(): '.$typeName.' { return $this->'.$varName.'; }';
            $context->xmlGenAttrs[] = '$this->attributeToXml(\''.$varName.'\', \''.$typeName.'\', $this->'.$varName.')';
        }
        $type = $complexType->getType();
        $comment = 'COMPLEX TYPE';
        if($type instanceof ComplexContent) {
            $comment .= ' / COMPLEX CONTENT';
            $this->logType('ComplexContent', '', '', '', $level + 1); // FIXME: Unused complex content
        } elseif($type instanceof Sequence) {
            $comment .= ' / SEQUENCE';
            $this->processComplexTypeSequence($type, $complexType, $context, $schema, $ns, $level);
        } elseif($type instanceof All) {
            $comment .= ' / ALL';
            $this->processComplexTypeAll($type, $context, $schema, $ns, $level + 1);
        } elseif($type instanceof Choice) {
            $comment .= ' / CHOICE';
            $this->processComplexTypeChoice($type, $complexType, $context, $schema, $ns, $level);
        } elseif(null === $type) {
            $comment .= ' / NULL';
            $this->logType('Null', '', '', '', $level + 1); // FIXME: unused
        } else {
            throw new \RuntimeException(sprintf('Invalid ComplexType element %s!', $type ? get_class($type) : 'null'));
        }

        $this->createComplexTypeClass($ns, $name, $context, $comment, $schema);
    }

    private function processComplexTypeSequence(Sequence $sequence, ComplexType $complexType, ClassContext $context, Schema $schema, string $ns, int $level)
    {
        $this->logType('Sequence', '', '', '', $level + 1);
        foreach($sequence->getElements() as $element) {
            if($element instanceof Element) {
                $this->logType('Element', $element->getName(), $element->getType(), '', $level + 2);
                $varName = $element->getName();
                $context->properties[] = $this->createProperty($element->getType(), $varName, $element->getMinOccurs(), $element->getMaxOccurs());
                $context->ctorArgs[] = '        '.$this->createParameter($element->getType(), $varName, $element->getMinOccurs(), $element->getMaxOccurs());
                $context->ctorAssigns[] = '        $this->'.$varName.' = $'.$varName.'; // Element';
                $context->getters[] = $this->createGetter($element->getType(), $varName, $element->getMinOccurs(), $element->getMaxOccurs());
                $context->uses[] = $this->convertXsdTypeToFqcn($schema, $ns, $element->getType());
                $context->xmlGen[] = $this->generateXmlElement($element, $schema).' /* Element */';
            } elseif($element instanceof Choice) {
                $className = $this->convertXsdTypeToName($complexType->getName()).'_Choice';
                $typeName = 'unbounded' === $element->getMaxOccurs() ? 'array' : $className;
                $varName = lcfirst($className);
                $context->properties[] = '    /** @var '.$typeName.' */'."\n".'    private $'.$varName.';';
                $context->ctorArgs[] = '        '.$this->createParameter($typeName, $varName, $element->getMinOccurs(), $element->getMaxOccurs());
                $context->ctorAssigns[] = '        $this->'.$varName.' = $'.$varName.'; // Choice';
                $context->getters[] = '    public function get'.ucfirst($varName).'(): '.$typeName.' { return $this->'.$varName.'; }';
                $context->uses[] = $this->convertXsdTypeToFqcn($schema, $ns, $complexType->getName()).'_Choice';
                $context->xmlGen[] = '            $this->choiceToXml($this->'.$varName.', $ns, $xmlns)';
                if('array' === $typeName) {
                    $context->ctorChecks[] = '        $this->checkCollection($'.$varName.', '.$className.'::class);';
                }
                $this->processChoice($element, $complexType, $schema, $ns, $level + 2);
            } elseif($element instanceof Sequence) {
                $this->logType('Sequence', '', '', '', $level + 2);
                $typeName = $this->convertXsdTypeToName($complexType->getName()).'_Sequence';
                $varName = lcfirst($typeName);
                $context->ctorArgs[] = '        '.$this->createParameter($typeName, $varName, 1, 1);
                $context->ctorAssigns[] = '        $this->'.$varName.' = $'.$varName.'; // Sequence';
                $context->uses[] = $this->convertXsdTypeToFqcn($schema, $ns, $complexType->getName()).'_Sequence';
                $context->getters[] = '    public function get'.ucfirst($varName).'(): '.$typeName.' { return $this->'.$varName.'; }';
                $this->processSequence($element, $complexType, $schema, $ns, $level + 2);
            } else {
                throw new \RuntimeException(sprintf('Invalid ComplexType/Sequence element %s!', get_class($element)));
            }
        }
    }

    private function processComplexTypeChoice(Choice $type, ComplexType $complexType, ClassContext $context, Schema $schema, string $ns, int $level)
    {
        $context->ctorVisibility = 'private';
        foreach($type->getElements() as $element) {
            if($element instanceof Element) {
                $this->logType('Element', $element->getName(), $element->getType(), '', $level + 1);
                $typeName = $this->convertXsdTypeToName($element->getType());
                $varName = $element->getName();
                $context->uses[] = $this->convertXsdTypeToFqcn($schema, $ns, $element->getType());
                $context->getters[] = '    public function get'.ucfirst($varName).'() { return $this->'.$varName.'; }';
                $context->properties[] = '    /** @var ?'.$typeName.' */'."\n".'    private $'.$varName.';';
                $context->xmlGen[] = $this->generateXmlElement($element, $schema);
                $context->namedCtors[] = '    /** ComplexType/Choice/Element */
    public static function createFrom'.ucfirst($varName).'('.($context->ctorArgs ? implode(', ', array_map('trim', $context->ctorArgs)).', ' : '').$typeName.' $'.$varName.'): self 
    {
        $self = new static('.implode(', ', $context->ctorVars).');

        $self->'.$varName.' = $'.$varName.';

        return $self;
    }
';
            } elseif($element instanceof Sequence) {
                $this->logType('Sequence', '', '', '', $level + 1);
                $ctorParameters = [];
                $ctorAssigns = [];
                foreach($element->getElements() as $seqElement) {
                    if($seqElement instanceof Element) {
                        $typeName = $this->convertXsdTypeToName($seqElement->getName());
                        $varName = $seqElement->getName();
                        $ctorParameters[] = $typeName.' $'.$varName.' = null';
                        $ctorAssigns[] = '$this->'.$varName.' = $'.$varName.';';
                        $context->uses[] = $this->convertXsdTypeToFqcn($schema, $ns, $seqElement->getType());
                        $context->getters[] = '    public function get'.ucfirst($varName).'(): '.ucfirst($typeName).' { return $this->'.$varName.'; }';
                    } else {
                        throw new \RuntimeException(sprintf('Invalid Choice/Sequence element %s!', get_class($element)));
                    }
                }

                $context->namedCtors[] = '    /** ComplexType/Choice/Sequence */
    public static function createFrom'.ucfirst($complexType->getName()).'('.implode(",\n        ", $ctorParameters).'): self 
    {
        $self = new static();

        '.implode("\n", $ctorAssigns).'

        return $self;
    }
';
            } else {
                throw new \RuntimeException(sprintf('Invalid Choice element %s!', get_class($element)));
            }
        }
    }

    private function processSimpleType(SimpleType $simpleType, Schema $schema, int $level)
    {
        $ns = $this->convertUriToPhpNamespace($schema->getNamespace());
        $this->logType('SimpleType', $simpleType->getName(), $simpleType->getType()->getBase(), '', $level);
        $name = $this->prepareName($simpleType->getName());
        $res = $simpleType->getType();
        $replaces = [
            '<NS>' => $ns,
            '<CLASS_NAME>' => $name,
            '<XMLNS>' => $schema->getNamespace(),
            '<VALUE_TYPE>' => $this->convertXsdTypeToName($simpleType->getType()->getBase()),
            '<XML_NAME>' => $simpleType->getName(),
            // FIXME: abstract generation of restriction checks from PrimitivePhpGenerator and replace this with a single call
            '<RESTRICTIONS>' => implode("\n", array_map(function(string $item) {
                    return '        '.$item;
                }, array_filter([
                    'xsd:date' === $res->getBase() ? 'if(!preg_match(\'~^[0-9]{4}-[0-9]{2}-[0-9]{2}$~\', $value)) {
            throw new \InvalidArgumentException(sprintf(\'%s value `%s` invalid date, expected Y-m-d!\', __CLASS__, $value));
        }' : '',
                    'xsd:dateTime' === $res->getBase() ? 'if(!preg_match(\'~^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}$~\', $value)) {
            throw new \InvalidArgumentException(sprintf(\'%s value `%s` invalid date and time, expected Y-m-dTH:i:s!\', __CLASS__, $value));
        }' : '',
                    null === $res->getMinLength() ? '' : 'if(mb_strlen($value) < '.$res->getMinLength().') {
            throw new \InvalidArgumentException(sprintf(\'%s value `%s` invalid length %s, expected >= %s!\', __CLASS__, $value, mb_strlen($value), '.number_format($res->getMinLength(), 0, '', '').'));
        }',
                    null === $res->getMaxLength() ? '' : 'if(mb_strlen($value) > '.$res->getMaxLength().') {
            throw new \InvalidArgumentException(sprintf(\'%s value `%s` invalid length %s, expected <= %s!\', __CLASS__, $value, mb_strlen($value), '.number_format($res->getMaxLength(), 0, '', '').'));
        }',
                    null === $res->getMinInclusive() ? '' : 'if($value < '.$res->getMinInclusive().') {
            throw new \InvalidArgumentException(sprintf(\'%s value `%s` too low, expected >= %s!\', __CLASS__, $value, '.number_format($res->getMinInclusive(), 0, '', '').'));
        }',
                    null === $res->getMaxInclusive() ? '' : 'if($value > '.$res->getMaxInclusive().') {
            throw new \InvalidArgumentException(sprintf(\'%s value `%s` too high, expected <= %s!\', __CLASS__, $value, '.number_format($res->getMaxInclusive(), 0, '', '').'));
        }',
                    null === $res->getFractionDigits() ? '' : 'if(mb_strlen(substr((string)$value, strpos((string)$value, \'.\'))) !== '.$res->getFractionDigits().') {
            throw new \InvalidArgumentException(\'Invalid fraction digits!\');
        }',
                    null === $res->getLength() ? '' : 'if(mb_strlen($value) !== '.$res->getLength().') {
            throw new \InvalidArgumentException(sprintf(\'%s value `%s` invalid length %s, expected %s!\', __CLASS__, $value, mb_strlen($value), '.$res->getLength().'));
        }',
                    empty($res->getEnumerations()) ? '' : 'if(false === in_array($value, [\''.implode('\', \'', $res->getEnumerations()).'\'], true)) {
            throw new \InvalidArgumentException(sprintf(\'%s value `%s` not in enumeration %s!\', __CLASS__, $value, \'['.implode(', ', $res->getEnumerations()).']\'));
        }',
                    empty($res->getPatterns()) ? '' : 'if(!preg_match(\'~'.implode('|', $res->getPatterns()).'~\', $value)) {
            throw new \InvalidArgumentException(sprintf(\'%s value `%s` not in enumeration %s!\', __CLASS__, $value, \'['.implode(', ', $res->getPatterns()).']\'));
        }',
                ])))
        ];

        $this->writeClassFile($ns.'\\'.$name, str_replace(array_keys($replaces), array_values($replaces), static::SIMPLE_TYPE_TEMPLATE));
    }

    /* --- UTILITIES -------------------------------------------------------- */

    private function createComplexTypeClass(string $ns, string $name, ClassContext $context, string $comment, Schema $schema)
    {
        $replaces = [
            '<COMMENT>' => $comment,
            '<NS>' => $ns,
            '<CLASS_NAME>' => ucfirst($name),
            '<USES>' => $this->createUses($context->uses, $ns),
            '<ATTRIBUTES>' => implode("\n", $context->attributes),
            '<PROPERTIES>' => implode("\n", $context->properties),
            '<CONSTRUCTOR_VISIBILITY>' => $context->ctorVisibility,
            '<CONSTRUCTOR_CHECKS>' => "\n".implode("\n", $context->ctorChecks),
            '<CONSTRUCTOR_ARGUMENTS>' => "\n".implode(",\n", $context->ctorArgs),
            '<CONSTRUCTOR_ASSIGNMENTS>' => implode("\n", $context->ctorAssigns),
            '<NAMED_CONSTRUCTORS>' => implode("\n", $context->namedCtors),
            '<GETTERS>' => implode("\n", $context->getters),
            '<XML_GENERATION>' => $context->xmlGen ? "\n".implode(".\n", $context->xmlGen) : '\'\'',
            '<XML_ATTRIBUTES>' => implode("\n            .", $context->xmlGenAttrs) ?: '\'\'',
            '<XMLNS>' => $schema->getNamespace(),
            '<XML_NAME>' => $context->xmlName,
        ];

        $this->writeClassFile($ns.'\\'.$name, str_replace(array_keys($replaces), array_values($replaces), static::COMPLEX_TYPE_TEMPLATE));
    }

    private function generateXmlElement(Element $element, Schema $schema): string
    {
        $name = $element->getName();
        $prefix = $this->convertXsdTypeToPrefix($element->getType());

        return '            $this->propertyToXml(\''.$name.'\', \''.$prefix.'\', $this->'.$name.', $ns, $xmlns)';
    }

    private function convertXsdTypeToPrefix(string $type): string
    {
        if(strpos($type, ':')) {
            $prefix = explode(':', $type, 2)[0];

            return 'xsd' === $prefix ? '' : $prefix.':';
        }

        return '';
    }

    private function createUses(array $uses, string $ns): string
    {
        if(empty($uses)) {
            return '';
        }

        $classToUse = function(string $class) { return 'use '.$class.';'; };
        $isOutsideOwnNamespace = function(string $class) use($ns) { return $class && 0 !== strpos($class, $ns); };
        $isBuiltInType = function(string $class) use($ns) { return false === in_array($class, ['string', 'int'], true); };

        return implode("\n", array_map($classToUse, array_unique(array_filter(array_filter($uses, $isOutsideOwnNamespace), $isBuiltInType))))."\n";
    }

    private function convertXsdTypeToFqcn(Schema $schema, string $ns, string $type): string
    {
        switch($type) {
            case 'xsd:date': { return \DateTimeImmutable::class; }
            case 'xsd:dateTime': { return \DateTimeImmutable::class; }
            case 'xsd:string': { return 'string'; }
            case 'xsd:long': { return 'int'; }
            case 'xsd:boolean': { return 'bool'; }
            case 'xsd:int': { return 'int'; }
            case 'xsd:decimal': { return 'string'; }
            case 'xsd:nonNegativeInteger': { return ''; }
        }

        if($position = strpos($type, ':')) {
            list($prefix, $type) = explode(':', $type, 2);

            $namespaces = $schema->getNamespaces();
            if(false === array_key_exists($prefix, $namespaces)) {
                throw new \RuntimeException(sprintf('XSD element %s namespace prefix %s not found among %s!', $type, $prefix, implode(', ', array_keys($namespaces))));
            }

            return $this->convertUriToPhpNamespace($namespaces[$prefix]).'\\'.ucfirst($type);
        }

        return $ns.'\\'.ucfirst($type);
    }

    private function convertUriToPhpNamespace(string $uri): string
    {
        return $this->namespaceResolver->convertUriToNs($uri);
    }

    private function prepareName(string $name): string
    {
        if('echo' === $name) {
            return 'Echo_';
        }

        return ucfirst($name);
    }

    private function createParameter(string $type, string $name, $minOccurs, $maxOccurs): string
    {
        switch(true) {
            case null === $minOccurs && null === $maxOccurs: { return $this->convertXsdTypeToName($type).' $'.$name; }
            case 1 === $minOccurs && 1 === $maxOccurs: { return $this->convertXsdTypeToName($type).' $'.$name; }
            case 0 === $minOccurs && 1 === $maxOccurs: { return $this->convertXsdTypeToName($type).' $'.$name.' = null'; }
            case 1 === $minOccurs && 'unbounded' === $maxOccurs: { return 'array $'.$name; }
            case 0 === $minOccurs && 'unbounded' === $maxOccurs: { return 'array $'.$name; }
            default: { throw new \RuntimeException(sprintf('Invalid arg type combination: %s!', json_encode(func_get_args()))); }
        }
    }

    private function createGetter(string $type, string $name, $minOccurs, $maxOccurs): string
    {
        switch(true) {
            case null === $minOccurs && null === $maxOccurs: { $type = $this->convertXsdTypeToName($type); break; }
            case 1 === $minOccurs && 1 === $maxOccurs: { $type = $this->convertXsdTypeToName($type); break; }
            case 0 === $minOccurs && 1 === $maxOccurs: { $type = null; break; }
            case 1 === $minOccurs && 'unbounded' === $maxOccurs: { $type = 'array'; break; }
            case 0 === $minOccurs && 'unbounded' === $maxOccurs: { $type = 'array'; break; }
            default: { throw new \RuntimeException(sprintf('Invalid arg type combination: %s!', json_encode(func_get_args()))); }
        }

        return '    public function get'.ucfirst($name).'()'.($type ? ': '.$type : '').' { return $this->'.$name.'; }';
    }

    private function createProperty(string $type, string $name, $minOccurs, $maxOccurs): string
    {
        switch(true) {
            case null === $minOccurs && null === $maxOccurs: { $type = $this->convertXsdTypeToName($type); break; }
            case 1 === $minOccurs && 1 === $maxOccurs: { $type = $this->convertXsdTypeToName($type); break; }
            case 0 === $minOccurs && 1 === $maxOccurs: { $type = '?'.$this->convertXsdTypeToName($type); break; }
            case 1 === $minOccurs && 'unbounded' === $maxOccurs: { $type = $this->convertXsdTypeToName($type).'[]'; break; }
            case 0 === $minOccurs && 'unbounded' === $maxOccurs: { $type = $this->convertXsdTypeToName($type).'[]'; break; }
            default: { throw new \RuntimeException(sprintf('Invalid arg type combination: %s!', json_encode(func_get_args()))); }
        }

        return '    /** @var '.$type.' */'."\n".'    private $'.$name.';';
    }

    private function convertXsdTypeToName(string $type): string
    {
        switch($type) {
            case 'xsd:date': { return 'string'; }
            case 'xsd:dateTime': { return 'string'; }
            case 'xsd:string': { return 'string'; }
            case 'xsd:int': { return 'int'; }
            case 'xsd:decimal': { return 'string'; }
            case 'xsd:long': { return 'int'; }
            case 'xsd:boolean': { return 'bool'; }
            case 'xsd:nonNegativeInteger': { return 'int'; }
        }

        return strpos($type, ':') ? ucfirst(explode(':', $type, 2)[1]) : ucfirst($type);
    }

    private function writeClassFile($fqcn, $code)
    {
        $this->writer->write(str_replace('\\', '/', $this->namespaceResolver->convertFqcnToPath($fqcn)).'.php', $code);
    }

    private function logType($logType, $type, $elementName, $xmlName, int $level)
    {
        $this->log(sprintf("\e[36m%s\e[0m \e[38m%s\e[0m \e[32m%s\e[0m \e[31m%s\e[0m", $logType, $type, $elementName, $xmlName), $level);
    }

    private function log(string $message, int $level)
    {
        $this->logger->log(str_pad('', $level * 2, ' ').$message);
    }

    // FIXME: remove these templates and reuse generic class template (and approach) from PrimitivePhpGenerator
    const ELEMENT_TEMPLATE = <<<'EOF'
<?php
/** ELEMENT TYPE */
declare(strict_types=1);
namespace <NS>;

use Thunder\Xsdragon\Xml\XmlObjectInterface;

final class <CLASS_NAME> implements XmlObjectInterface
{
    /** @var <TYPE> */
    private $type;

    public function __construct(<TYPE> $type)
    {
        $this->type = $type;
    }

    public function getType(): <TYPE>
    {
        return $this->type;
    }

    public function toXml(): string
    {
        return '<XML>';
    }

    public function toXmlAttributes(): string
    {
        return '';
    }

    public function getXmlNamespace(): string
    {
        return '<XMLNS>';
    }

    public function getXmlName(): string
    {
        return '<XML_NAME>';
    }
}

EOF;

    const COMPLEX_TYPE_TEMPLATE = <<<'EOF'
<?php
/** <COMMENT> */
declare(strict_types=1);
namespace <NS>;

use Thunder\Xsdragon\Traits\ComplexTypeTrait;
use Thunder\Xsdragon\Xml\XmlObjectInterface;
<USES>
final class <CLASS_NAME> implements XmlObjectInterface
{
    use ComplexTypeTrait;

<ATTRIBUTES>

<PROPERTIES>

    <CONSTRUCTOR_VISIBILITY> function __construct(<CONSTRUCTOR_ARGUMENTS>
    ) {
<CONSTRUCTOR_CHECKS>
<CONSTRUCTOR_ASSIGNMENTS>
    }

<NAMED_CONSTRUCTORS>
<GETTERS>

    public function toXml(string $ns, array $xmlns): string
    {
        return <XML_GENERATION>;
    }

    public function toXmlAttributes(): string
    {
        return <XML_ATTRIBUTES>;
    }

    public function getXmlNamespace(): string
    {
        return '<XMLNS>';
    }

    public function getXmlName(): string
    {
        return '<XML_NAME>';
    }
}

EOF;

    const SIMPLE_TYPE_TEMPLATE = <<<'EOF'
<?php
/** SIMPLE TYPE */
declare(strict_types=1);
namespace <NS>;

use Thunder\Xsdragon\Xml\XmlObjectInterface;

final class <CLASS_NAME> implements XmlObjectInterface
{
    /** @var <VALUE_TYPE> */
    private $value;

    public function __construct(<VALUE_TYPE> $value)
    {
<RESTRICTIONS>
        $this->value = $value;
    }

    public function getValue(): <VALUE_TYPE>
    {
        return $this->value;
    }

    public function toXml(): string
    {
        return $this->value instanceof \DateTimeImmutable ? $this->value : (string)$this->value;
    }

    public function toXmlAttributes(): string
    {
        return '';
    }

    public function getXmlNamespace(): string
    {
        return '<XMLNS>';
    }
    
    public function getXmlName(): string
    {
        return '<XML_NAME>';
    }
}

EOF;
}
