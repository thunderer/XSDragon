<?php
declare(strict_types = 1);
namespace Thunder\Xsdragon\Schema;

final class Element
{
    /** @var string */
    private $namespaceUri;
    /** @var string */
    private $name;
    /** @var null|string|SimpleType|ComplexType */
    private $type;
    /** @var ?string */
    private $documentation;
    /** @var null|int|string */
    private $maxOccurs;
    /** @var null|int|string */
    private $minOccurs;
    /** @var bool */
    private $isNullable;

    public function __construct(string $namespaceUri, string $name, $type, $minOccurs, $maxOccurs, bool $isNullable, string $documentation = null)
    {
        if(null !== $type && false === (is_string($type) && false === empty($type)) && false === $type instanceof SimpleType && false === $type instanceof ComplexType) {
            throw new \InvalidArgumentException(sprintf('Invalid element type %s!', is_object($type) ? get_class($type) : $type));
        }
        if(false === (is_int($minOccurs) && $minOccurs >= 0) && 'unbounded' !== $minOccurs && null !== $minOccurs) {
            throw new \InvalidArgumentException(sprintf('Choice minOccurs can be either null, non-negative integer or string `unbounded`, %s given!', $minOccurs));
        }
        if(false === (is_int($maxOccurs) && $maxOccurs >= 0) && 'unbounded' !== $maxOccurs && null !== $maxOccurs) {
            throw new \InvalidArgumentException(sprintf('Choice maxOccurs can be either null, non-negative integer or string `unbounded`, %s given!', $maxOccurs));
        }

        $this->namespaceUri = $namespaceUri;
        $this->name = $name;
        $this->type = $type;
        $this->documentation = $documentation;
        $this->minOccurs = $minOccurs;
        $this->maxOccurs = $maxOccurs;
        $this->isNullable = $isNullable;
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

    public function getMinOccurs()
    {
        return $this->minOccurs;
    }

    public function getMaxOccurs()
    {
        return $this->maxOccurs;
    }

    public function getDocumentation()
    {
        return $this->documentation;
    }

    public function isNullable(): bool
    {
        return $this->isNullable;
    }
}
