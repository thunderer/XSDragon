<?php
declare(strict_types=1);
namespace Thunder\Xsdragon\Tests\Schema;

use PHPUnit\Framework\TestCase;
use Thunder\Xsdragon\Schema\Attribute;
use Thunder\Xsdragon\Schema\Extension;
use Thunder\Xsdragon\Schema\Restrictions;
use Thunder\Xsdragon\Schema\SimpleType;

final class ExtensionTest extends TestCase
{
    public function testExtension()
    {
        $restriction = Restrictions::createFromPatterns('xsd:string', ['\d']);
        $simpleType = new SimpleType('ns', 'name', 'doc', $restriction);
        $attribute = new Attribute('ns', 'name', 'type', 'optional');
        $extension = new Extension('ns', 'base', [$simpleType], [$attribute]);

        $this->assertSame('ns', $extension->getNamespaceUri());
        $this->assertSame('base', $extension->getBase());
        $this->assertSame([$simpleType], $extension->getElements());
        $this->assertSame([$attribute], $extension->getAttributes());
    }
}
