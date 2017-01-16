<?php
declare(strict_types=1);
namespace Thunder\Xsdragon\Schema;

final class Union
{
    /** @var string */
    private $namespaceUri;
    /** @var string[] */
    private $memberTypes = [];

    public function __construct(string $namespaceUri, array $memberTypes)
    {
        $this->namespaceUri = $namespaceUri;
        $this->memberTypes = $memberTypes;
    }

    public function getNamespaceUri(): string
    {
        return $this->namespaceUri;
    }

    public function getMemberTypes(): array
    {
        return $this->memberTypes;
    }
}
