<?php
declare(strict_types=1);
namespace Thunder\Xsdragon\Tests\Schema;

use PHPUnit\Framework\TestCase;
use Thunder\Xsdragon\Schema\ComplexContent;

final class ComplexContentTest extends TestCase
{
    public function testComplexContent()
    {
        $complexContent = new ComplexContent('ns', 'base', 'type');

        $this->assertSame('ns', $complexContent->getNamespaceUri());
        $this->assertSame('base', $complexContent->getBase());
        $this->assertSame('type', $complexContent->getType());
    }
}
