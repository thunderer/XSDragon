<?php
declare(strict_types=1);
namespace Thunder\Xsdragon\Utility;

use Thunder\Xsdragon\Counter\Counter;
use Thunder\Xsdragon\Logger\LoggerInterface;
use Thunder\Xsdragon\Logger\NullLogger;
use Thunder\Xsdragon\Schema\Choice;
use Thunder\Xsdragon\Schema\ComplexType;
use Thunder\Xsdragon\Schema\Element;
use Thunder\Xsdragon\Schema\Restrictions;
use Thunder\Xsdragon\Schema\Schema;
use Thunder\Xsdragon\Schema\SchemaContainer;
use Thunder\Xsdragon\Schema\SimpleType;

final class XsdUtility
{
    /** @see http://patorjk.com/software/taag/#p=display&f=ANSI%20Shadow&t=XSDragon */
    public static function logo(): string
    {
        return <<<EOF
XSDragon (c) 2016-2018 Tomasz Kowalczyk
    
    \e[97m██╗  ██╗███████╗██████╗ \e[0m██████╗  █████╗  ██████╗  ██████╗ ███╗   ██╗\e[0m
    \e[97m╚██╗██╔╝██╔════╝██╔══██╗\e[0m██╔══██╗██╔══██╗██╔════╝ ██╔═══██╗████╗  ██║\e[0m
    \e[97m ╚███╔╝ ███████╗██║  ██║\e[0m██████╔╝███████║██║  ███╗██║   ██║██╔██╗ ██║\e[0m
    \e[97m ██╔██╗ ╚════██║██║  ██║\e[0m██╔══██╗██╔══██║██║   ██║██║   ██║██║╚██╗██║\e[0m
    \e[97m██╔╝ ██╗███████║██████╔╝\e[0m██║  ██║██║  ██║╚██████╔╝╚██████╔╝██║ ╚████║\e[0m
    \e[97m╚═╝  ╚═╝╚══════╝╚═════╝ \e[0m╚═╝  ╚═╝╚═╝  ╚═╝ ╚═════╝  ╚═════╝ ╚═╝  ╚═══╝\e[0m


EOF;
    }

    public static function formatCounter(Counter $counter): string
    {
        $template = <<<EOF
Done.
Processed \e[33m<S>\e[0m schemas with \e[37m<E>\e[0m Elements, \e[37m<CT>\e[0m ComplexTypes, and \e[37m<ST>\e[0m SimpleTypes in \e[33m<T>\e[0m.
Types contained \e[37m<A>\e[0m attributes, \e[37m<SQ>\e[0m Sequences, \e[37m<CH>\e[0m Choices, and \e[37m<AL>\e[0m Alls.
Generated \e[37m<C>\e[0m classes, \e[37m<PS>\e[0m of which were pseudotypes.


EOF;

        return XsdUtility::replace([
            '<A>' => $counter->getAttributesCount(),
            '<S>' => $counter->getSchemasCount(),
            '<E>' => $counter->getElementsCount(),
            '<CT>' => $counter->getComplexTypesCount(),
            '<ST>' => $counter->getSimpleTypesCount(),
            '<C>' => $counter->getClassesCount(),
            '<PS>' => $counter->getPseudoTypesCount(),
            '<SQ>' => $counter->getSequencesCount(),
            '<CH>' => $counter->getChoicesCount(),
            '<AL>' => $counter->getAllsCount(),
            '<T>' => number_format(($counter->getFinishedAt() - $counter->getStartedAt()) * 1000).'ms',
        ], $template);
    }

    /**
     * @deprecated FIXME: move to SchemaContainer
     */
    public static function findTypeEverywhere(SchemaContainer $schemas, Schema $schema, string $name)
    {
        if(false !== strpos($name, ':')) {
            list($prefix, $name) = explode(':', $name, 2);
            $namespaces = $schema->getNamespaces();
            if(false === array_key_exists($prefix, $namespaces)) {
                throw new \RuntimeException(sprintf('Failed to find prefix %s among %s!', $prefix.':'.$name, json_encode($namespaces)));
            }

            return $schemas->findSchemaByNs($namespaces[$prefix])->findTypeByName($name);
        }

        return $schema->findTypeByName($name);
    }

    /**
     * @deprecated FIXME: dead code, remove
     */
    public static function resolveRealTypeName(SchemaContainer $schemas, Schema $schema, $type): string
    {
        if($type instanceof SimpleType) {
            $type = $type->getType()->getBase();
        }

        if($type instanceof ComplexType) {
            $type = $type->getName();
        }

        if(false === is_string($type)) {
            throw new \RuntimeException(sprintf('Invalid parameter type %s!', static::describe($type)));
        }

        if(strpos($type, ':') && false === static::isPrimitiveType($type)) {
            try {
                $realType = $schema->findTypeByName($type);
            } catch(\RuntimeException $e) {
                list($prefix, $newType) = explode(':', $type, 2);
                try {
                    $realType = $schema->findTypeByName($newType);
                } catch(\RuntimeException $e) {
                    $schemaUris = $schemas->findUrisFor($schema);
                    if(false === array_key_exists($prefix, $schemaUris)) {
                        throw new \RuntimeException(sprintf('Prefix `%s` not found among schema `%s` namespaces %s!', $prefix, $schema->getNamespace(), var_export($schemaUris, true)));
                    }
                    $realType = $schemas->findSchemaByNs($schemaUris[$prefix])->findTypeByName($newType);
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

    public static function isPrimitiveType(string $type)
    {
        return array_key_exists($type, static::$primitiveTypeMap);
    }

    public static function getPrimitivePhpType($type)
    {
        return static::$primitiveTypeMap[$type];
    }

    public static function describe($var): string
    {
        switch(true) {
            case $var instanceof Restrictions: { return static::restriction($var); }
            case is_object($var): { return get_class($var); }
            case is_scalar($var): { return (string)$var; }
            case is_array($var): { return 'array('.count($var).')<'.implode(',', array_map([static::class, 'describe'], $var)).'>'; }
            default: gettype($var);
        }

        if(is_object($var)) { return get_class($var); }
        if(is_scalar($var)) { return (string)$var; }
        if(is_array($var)) { return 'array('.count($var).')<'.implode(',', array_map([static::class, 'describe'], $var)).'>'; }

        return gettype($var);
    }

    private static function restriction(Restrictions $res): string
    {
        $parts = [];

        if(null !== $res->getMinLength() || null !== $res->getMaxLength()) { $parts[] = 'len='.$res->getMinLength().';'.$res->getMaxLength(); }
        if(null !== $res->getMinInclusive() || null !== $res->getMaxInclusive()) { $parts[] = 'range='.$res->getMinInclusive().';'.$res->getMaxInclusive(); }
        if(null !== $res->getLength()) { $parts[] = 'len='.$res->getLength(); }
        if(null !== $res->getFractionDigits()) { $parts[] = 'frac='.$res->getFractionDigits(); }
        if(false === empty($res->getEnumerations())) { $parts[] = 'enum='.implode(',', $res->getEnumerations()); }
        if(false === empty($res->getPatterns())) { $parts[] = 'pattern='.implode(',', $res->getPatterns()); }

        return $res->getBase().'<'.implode('|', $parts ?: ['*EMPTY*']).'>';
    }

    public static function occurs($var): string
    {
        if($var instanceof Element) {
            return XsdUtility::describe($var->getMinOccurs()).'..'.XsdUtility::describe($var->getMaxOccurs());
        } elseif($var instanceof Choice) {
            return XsdUtility::describe($var->getMinOccurs()).'..'.XsdUtility::describe($var->getMaxOccurs());
        }

        throw new \RuntimeException('Invalid occurs object!');
    }

    public static function log(LoggerInterface $logger, int $level, string ...$message)
    {
        if($logger instanceof NullLogger) {
            return;
        }

        $colors = [29, 33, 34, 32, 35, 36, 37];
        if(count($message) > count($colors)) {
            throw new \RuntimeException(sprintf('Failed to process log message, not enough colors %s, %s required!', count($colors), count($message)));
        }

        $pre = '';
        for($i = 0; $i < $level; $i++) {
            $pre .= "\e[".$colors[$i % count($colors)]."m| \e[0m";
        }
        $logger->log($pre.implode(' ', array_map(function(string $item, int $color) {
            return "\e[".$color.'m'.$item."\e[0m";
        }, $message, array_slice($colors, 0, count($message)))));
    }

    public static function formatXml(string $xml)
    {
        $dom = new \DOMDocument();
        $dom->formatOutput = true;
        $dom->loadXML($xml);

        return $dom->saveXML();
    }

    public static function formatXmlNode(\DOMNode $xml)
    {
        $xml->ownerDocument->formatOutput = true;

        return $xml->ownerDocument->saveXML($xml);
    }

    public static function xmlPrefix(SchemaContainer $schemas, string $uri, Schema $schema): string
    {
        try {
            return $schema->getNamespace() === $uri ? '' : $schema->findNamespacePrefix($uri).':';
        } catch(\RuntimeException $e) {
            foreach($schema->getImports() as $import) {
                try {
                    return static::xmlPrefix($schemas, $uri, $schemas->findSchemaByNs($import));
                    // return $this->schemas->findSchemaByNs($import)->findNamespacePrefix($uri).':';
                } catch(\RuntimeException $e) {
                    // shh...
                }
            }
        }

        throw new \RuntimeException(sprintf('Failed to find prefix for URI `%s` in Schema `%s`!', $uri, $schema->getNamespace()));
    }

    public static function replace(array $replaces, string $template): string
    {
        return str_replace(array_keys($replaces), array_values($replaces), $template);
    }

    public static function qualifiedName(SchemaContainer $schemas, Schema $schema, string $type)
    {
        if(false === strpos($type, ':')) {
            return '';
        }

        list($prefix, $name) = explode(':', $type, 2);
        $uris = $schemas->findUrisFor($schema);

        return sprintf('{%s}%s', array_key_exists($prefix, $uris) ? $uris[$prefix] : $schema->getNamespace(), $name);
    }

    private static $primitiveTypeMap = [
        '{http://www.w3.org/2001/XMLSchema}date' => 'string',
        '{http://www.w3.org/2001/XMLSchema}dateTime' => 'string',
        '{http://www.w3.org/2001/XMLSchema}string' => 'string',
        '{http://www.w3.org/2001/XMLSchema}anyURI' => 'string',

        '{http://www.w3.org/2001/XMLSchema}decimal' => 'string', // requires certain precision
        '{http://www.w3.org/2001/XMLSchema}int' => 'int',
        '{http://www.w3.org/2001/XMLSchema}integer' => 'int',
        '{http://www.w3.org/2001/XMLSchema}long' => 'int',
        '{http://www.w3.org/2001/XMLSchema}positiveInteger' => 'int', // FIXME: range validated elsewhere
        '{http://www.w3.org/2001/XMLSchema}nonNegativeInteger' => 'int', // FIXME: range validated elsewhere

        '{http://www.w3.org/2001/XMLSchema}boolean' => 'bool',
    ];
}
