<?php
declare(strict_types=1);
namespace Thunder\Xsdragon\Generator;

use Thunder\Xsdragon\Counter\Counter;
use Thunder\Xsdragon\Filesystem\FilesystemInterface;
use Thunder\Xsdragon\Logger\LoggerInterface;
use Thunder\Xsdragon\NamespaceResolver\NamespaceResolverInterface;
use Thunder\Xsdragon\Schema\All;
use Thunder\Xsdragon\Schema\Attribute;
use Thunder\Xsdragon\Schema\Choice;
use Thunder\Xsdragon\Schema\ComplexContent;
use Thunder\Xsdragon\Schema\ComplexType;
use Thunder\Xsdragon\Schema\Element;
use Thunder\Xsdragon\Schema\Extension;
use Thunder\Xsdragon\Schema\ListNode;
use Thunder\Xsdragon\Schema\Restrictions;
use Thunder\Xsdragon\Schema\Schema;
use Thunder\Xsdragon\Schema\SchemaContainer;
use Thunder\Xsdragon\Schema\Sequence;
use Thunder\Xsdragon\Schema\SimpleContent;
use Thunder\Xsdragon\Schema\SimpleType;
use Thunder\Xsdragon\Schema\Union;
use Thunder\Xsdragon\Utility\XsdUtility;
use Thunder\Xsdragon\Xml\XmlObjectInterface;

final class PrimitivePhpGenerator implements GeneratorInterface
{
    private $filesystem;
    private $namespaceResolver;
    private $logger;
    /** @var SchemaContainer */
    private $schemas;
    private $counter;

    public function __construct(FilesystemInterface $writer, NamespaceResolverInterface $namespaceResolver, LoggerInterface $logger, Counter $counter)
    {
        $this->filesystem = $writer;
        $this->namespaceResolver = $namespaceResolver;
        $this->logger = $logger;
        $this->counter = $counter;
    }

    public function generate(SchemaContainer $schemas)
    {
        $this->counter->start();
        $this->schemas = $schemas;

        foreach($schemas->getSchemas() as $schema) {
            $this->counter->tickSchema();
            $this->log(0, 'Schema', '//////////', $schema->getNamespace(), (string)$schema->getLocation(), str_pad('', 5, '\\').' '.$this->namespaceResolver->convertUriToNs($schema->getNamespace()));
            $this->log(0, '');
            foreach($schema->getElements() as $element) {
                $this->log(1, 'RElement', $element->getName());
                $this->writeClass($this->element($element, $schema, new ClassContext(), 1));
                $this->log(0, '');
            }
            foreach($schema->getComplexTypes() as $complexType) {
                $this->log(1, 'RComplexType', $complexType->getName());
                $this->writeClass($this->complexType($complexType, $schema, new ClassContext(), 1));
                $this->log(0, '');
            }
            foreach($schema->getSimpleTypes() as $simpleType) {
                $this->writeClass($this->simpleType($simpleType, $schema, new ClassContext(), 1));
            }
            $schema->getSimpleTypes() && $this->log(0, '');
        }

        $this->filesystem->write('schema.php', serialize($schemas));
        $this->counter->stop();
    }

    /* --- RULES ------------------------------------------------------------ */

    private function complexType(ComplexType $complexType, Schema $schema, ClassContext $context, int $level): ClassContext
    {
        $this->counter->tickComplexType();
        $log = function(string $header) use($level, $complexType) {
            $this->log($level, $header, $complexType->getName(), XsdUtility::describe($complexType->getType()));
        };

        $this->complexTypeBody($complexType, $schema, $context, $level, $log);

        $context->comment = '/* COMPLEX TYPE */';
        $context->name = $this->namespaceResolver->convertNameToClassName($complexType->getName());
        $context->namespace = $this->namespaceResolver->convertUriToNs($schema->getNamespace());
        $context->xmlNamespace = $schema->getNamespace();
        $context->getters[] = '    public function getXmlName(): string { return \''.$complexType->getName().'\'; }';

        return $context;
    }

