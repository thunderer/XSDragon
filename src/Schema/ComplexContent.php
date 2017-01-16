<?php
declare(strict_types=1);
namespace Thunder\Xsdragon\Schema;

final class ComplexContent
{
    /** @var string */
    private $namespaceUri;
    /** @var string */
    private $base;
    /** @var Extension|Restrictions */
    private $type;

    public function __construct(string $namespaceUri, string $base, $type)
    {
        $this->namespaceUri = $namespaceUri;
        $this->base = $base;
        // FIXME: validate types from property docblock
        $this->type = $type;
    }

    public function getNamespaceUri(): string
    {
        return $this->namespaceUri;
    }

    public function getBase(): string
    {
        return $this->base;
    }

    public function getType()
    {
        return $this->type;
    }
}
