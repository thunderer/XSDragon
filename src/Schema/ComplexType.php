<?php
declare(strict_types=1);
namespace Thunder\Xsdragon\Schema;

final class ComplexType
{
    /** @var string */
    private $namespaceUri;
    /** @var string */
    private $name;
    /** @var ComplexType|SimpleType */
    private $type;
    /** @var Attribute[] */
    private $attributes;
    /** @var ?string */
    private $documentation;

    public function __construct(string $namespaceUri, string $name, $type, array $attributes, ?string $documentation)
    {
        $validTypes = [ComplexType::class, Sequence::class, All::class, ComplexContent::class, Choice::class, SimpleContent::class];
        if(null !== $type && false === (is_object($type) && in_array(get_class($type), $validTypes, true))) {
            throw new \InvalidArgumentException(sprintf('Invalid ComplexType type: %s!', is_object($type) ? get_class($type) : gettype($type)));
        }

        $this->namespaceUri = $namespaceUri;
        $this->name = $name;
        $this->type = $type;
        $this->attributes = $attributes;
        $this->documentation = $documentation;
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

    /** @return Attribute[] */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getDocumentation(): ?string
    {
        return $this->documentation;
    }

    public function countAttributes(): int
    {
        return count($this->attributes);
    }
}