    private function complexTypeBody(ComplexType $complexType, Schema $schema, ClassContext $context, int $level, callable $log)
    {
        $this->dispatch($complexType, $complexType->getAttributes(), $schema, $context, $level);
        $innerType = $complexType->getType();
        if($innerType instanceof Sequence) {
            $this->counter->tickSequence();
            $log('Sequence');
            $this->dispatch($complexType, $innerType->getElements(), $schema, $context, $level + 1);
        } elseif($innerType instanceof All) {
            $this->counter->tickAll();
            $log('All');
            $this->dispatch($complexType, $innerType->getElements(), $schema, $context, $level + 1);
        } elseif($innerType instanceof Choice) {
            $this->counter->tickChoice();
            $log('Choice');
            $this->choiceWithNamedCtors($complexType, $complexType->getAttributes(), $innerType, $schema, $context, $level + 1);
        } elseif($innerType instanceof SimpleContent) {
            // FIXME: add support
        } elseif($innerType instanceof ComplexContent) {
            $log('ComplexContent');
            if(null === $innerType->getType()) {
                return;
            }
            if($innerType->getType() instanceof Extension && $innerType->getType()->getBase() === 'xs:anyType') {
                return;
            }
            if('xsd:anyType' === $innerType->getType()->getBase()) {
                return; // FIXME: add support!
            }
            list($xschema, $xtype) = $this->findTypeEverywhere($this->schemas, $schema, $innerType->getType()->getBase());
            $this->complexTypeBody($xtype, $xschema, $context, $level, $log);
            // FIXME: this is hardcoded for ComplexContent with Sequence
            if($innerType->getType()->getElements()) {
                $this->dispatch($complexType, $innerType->getType()->getElements()[0]->getElements(), $schema, $context, $level + 1);
            }
            $context->ctorChecks[] = '        // FIXME: ComplexContent is not supported! Merge base ComplexType with Extension content.';
        } elseif(null === $innerType) {
            $log('NULL');
            $context->ctorChecks[] = '        // FIXME: Null content is not supported! Element has only Attributes.';
        } else {
            throw new \RuntimeException(sprintf('Invalid ComplexType type %s!', get_class($innerType)));
        }
    }

    private function element(Element $element, Schema $schema, ClassContext $context, int $level): ClassContext
    {
        $this->counter->tickElement();
        $type = $element->getType();
        if(is_string($type)) {
            if(XsdUtility::isPrimitiveType($type)) {
                $this->log($level, 'Primitive', $element->getName(), $type, XsdUtility::occurs($element));
                $this->handleParameter($context, $schema, $element->getName(), $type, $element->getMinOccurs(), $element->getMaxOccurs(), 'Primitive');
                return $context;
            }

            $prefix = null;
            if(strpos($element->getType(), ':')) {
                $schemaUris = $this->schemas->findUrisFor($schema);
                list($prefix, $type) = explode(':', $element->getType(), 2);
                if(XsdUtility::isPrimitiveType('{'.$schemaUris[$prefix].'}'.$type)) {
                    $this->log($level, 'Primitive', $element->getName(), $type, XsdUtility::occurs($element));
                    $this->handleParameter($context, $schema, $element->getName(), $type, $element->getMinOccurs(), $element->getMaxOccurs(), 'Primitive');
                    return $context;
                }
                $schema = $this->schemas->findSchemaByNs($schemaUris[$prefix]);
            }

            try {
                $realType = $schema->findElementTypeByName($type);
            } catch(\RuntimeException $e) {
                $realType = $this->findTypeEverywhere($this->schemas, $schema, $type)[1];
            }
            if($realType instanceof ComplexType) {
                $log = function(string $header) use($level, $element, $realType) {
                    $this->log($level, $header, $element->getName(), $realType->getName(), XsdUtility::occurs($element));
                };
                $this->complexTypeBody($realType, $schema, $context, $level, $log);
            } elseif($realType instanceof SimpleType) {
                $this->log($level, 'SimpleType', $element->getName(), $realType->getName(), XsdUtility::occurs($element), XsdUtility::describe($realType->getType()));
                $this->handleParameter($context, $schema, $element->getName(), $realType, $element->getMinOccurs(), $element->getMaxOccurs(), 'SimpleType');
            } else {
                throw new \RuntimeException(sprintf('Invalid Element %s type %s - %s!', $element->getName(), XsdUtility::describe($type), XsdUtility::describe($realType)));
            }
        } elseif($type instanceof SimpleType) {
            $this->log($level, 'Inline/SimpleType', $element->getName(), '*INLINE*', XsdUtility::occurs($element), XsdUtility::describe($type->getType()));
            $this->handleParameter($context, $schema, $element->getName(), $type, $element->getMinOccurs(), $element->getMaxOccurs(), 'Inline/SimpleType');
        } elseif($type instanceof ComplexType) {
            $this->log($level, 'Inline/ComplexType', $element->getName(), '*INLINE*', XsdUtility::occurs($element), XsdUtility::describe($type->getType()));
            $this->dispatch($element, $type->getAttributes(), $schema, $context, $level);
        } else {
            throw new \RuntimeException(sprintf('Invalid Element type type!'));
        }

        $context->comment = '/* ELEMENT */';
        $context->name = $this->namespaceResolver->convertNameToClassName($element->getName());
        $context->namespace = $this->namespaceResolver->convertUriToNs($schema->getNamespace());
        $context->xmlNamespace = $schema->getNamespace();
        $context->getters[] = '    public function getXmlName(): string { return \''.$element->getName().'\'; }';

        return $context;
    }

