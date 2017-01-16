<?php
declare(strict_types=1);
namespace Thunder\Xsdragon\Schema;

final class Extension
{
    /** @var string */
    private $namespaceUri;
    /** @var string */
    private $base;
    /** @var Element[] */
    private $elements;
    /** @var Attribute[] */
    private $attributes = [];

    public function __construct(string $namespaceUri, string $base, array $elements, array $attributes)
    {
        $this->namespaceUri = $namespaceUri;
        $this->base = $base;
        $this->elements = $elements;
        $this->attributes = $attributes;
    }

    public function getNamespaceUri(): string
    {
        return $this->namespaceUri;
    }

    public function getBase(): string
    {
        return $this->base;
    }

    public function getElements(): array
    {
        return $this->elements;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }
}
