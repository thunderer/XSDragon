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

    public function __construct(string $namespaceUri, string $name = null, string $documentation = null, $type)
    {
        if(false === (null === $type || (is_object($type) && in_array(get_class($type), [Restrictions::class, Union::class, ListNode::class], true)))) {
            throw new \InvalidArgumentException(sprintf('SimpleType type can be either null, Restrictions, Union, or List, `%s` given!', XsdUtility::describe($type)));
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

    public function getName()
    {
        return $this->name;
    }

    public function getDocumentation()
    {
        return $this->documentation;
    }

    public function getType()
    {
        return $this->type;
    }
}
