<?php
declare(strict_types=1);
namespace Thunder\Xsdragon\Schema;

final class Sequence
{
    /** @var string */
    private $namespaceUri;
    /** @var Element[] */
    private $elements;

    public function __construct(string $namespaceUri, array $elements)
    {
        $this->namespaceUri = $namespaceUri;
        $this->elements = $elements;
    }

    public function getNamespaceUri(): string
    {
        return $this->namespaceUri;
    }

    public function getElements(): array
    {
        return $this->elements;
    }
}
