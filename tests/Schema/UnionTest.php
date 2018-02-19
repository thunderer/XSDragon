<?php
declare(strict_types=1);
namespace Thunder\Xsdragon\Tests\Schema;

use PHPUnit\Framework\TestCase;
use Thunder\Xsdragon\Schema\SimpleType;
use Thunder\Xsdragon\Schema\Union;

final class UnionTest extends TestCase
{
    public function testUnion()
    {
        $simpleType = new SimpleType('ns', 'name', 'doc', null);
        $union = new Union('ns', ['type', 'other'], [$simpleType]);

        $this->assertSame('ns', $union->getNamespaceUri());
        $this->assertSame(['type', 'other'], $union->getMemberTypes());
        $this->assertSame([$simpleType], $union->getSimpleTypes());
    }
}
