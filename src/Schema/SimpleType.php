<?php
declare(strict_types=1);
namespace Thunder\Xsdragon\Schema;

use Thunder\Xsdragon\Utility\XsdUtility;

final class SimpleType
{
    /** @var string */
    private $namespaceUri;
    /** @var string */
    private $name;
    /** @var ?string */
    private $documentation;
    /** @var null|Union|Restrictions */
    private $type;

    public function __construct(string $namespaceUri, ?string $name, ?string $documentation, $type)
    {
        if(false === (null === $type || (is_object($type) && in_array(get_class($type), [Restrictions::class, Union::class], true)))) {
            throw new \InvalidArgumentException(sprintf('SimpleType type can be either null, Restrictions or Union, `%s` given!', XsdUtility::describe($type)));
        }

        $this->namespaceUri = $namespaceUri;
        $this->name = $name;
        $this->documentation = $documentation;
        $this->type = $type;
    }

    public function getNamespaceUri(): string
    {
        return $this->namespaceUri;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getDocumentation(): ?string
    {
        return $this->documentation;
    }

    public function getType()
    {
        return $this->type;
    }
}
