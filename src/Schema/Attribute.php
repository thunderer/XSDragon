<?php
declare(strict_types = 1);
namespace Thunder\Xsdragon\Schema;

final class Attribute
{
    /** @var string */
    private $namespaceUri;
    /** @var string */
    private $name;
    /** @var null|string|SimpleType */
    private $type;
    /** @var string */
    private $use;

    public function __construct(string $namespaceUri, string $name, $type, string $use)
    {
        if(empty($name)) {
            throw new \InvalidArgumentException('Attribute name must not be empty!');
        }
        if(false === (null === $type || $type instanceof SimpleType || (is_string($type) && false === empty($type)))) {
            throw new \InvalidArgumentException('Attribute type must be either null, non-empty string or SimpleType object!');
        }
        $validUses = ['required', 'optional'];
        if(false === in_array($use, $validUses, true)) {
            throw new \InvalidArgumentException(sprintf('Attribute use must be one of %s, `%s` given!', json_encode($validUses), $use));
        }

        $this->namespaceUri = $namespaceUri;
        $this->name = $name;
        $this->type = $type;
        $this->use = $use;
    }

    public function getNamespaceUri(): string
    {
        return $this->namespaceUri;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType()
    {
        return $this->type;
    }

    public function getUse(): string
    {
        return $this->use;
    }
}