    private function choiceWithNamedCtors(ComplexType $parent, array $attributes, Choice $choice, Schema $schema, ClassContext $context, int $level)
    {
        $context->ctorVisibility = 'private';
        $this->dispatchChoice($parent, $attributes, $choice->getElements(), $schema, $context, $level + 1);
    }

    private function dispatchChoice(ComplexType $parent, array $attributes, array $elements, Schema $schema, ClassContext $context, int $level)
    {
        $attributesArgs = [];
        $attributesCtor = [];
        /** @var Attribute $attribute */
        foreach($attributes as $attribute) {
            $attributesArgs[] = $this->convertXsdTypeToName($schema, $attribute->getType()).' $'.$attribute->getName();
            $attributesCtor[] = '$'.$attribute->getName();
        }
        $attributesArgs = $attributes ? implode(', ', $attributesArgs).', ' : '';
        $attributesCtor = $attributes ? implode(', ', $attributesCtor) : '';

        foreach($elements as $element) {
            if($element instanceof Element) {
                $this->counter->tickElement();
                $this->log($level, 'Choice/Element', $element->getName(), XsdUtility::describe($element->getType()), XsdUtility::occurs($element));

                // FIXME: handle `ref` attribute!
                $type = $this->resolveRealTypeName($schema, $element->getType());
                if(XsdUtility::isPrimitiveType($type)) {
                    $type = XsdUtility::getPrimitivePhpType($type);
                }
                if(strpos($type, ':')) {
                    $context->uses[] = $this->convertXsdTypeToFqcn($schema, $type);
                    $type = $this->convertXsdTypeToName($schema, $type);
                }

                $context->namedCtors[] = '    public static function createFrom'.ucfirst($element->getName()).'('.$attributesArgs.$type.' $'.$element->getName().'): self 
    { 
        $self = new static('.$attributesCtor.');

        $self->'.$element->getName().' = $'.$element->getName().';

        return $self;
    }';
                $context->properties[] = '    /** @var '.$type.' */'."\n".'    private $'.$element->getName().';';
                $context->getters[] = '    public function has'.ucfirst($element->getName()).'(): bool { return null !== $this->'.$element->getName().'; }';
                $context->getters[] = '    public function get'.ucfirst($element->getName()).'()'.' { return $this->'.$element->getName().'; }';
            } elseif($element instanceof Sequence) {
                $this->counter->tickSequence();
                $this->log($level, 'Choice/Sequence', $parent->getName());

                $constructorArgs = [];
                $constructorAssigns = [];
                foreach($element->getElements() as $seqElement) {
                    if($seqElement instanceof Element) {
                        $type = $this->resolveRealTypeName($schema, $seqElement->getType());
                        if(XsdUtility::isPrimitiveType($type)) {
                            $type = XsdUtility::getPrimitivePhpType($type);
                        }
                        if(strpos($type, ':')) {
                            $context->uses[] = $this->convertXsdTypeToFqcn($schema, $type);
                            $type = $this->convertXsdTypeToName($schema, $type);
                        }

                        $constructorArgs[] = $type.' $'.$seqElement->getName();
                        $constructorAssigns[] = '        $self->'.$seqElement->getName().' = $'.$seqElement->getName().';';

                        $context->properties[] = '    /** @var '.$type.' */'."\n".'    private $'.$seqElement->getName().';';
                        $context->getters[] = '    public function has'.ucfirst($seqElement->getName()).'(): bool { return null !== $this->'.$seqElement->getName().'; }';
                        $context->getters[] = '    public function get'.ucfirst($seqElement->getName()).'()'.' { return $this->'.$seqElement->getName().'; }';
                    } else {
                        throw new \RuntimeException(sprintf('Invalid Choice Sequence element %s!', XsdUtility::describe($seqElement)));
                    }
                }

                $context->namedCtors[] = '    public static function createFrom'.ucfirst($parent->getName()).'_Sequence('.$attributesArgs.implode(', ', $constructorArgs).'): self 
    { 
        $self = new static('.$attributesCtor.');

'.implode("\n", $constructorAssigns).'

        return $self;
    }';
            } else {
                throw new \RuntimeException(sprintf('Invalid Choice element %s!', XsdUtility::describe($element)));
            }
        }
    }

