<?php
namespace Thunder\Xsdragon\NamespaceResolver;

interface NamespaceResolverInterface
{
    public function convertFqcnToPath(string $ns): string;

    public function convertUriToFqcn(string $uri, string $name): string;

    public function convertUriToNs(string $ns): string;

    public function convertNameToClassName(string $name): string;
}
