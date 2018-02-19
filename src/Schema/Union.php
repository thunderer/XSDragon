<?php
declare(strict_types=1);
namespace Thunder\Xsdragon\Schema;

final class Union
{
    /** @var string */
    private $namespaceUri;
    /** @var string[] */
    private $memberTypes = [];
    /** @var SimpleType[] */
    private $simpleTypes = [];

    public function __construct(string $namespaceUri, array $memberTypes, array $simpleTypes)
    {
        $this->namespaceUri = $namespaceUri;
        $this->memberTypes = $memberTypes;
        $this->simpleTypes = $simpleTypes;
    }

    public function getNamespaceUri(): string
    {
        return $this->namespaceUri;
    }

    public function getMemberTypes(): array
    {
        return $this->memberTypes;
    }

    public function getSimpleTypes(): array
    {
        return $this->simpleTypes;
    }
}
