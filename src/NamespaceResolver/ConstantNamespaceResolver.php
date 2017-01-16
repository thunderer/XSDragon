<?php
declare(strict_types=1);
namespace Thunder\Xsdragon\NamespaceResolver;

final class ConstantNamespaceResolver implements NamespaceResolverInterface
{
    /** @var string */
    private $baseNs;

    public function __construct(string $baseNs)
    {
        $this->baseNs = $baseNs;
    }

    public function convertFqcnToPath(string $ns): string
    {
        return $ns;
    }

    public function convertUriToFqcn(string $uri, string $name): string
    {
        return $this->baseNs.'\\'.ucfirst($name);
    }

    public function convertUriToNs(string $ns): string
    {
        return $this->baseNs;
    }

    public function convertNameToClassName(string $name): string
    {
        return $name;
    }
}
