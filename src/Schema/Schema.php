<?php
declare(strict_types=1);
namespace Thunder\Xsdragon\Schema;

final class Schema
{
    /** @var string */
    private $namespace;
    /** @var string[] */
    private $namespaces;
    /** @var string[] */
    private $imports = [];
    /** @var SimpleType[] */
    private $simpleTypes = [];
    /** @var ComplexType[] */
    private $complexTypes = [];
    /** @var Element[] */
    private $elements = [];

    public function __construct($namespace, array $namespaces)
    {
        $this->namespace = $namespace;
        $this->namespaces = $namespaces;
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function getNamespaces(): array
    {
        return $this->namespaces;
    }

    public function getImports(): array
    {
        return $this->imports;
    }

    public function getSimpleTypes()
    {
        return $this->simpleTypes;
    }

    public function getComplexTypes()
    {
        return $this->complexTypes;
    }

    public function getElements()
    {
        return $this->elements;
    }

    public function hasSimpleTypeWithName(string $name): bool
    {
        return isset($this->simpleTypes[$name]);
    }

    public function hasComplexTypeWithName(string $name): bool
    {
        return isset($this->complexTypes[$name]);
    }

    public function hasElementWithName(string $name): bool
    {
        return isset($this->elements[$name]);
    }

    public function addImport(string $import)
    {
        $this->imports[] = $import;
    }

    public function addSimpleType(SimpleType $type)
    {
        if($this->hasSimpleTypeWithName($type->getName())) {
            throw new \RuntimeException(sprintf('Duplicate SimpleType identifier %s!', $type->getName()));
        }

        $this->simpleTypes[$type->getName()] = $type;
    }

    public function addComplexType(ComplexType $type)
    {
        if($this->hasComplexTypeWithName($type->getName())) {
            throw new \RuntimeException(sprintf('Duplicate ComplexType identifier %s!', $type->getName()));
        }

        $this->complexTypes[$type->getName()] = $type;
    }

    public function addElement(Element $type)
    {
        if($this->hasElementWithName($type->getName())) {
            throw new \RuntimeException(sprintf('Duplicate Element identifier %s!', $type->getName()));
        }

        $this->elements[$type->getName()] = $type;
    }

    public function findTypeByName(string $name)
    {
        foreach($this->elements as $element) {
            if($element->getName() === $name) {
                return $element;
            }
        }
        foreach($this->complexTypes as $complexType) {
            if($complexType->getName() === $name) {
                return $complexType;
            }
        }
        foreach($this->simpleTypes as $simpleType) {
            if($simpleType->getName() === $name) {
                return $simpleType;
            }
        }

        throw new \RuntimeException(sprintf('Failed to find type with name %s! XMLNS: %s', $name, $this->namespace));
    }

    public function findElementTypeByName(string $name)
    {
        foreach($this->complexTypes as $complexType) {
            if($complexType->getName() === $name) {
                return $complexType;
            }
        }
        foreach($this->simpleTypes as $simpleType) {
            if($simpleType->getName() === $name) {
                return $simpleType;
            }
        }

        throw new \RuntimeException(sprintf('Failed to find Element type with name `%s`! XMLNS: `%s`', $name, $this->namespace));
    }

    public function findNamespacePrefix(string $uri): string
    {
        $uriPrefixes = array_flip($this->namespaces);

        if(false === array_key_exists($uri, $uriPrefixes)) {
            throw new \RuntimeException(sprintf('This schema does not contain URI `%s`!', $uri));
        }

        return $uriPrefixes[$uri];
    }
}