    private function pseudoType(string $name, $type, Schema $schema, int $level, string $comment, string $suffix)
    {
        $this->counter->tickPseudoTypes();
        /** @var $type Sequence|Choice */
        if(false === in_array(get_class($type), [Sequence::class, Choice::class], true)) {
            throw new \RuntimeException(sprintf('Invalid pseudo type %s!', XsdUtility::describe($type)));
        }

        $context = new ClassContext();
        $complexType = new ComplexType($type->getNamespaceUri(), $name, $type, [], null);

        $this->complexType($complexType, $schema, $context, $level + 1);

        $context->comment = $comment;
        $context->name = $this->namespaceResolver->convertNameToClassName($name).$suffix;
        $context->namespace = $this->namespaceResolver->convertUriToNs($schema->getNamespace());
        $context->xmlNamespace = $schema->getNamespace();

        $this->writeClass($context);
    }

    private function dispatch($parent, array $elements, Schema $schema, ClassContext $context, int $level)
    {
        if($parent instanceof Element) {
            $parentName = $parent->getName();
            $parentType = $parent->getType();
        } elseif($parent instanceof ComplexType) {
            $parentName = $parent->getName();
            $parentType = $parent->getName();
        } else {
            throw new \RuntimeException(sprintf('Invalid dispatch parent %s!', XsdUtility::describe($parent)));
        }

        foreach($elements as $element) {
            if($element instanceof Element) {
                if($element->getType() instanceof ComplexType && $element->getType()->getType() instanceof SimpleContent && $element->getType()->getType()->getType() instanceof Extension) {
                    $extension = $element->getType()->getType()->getType();
                    $this->log($level, 'ElementSC', $element->getName(), $extension->getBase(), XsdUtility::occurs($element));

                    $innerContext = new ClassContext();
                    $this->dispatch($element->getType(), $extension->getAttributes(), $schema, $innerContext, $level);
                    $innerContext->name = $parentName.'_'.ucfirst($element->getName());
                    $innerContext->namespace = $this->namespaceResolver->convertUriToNs($schema->getNamespace());
                    $innerContext->xmlNamespace = $schema->getNamespace();
                    $innerContext->getters[] = '    public function getXmlName(): string { return \''.$element->getName().'\'; }';
                    $this->writeClass($innerContext);
                    $this->handleParameter($context, $schema, $element->getName(), $parentName.'_'.ucfirst($element->getName()), $element->getMinOccurs(), $element->getMaxOccurs(), 'Dispatch/Element');
                } else {
                    $this->log($level, 'Element', $element->getName(), XsdUtility::describe($element->getType()), XsdUtility::occurs($element));
                    $this->handleParameter($context, $schema, $element->getName(), $element->getType(), $element->getMinOccurs(), $element->getMaxOccurs(), 'Dispatch/Element');
                    $context->uses[] = $this->convertXsdTypeToFqcn($schema, $element->getType());
                }
            } elseif($element instanceof Sequence) {
                $this->counter->tickSequence();
                $this->log($level, 'DSequence');
                $this->handleParameter($context, $schema, $parentName.'_Sequence', $parentType.'_Sequence', 1, 1, 'Dispatch/Sequence');
                $this->pseudoType($parentName, $element, $schema, $level + 1, '/* PSEUDO SEQUENCE */', '_Sequence');
            } elseif($element instanceof Choice) {
                $this->counter->tickChoice();
                $this->log($level, 'DChoice');
                $this->handleParameter($context, $schema, $parentName.'_Choice', $parentType.'_Choice', $element->getMinOccurs(), $element->getMaxOccurs(), 'Dispatch/Choice');
                $this->pseudoType($parentName, $element, $schema, $level + 1, '/* PSEUDO CHOICE */', '_Choice');
            } elseif($element instanceof Attribute) {
                $this->counter->tickAttribute();
                $this->log($level, 'Attribute', $element->getName(), XsdUtility::describe($element->getType()), $element->getUse());
                $this->handleParameter($context, $schema, $element->getName(), $element->getType(), $element->getUse() === 'required' ? 1 : 0, 1, 'Dispatch/Attribute');
                $context->ctorChecks[] = '        // FIXME: Validate attribute `'.$element->getName().'` type `'.XsdUtility::describe($element->getType()).'`';
            } else {
                throw new \RuntimeException(sprintf('Invalid dispatch type %s!', get_class($element)));
            }
        }
    }

    private function simpleType(SimpleType $simpleType, Schema $schema, ClassContext $context, int $level): ClassContext
    {
        $this->counter->tickSimpleType();
        $this->log($level, 'SimpleType', $simpleType->getName(), XsdUtility::describe($simpleType->getType()));

        $type = $simpleType->getType();
        $context->comment = '/* SIMPLE TYPE */';
        $context->name = $this->namespaceResolver->convertNameToClassName($simpleType->getName());
        $context->namespace = $this->namespaceResolver->convertUriToNs($schema->getNamespace());
        if($type instanceof Restrictions) {
            $typeName = $this->convertXsdTypeToName($schema, $type->getBase());
            $context->properties[] = '    /** @var '.$typeName.' */'."\n".'    private $value;';
            $context->ctorArgs[] = $typeName.' $value';
            $context->getters[] = $this->createGetter($schema, 'value', $typeName);
            $context->ctorChecks = $this->restrictionChecks($type, 'value');
        }
        $context->ctorAssigns[] = '        $this->value = $value;';
        $context->getters[] = '    public function getXmlName(): string { return \''.$simpleType->getName().'\'; }';
        $context->xmlNamespace = $schema->getNamespace();

        return $context;
    }

    /* --- UTILITIES -------------------------------------------------------- */

    private function findTypeEverywhere(SchemaContainer $schemas, Schema $schema, string $name)
    {
        if(false !== strpos($name, ':')) {
            list($prefix, $name) = explode(':', $name, 2);
            $namespaces = $schema->getNamespaces();
            if(false === array_key_exists($prefix, $namespaces)) {
                throw new \RuntimeException(sprintf('Failed to find prefix %s among %s!', $prefix.':'.$name, json_encode($namespaces)));
            }
            $schema = $schemas->findSchemaByNs($namespaces[$prefix]);
        }

        $type = null;
        try {
            $type = $schema->findTypeByName($name);
        } catch(\RuntimeException $e) {
            $visited = [];
            $type = $this->findTypeEverywhereTraverseIncludes($schemas, $schema, $name, $visited);
        }

        return [$schema, $type];
    }

    private function findTypeEverywhereTraverseIncludes(SchemaContainer $schemas, Schema $schema, string $name, array &$visited)
    {
        // xsd:include is for referencing elements in the same namespace
        // defined in different files. the schemaLocation attribute defines
        // relative schema file location in the filesystem
        foreach($schema->getIncludes() as $include) {
            if(in_array($include, $visited, true)) {
                continue;
            }
            $visited[] = $include;
            try {
                $innerSchema = $schemas->findSchemaByLocation($include);
            } catch(\RuntimeException $e) {
                continue;
            }
            try {
                return $innerSchema->findTypeByName($name);
            } catch(\RuntimeException $e) {
                try {
                    $type = $this->findTypeEverywhereTraverseIncludes($schemas, $innerSchema, $name, $visited);
                    if(null === $type) {
                        continue;
                    }
                    return $type;
                } catch(\RuntimeException $e) {
                    // shh...
                }
            }
        }

        return null;
    }

    private function restrictionChecks(Restrictions $res, string $name)
    {
        // FIXME: refactor to arrays [conditition, if, then] and filter false conditions
        return array_map(function(string $item) { return '        '.str_replace("\n", "\n        ", $item); }, array_filter([
            'xsd:date' === $res->getBase() ? 'if(false === strtotime($'.$name.')) {
    throw new \InvalidArgumentException(sprintf(\'%s value `%s` invalid date, expected Y-m-d!\', __CLASS__, $'.$name.'));
}' : '',
            'xsd:dateTime' === $res->getBase() ? 'if(false === strtotime($'.$name.')) {
    throw new \InvalidArgumentException(sprintf(\'%s value `%s` invalid date and time, expected Y-m-dTH:i:s!\', __CLASS__, $'.$name.'));
}' : '',
            null === $res->getMinLength() ? '' : 'if(\is_string($'.$name.') && \mb_strlen($'.$name.') < '.$res->getMinLength().') {
    throw new \InvalidArgumentException(sprintf(\'%s value `%s` invalid length %s, expected >= %s!\', __CLASS__, $'.$name.', \mb_strlen($'.$name.'), '.number_format($res->getMinLength(), 0, '', '').'));
}',
            null === $res->getMaxLength() ? '' : 'if(\is_string($'.$name.') && \mb_strlen($'.$name.') > '.$res->getMaxLength().') {
    throw new \InvalidArgumentException(sprintf(\'%s value `%s` invalid length %s, expected <= %s!\', __CLASS__, $'.$name.', \mb_strlen($'.$name.'), '.number_format($res->getMaxLength(), 0, '', '').'));
}',
            null === $res->getMinInclusive() ? '' : 'if($'.$name.' < '.$res->getMinInclusive().') {
    throw new \InvalidArgumentException(sprintf(\'%s value `%s` too low, expected >= %s!\', __CLASS__, $'.$name.', '.number_format($res->getMinInclusive(), 0, '', '').'));
}',
            null === $res->getMaxInclusive() ? '' : 'if($'.$name.' > '.$res->getMaxInclusive().') {
    throw new \InvalidArgumentException(sprintf(\'%s value `%s` too high, expected <= %s!\', __CLASS__, $'.$name.', '.number_format($res->getMaxInclusive(), 0, '', '').'));
}',
            null === $res->getFractionDigits() ? '' : 'if(\is_string($'.$name.') && !\\preg_match(\'~\\.\\d{'.$res->getFractionDigits().'}$~\', (string)$'.$name.')) {
    throw new \InvalidArgumentException(\'Invalid fraction digits!\');
}',
            null === $res->getLength() ? '' : 'if(\is_string($'.$name.') && \mb_strlen($'.$name.') !== '.$res->getLength().') {
    throw new \InvalidArgumentException(sprintf(\'%s value `%s` invalid length %s, expected %s!\', __CLASS__, $'.$name.', \mb_strlen($'.$name.'), '.$res->getLength().'));
}',
            empty($res->getEnumerations()) ? '' : 'if(\is_string($'.$name.') && false === \in_array($'.$name.', [\''.implode('\', \'', $res->getEnumerations()).'\'], true)) {
    throw new \InvalidArgumentException(sprintf(\'%s value `%s` not in enumeration %s!\', __CLASS__, $'.$name.', \'['.implode(', ', $res->getEnumerations()).']\'));
}',
            empty($res->getPatterns()) ? '' : 'if(!\preg_match(\'~'.implode('|', $res->getPatterns()).'~\', $'.$name.')) {
    throw new \InvalidArgumentException(sprintf(\'%s value `%s` not in enumeration %s!\', __CLASS__, $'.$name.', \'['.implode(', ', $res->getPatterns()).']\'));
}',
        ]));
    }

    private function createGetter(Schema $schema, string $name, $type): string
    {
        return '    public function get'.ucfirst($name).'(): '.$this->convertXsdTypeToName($schema, $type).' { return $this->'.$name.'; }';
    }

    private function writeClass(ClassContext $context)
    {
        $this->counter->tickClass();
        $context->getters[] = '    public function getXmlNamespace(): string { return \''.$context->xmlNamespace.'\'; }';
        $context->uses[] = XmlObjectInterface::class;

        $fqcnToUse = function(string $fqcn) { return 'use '.$fqcn.';'; };
        $isPrimitiveType = function(string $fqcn) { return $fqcn && false === in_array($fqcn, ['int', 'string', 'bool'], true); };

        $replaces = [
            '<COMMENT>' => $context->comment,
            '<NS>' => $context->namespace,
            '<CLASS>' => ucfirst($context->name),
            '<IMPLEMENTS>' => ' implements XmlObjectInterface',
            '<USES>' => $context->uses ? implode("\n", array_map($fqcnToUse, array_filter(array_unique($context->uses), $isPrimitiveType)))."\n\n" : '',
            '<PROPERTIES>' => $context->properties ? implode("\n", $context->properties)."\n" : '',
            '<CONSTRUCTOR_VISIBILITY>' => $context->ctorVisibility,
            '<CONSTRUCTOR_PARAMETERS>' => implode(', ', $context->ctorArgs),
            '<CONSTRUCTOR_VALIDATION>' => $context->ctorChecks ? "\n".implode("\n", $context->ctorChecks)."\n" : '',
            '<CONSTRUCTOR_ASSIGNMENTS>' => $context->ctorAssigns ? "\n".implode("\n", $context->ctorAssigns) : '',
            '<NAMED_CONSTRUCTORS>' => $context->namedCtors ? "\n\n".implode("\n\n", $context->namedCtors) : '',
            '<GETTERS>' => $context->getters ? "\n\n".implode("\n", $context->getters) : '',
        ];

        $finalPath = str_replace('\\', '/', $this->namespaceResolver->convertFqcnToPath($context->namespace.'\\'.ucfirst($context->name))).'.php';
        $code = str_replace(array_keys($replaces), array_values($replaces), static::TEMPLATE);


        $this->filesystem->write($finalPath, $code);
    }

    private function convertXsdTypeToFqcn(Schema $schema, $type): string
    {
        if($type instanceof SimpleType) {
            $type = $type->getType()->getBase();
        }
        if($type instanceof ComplexType) {
            $type = $type->getName();
        }
        if(null === $type) {
            return '';
        }

        if(false === is_string($type)) {
            throw new \RuntimeException(sprintf('Invalid FQCN type %s!', XsdUtility::describe($type)));
        }

        $qualified = XsdUtility::qualifiedName($this->schemas, $schema, $type);
        if(XsdUtility::isPrimitiveType($qualified)) {
            return XsdUtility::getPrimitivePhpType($qualified);
        }

        if($position = strpos($type, ':')) {
            list($prefix, $type) = explode(':', $type, 2);

            $namespaces = $schema->getNamespaces();
            if(false === array_key_exists($prefix, $namespaces)) {
                throw new \RuntimeException(sprintf('XSD element %s namespace prefix %s not found among %s!', $type, $prefix, implode(', ', array_keys($namespaces))));
            }

            return $this->namespaceResolver->convertUriToNs($namespaces[$prefix]).'\\'.ucfirst($type);
        }

        return $type ? $this->namespaceResolver->convertUriToNs($schema->getNamespace()).'\\'.ucfirst($type) : '';
    }

    private function handleParameter(ClassContext $context, Schema $schema, string $name, $type, $minOccurs, $maxOccurs, string $comment)
    {
        if(null === $type) {
            $context->ctorArgs[] = '$'.$name;
            $context->ctorAssigns[] = '        $this->'.$name.' = $'.$name.'; // '.$comment;
            $context->getters[] = $this->createGetter($schema, $name, '');

            return;
        }

        if($type instanceof SimpleType) {
            if($type->getType() instanceof Restrictions) {
                $context->ctorChecks = array_merge($context->ctorChecks, $this->restrictionChecks($type->getType(), $name));
            }
            // FIXME: support Union
        } else {
            $realType = null;
            if(is_string($type) && false === XsdUtility::isPrimitiveType($type)) {
                $typeX = '';
                if(strpos($type, ':')) {
                    $schemaUris = $this->schemas->findUrisFor($schema);
                    list($prefix, $typeX) = explode(':', $type, 2);
                    if(false === array_key_exists($prefix, $schemaUris)) {
                        throw new \RuntimeException(sprintf('Element %s prefix %s not found among namespaces %s!', $typeX, $prefix, var_export($schemaUris, true)));
                    }
                    if('http://www.w3.org/2001/XMLSchema' === $schemaUris[$prefix]) {
                        $realType = null;
                    } else {
                        $schema = $this->schemas->findSchemaByNs($schemaUris[$prefix]);
                    }
                }
                try {
                    $realType = $schema->findTypeByName($typeX);
                    if($realType instanceof SimpleType) {
                        $context->ctorChecks = array_merge($context->ctorChecks, $this->restrictionChecks($realType->getType(), $name));
                    }
                } catch(\RuntimeException $e) {
                    // shh...
                }
            }
        }

        $type = $this->resolveRealTypeName($schema, $type);
        $xsdTypeName = $this->convertXsdTypeToName($schema, $type);
        $optional = false;
        switch(true) {
            case null === $minOccurs && null === $maxOccurs: { $typeName = $xsdTypeName; break; }
            case 1 === $minOccurs && 1 === $maxOccurs: { $typeName = $xsdTypeName; break; }
            case 0 === $minOccurs && 1 === $maxOccurs: { $typeName = $xsdTypeName; $optional = true; break; }
            case 1 === $minOccurs && 'unbounded' === $maxOccurs: { $typeName = 'array'; break; }
            case 0 === $minOccurs && 'unbounded' === $maxOccurs: { $typeName = 'array'; break; }
            case null === $minOccurs && 'unbounded' === $maxOccurs: { $typeName = 'array'; break; }
            case null === $minOccurs && is_int($maxOccurs) && $maxOccurs > 1: { $typeName = 'array'; break; }
            case 0 === $minOccurs && is_int($maxOccurs) && $maxOccurs > 1: { $typeName = 'array'; break; }
            case 1 === $minOccurs && is_int($maxOccurs) && $maxOccurs > 1: { $typeName = 'array'; break; }
            case is_int($minOccurs) && $minOccurs > 1 && is_int($maxOccurs) && $maxOccurs > 1: { $typeName = 'array'; break; }
            case is_int($minOccurs) && $minOccurs > 1 && 'unbounded' === $maxOccurs: { $typeName = 'array'; break; }
            case 0 === $minOccurs && null === $maxOccurs: { $typeName = $xsdTypeName; $optional = true; break; }
            default: { throw new \RuntimeException(sprintf('Invalid arg type combination: `%s`%s`!', XsdUtility::describe($minOccurs), XsdUtility::describe($maxOccurs))); }
        }

        $context->properties[] = '    /** @var '.$typeName.' */'."\n".'    private $'.$name.';';
        $context->ctorArgs[] = $typeName.' $'.$name.($optional ? ' = null' : '');
        $context->ctorAssigns[] = '        $this->'.$name.' = $'.$name.'; // '.$comment;
        $context->getters[] = ('array' === $typeName && $xsdTypeName ? '    /** @return '.$xsdTypeName.'[] */'."\n" : '')
            .'    public function get'.ucfirst($name).'()'.($optional ? '' : ': '.$typeName).' { return $this->'.$name.'; }';
    }

    private function resolveRealTypeName(Schema $schema, $type): string
    {
        if($type instanceof SimpleType) {
            if($type->getType() instanceof Restrictions) {
                $type = $type->getType()->getBase();
            } elseif($type->getType() instanceof Union) {
                $type = $type->getType()->getMemberTypes();
                if(empty($type)) {
                    return '';  // FIXME: case with Attribute -> SimpleType -> Union -> SimpleTypes with Restrictions
                }
                $type = $type[0];
            } elseif($type->getType() instanceof ListNode) {
                $type = $type->getType()->getItemType();
            }
        }

        if($type instanceof ComplexType) {
            $type = $type->getName();
        }

        if(false === is_string($type)) {
            throw new \RuntimeException(sprintf('Invalid parameter type %s!', XsdUtility::describe($type)));
        }

        if(strpos($type, ':') && false === XsdUtility::isPrimitiveType($type)) {
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
                    if('http://www.w3.org/2001/XMLSchema' === $schemaUris[$prefix]) {
                        $realType = null;
                    } else {
                        $realType = $this->schemas->findSchemaByNs($schemaUris[$prefix])->findTypeByName($newType);
                    }
                }
            }
            if($realType instanceof SimpleType) {
                $type = $realType->getType()->getBase();
            }
        }

        try {
            $typeObject = $schema->findTypeByName($type);

            if($typeObject instanceof SimpleType && $typeObject->getType() instanceof Restrictions) {
                return $typeObject->getType()->getBase();
            }

            return $type;
        } catch(\RuntimeException $e) {
            return $type;
        }
    }

    private function convertXsdTypeToName(Schema $schema, $type): string
    {
        if($type instanceof SimpleType) {
            $type = $type->getType()->getBase();
        } elseif($type instanceof ComplexType) {
            $type = $type->getName();
        }

        if(false === is_string($type)) {
            throw new \RuntimeException(sprintf('Invalid parameter type %s!', XsdUtility::describe($type)));
        }

        $qualified = XsdUtility::qualifiedName($this->schemas, $schema, $type);
        if(XsdUtility::isPrimitiveType($qualified)) {
            return XsdUtility::getPrimitivePhpType($qualified);
        }

        return strpos($type, ':') ? ucfirst(explode(':', $type, 2)[1]) : ucfirst($type);
    }

    private function log(int $level, string ...$message)
    {
        XsdUtility::log($this->logger, $level, ...$message);
    }

    const TEMPLATE = <<<'EOF'
<?php
<COMMENT>
declare(strict_types=1);
namespace <NS>;

<USES>final class <CLASS><IMPLEMENTS>
{
<PROPERTIES>
    <CONSTRUCTOR_VISIBILITY> function __construct(<CONSTRUCTOR_PARAMETERS>)
    {<CONSTRUCTOR_VALIDATION><CONSTRUCTOR_ASSIGNMENTS>
    }<NAMED_CONSTRUCTORS><GETTERS>
}

EOF;
}
